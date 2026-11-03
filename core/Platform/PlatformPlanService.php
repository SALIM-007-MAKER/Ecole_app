<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use PDO;

/**
 * CRUD des plans SaaS (`platform_plans`, existe depuis la Phase 14.2) — Phase 14.10, complété à l'étape 3 du Super-Admin SaaS (T066).
 *
 * Les LIMITES (quotas) vivent sur le plan et sont copiées sur l'établissement à l'assignation (PlatformEtablissementService::assignPlan()) :
 * un changement de plan ne modifie jamais rétroactivement les établissements déjà assignés. Les conditions commerciales (prix, devise, essai,
 * grâce) ne sont PAS encore figées par établissement : ce sera le rôle de l'abonnement (étape 4).
 *
 * Règles :
 *  - modification PARTIELLE : une clé absente des données conserve la valeur existante (jamais de remise à une valeur par défaut) ;
 *  - le code d'un plan est immuable ; un plan n'est jamais supprimé, seulement archivé (`actif` = 0) ;
 *  - changer le prix ou la devise d'un plan utilisé exige la confirmation explicite `confirmer_conditions` et est audité avant/après ;
 *  - aucune conversion de devise : les montants sont stockés tels que saisis, dans la devise du plan.
 */
final class PlatformPlanService
{
    /** Variable d'environnement de la devise par défaut proposée pour un nouveau plan. */
    public const ENV_DEVISE_DEFAUT = 'PLATFORM_DEFAULT_CURRENCY';
    public const DEVISE_DEFAUT_REPLI = 'XOF';

    /** Colonnes lues pour l'audit (ancienne / nouvelle valeur). */
    private const CHAMPS_AUDIT = [
        'code', 'nom', 'description', 'max_users', 'max_eleves', 'storage_quota_mb', 'api_calls_per_day', 'modules_inclus',
        'prix_mensuel', 'prix_annuel', 'devise', 'duree_essai_jours', 'jours_grace', 'ordre', 'visible_catalogue', 'actif',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /** Devise proposée par défaut (configuration, jamais codée en dur dans le traitement). */
    public static function deviseReference(): string
    {
        $v = strtoupper(trim((string)($_ENV[self::ENV_DEVISE_DEFAUT] ?? getenv(self::ENV_DEVISE_DEFAUT) ?: '')));
        return preg_match('/^[A-Z]{3}$/', $v) ? $v : self::DEVISE_DEFAUT_REPLI;
    }

    /**
     * Clés de modules connues (toutes les clés de config/modules.php, activées ou non : cocher un module dans un plan ne l'active pas).
     * @return list<string>
     */
    public static function modulesConnus(): array
    {
        $fichier = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/config/modules.php';
        $registre = is_file($fichier) ? require $fichier : [];
        return is_array($registre) ? array_values(array_map('strval', array_keys($registre))) : [];
    }

    public function all(bool $inclureArchives = true): array
    {
        $sql = 'SELECT * FROM platform_plans' . ($inclureArchives ? '' : ' WHERE actif = 1') . ' ORDER BY ordre ASC, id ASC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_plans WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Nombre d'établissements (non supprimés) actuellement sur ce plan. */
    public function usageCount(int $planId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM etablissements WHERE plan_id = ? AND deleted_at IS NULL");
        $stmt->execute([$planId]);
        return (int)$stmt->fetchColumn();
    }

    // ── Validation ────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Fusionne les données reçues avec le plan existant (ou les valeurs de création) et valide le résultat.
     * Une clé absente conserve la valeur existante ; en création, elle prend la valeur par défaut.
     *
     * @return array<string,mixed> valeurs normalisées, prêtes pour la base (modules_inclus en liste PHP)
     * @throws PlanValidationException
     */
    public function normaliser(array $data, ?array $existant): array
    {
        $erreurs = [];
        $creation = $existant === null;
        $a = static fn(string $k): bool => array_key_exists($k, $data);

        // Valeurs de départ : plan existant, sinon valeurs de création.
        $v = $existant ?? [
            'code' => '', 'nom' => '', 'description' => null, 'max_users' => 50, 'max_eleves' => 500, 'storage_quota_mb' => 1024,
            'api_calls_per_day' => 10000, 'modules_inclus' => '[]', 'prix_mensuel' => '0', 'prix_annuel' => '0',
            'devise' => self::deviseReference(), 'duree_essai_jours' => 0, 'jours_grace' => 0, 'ordre' => 0, 'visible_catalogue' => 1, 'actif' => 1,
        ];

        // Code : saisi à la création seulement, immuable ensuite.
        if ($creation) {
            $code = strtolower(trim((string)($data['code'] ?? '')));
            if (!preg_match('/^[a-z0-9_-]{2,30}$/', $code)) {
                $erreurs[] = 'Code de plan invalide (2 à 30 caractères : lettres minuscules, chiffres, tirets).';
            }
            $v['code'] = $code;
        } elseif ($a('code') && strtolower(trim((string)$data['code'])) !== (string)$existant['code']) {
            $erreurs[] = 'Le code d\'un plan ne peut pas être modifié.';
        }

        if ($a('nom') || $creation) {
            $nom = trim((string)($data['nom'] ?? ''));
            if (mb_strlen($nom) < 2 || mb_strlen($nom) > 100) {
                $erreurs[] = 'Le nom est obligatoire (2 à 100 caractères).';
            }
            $v['nom'] = $nom;
        }

        if ($a('description')) {
            $d = trim((string)$data['description']);
            if (mb_strlen($d) > 2000) {
                $erreurs[] = 'La description est trop longue (2000 caractères maximum).';
            }
            $v['description'] = $d === '' ? null : $d;
        }

        // Entiers bornés
        $entiers = [
            'max_users'         => ['Nombre maximum d\'utilisateurs', 1, 65535],
            'max_eleves'        => ['Nombre maximum d\'élèves', 1, 65535],
            'storage_quota_mb'  => ['Quota de stockage (Mo)', 1, 4294967295],
            'api_calls_per_day' => ['Appels API par jour', 1, 4294967295],
            'duree_essai_jours' => ['Durée d\'essai (jours)', 0, 365],
            'jours_grace'       => ['Jours de grâce', 0, 90],
            'ordre'             => ['Ordre d\'affichage', -32768, 32767],
        ];
        foreach ($entiers as $cle => [$libelle, $min, $max]) {
            if (!$a($cle)) { continue; }
            $brut = $data[$cle];
            if (!is_int($brut) && !(is_string($brut) && preg_match('/^-?\d{1,10}$/', trim($brut)))) {
                $erreurs[] = "{$libelle} : entier attendu.";
                continue;
            }
            $n = (int)$brut;
            if ($n < $min || $n > $max) {
                $erreurs[] = "{$libelle} : valeur comprise entre {$min} et {$max} attendue.";
                continue;
            }
            $v[$cle] = $n;
        }

        // Booléen (convention du projet : filter_var) : toute autre valeur est refusée au lieu d'être lue comme « masqué ».
        if ($a('visible_catalogue')) {
            $brut = $data['visible_catalogue'];
            $bool = (is_bool($brut) || is_int($brut) || (is_string($brut) && trim($brut) !== ''))
                ? filter_var(is_string($brut) ? trim($brut) : $brut, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            if ($bool === null) {
                $erreurs[] = 'Visibilité dans le catalogue : valeur booléenne attendue (0 ou 1).';
            } else {
                $v['visible_catalogue'] = $bool ? 1 : 0;
            }
        }

        // Devise : 3 lettres majuscules (ISO 4217). Les devises déjà présentes (DZD…) restent valides.
        if ($a('devise')) {
            $devise = strtoupper(trim((string)$data['devise']));
            if (!preg_match('/^[A-Z]{3}$/', $devise)) {
                $erreurs[] = 'Devise invalide : code ISO de 3 lettres attendu (ex. XOF).';
            } else {
                $v['devise'] = $devise;
            }
        }

        // Prix : validés d'après la devise FINALE ; chaîne décimale stricte, jamais de flottant.
        foreach (['prix_mensuel' => 'Prix mensuel', 'prix_annuel' => 'Prix annuel'] as $cle => $libelle) {
            if (!$a($cle)) { continue; }
            $brut = str_replace(',', '.', trim((string)$data[$cle]));
            $entierSeul = $v['devise'] === 'XOF';
            $motif = $entierSeul ? '/^\d{1,8}(\.0{1,2})?$/' : '/^\d{1,8}(\.\d{1,2})?$/';
            if (!preg_match($motif, $brut)) {
                $erreurs[] = $entierSeul
                    ? "{$libelle} : montant entier positif attendu (le XOF n'a pas de décimales)."
                    : "{$libelle} : montant positif attendu (2 décimales au maximum).";
                continue;
            }
            $v[$cle] = number_format((float)$brut, 2, '.', '');
        }
        // Le XOF n'a pas de décimales : un montant existant devenu XOF via un changement de devise doit lui aussi être entier.
        if ($v['devise'] === 'XOF') {
            foreach (['prix_mensuel', 'prix_annuel'] as $cle) {
                if (!$a($cle) && fmod((float)$v[$cle], 1.0) !== 0.0) {
                    $erreurs[] = ($cle === 'prix_mensuel' ? 'Prix mensuel' : 'Prix annuel') . ' : saisir un montant entier pour le XOF.';
                }
            }
        }

        // Modules : uniquement des clés connues de config/modules.php, sans doublon.
        if ($a('modules_inclus') || $a('modules_present')) {
            $liste = $data['modules_inclus'] ?? [];
            if (!is_array($liste)) {
                $erreurs[] = 'Modules : liste attendue.';
            } else {
                $connus = self::modulesConnus();
                $propres = [];
                foreach ($liste as $m) {
                    if (!is_string($m) || trim($m) === '') {
                        $erreurs[] = 'Module : valeur invalide (nom de module attendu, sous forme de texte non vide).';
                        continue;
                    }
                    $m = trim($m);
                    if (!in_array($m, $connus, true)) {
                        $erreurs[] = 'Module inconnu : « ' . mb_substr($m, 0, 40) . ' ».';
                        continue;
                    }
                    $propres[$m] = $m;
                }
                $v['modules_inclus'] = array_values($propres);
            }
        } elseif (is_string($v['modules_inclus'])) {
            $decode = json_decode($v['modules_inclus'], true);
            $v['modules_inclus'] = is_array($decode) ? array_values($decode) : [];
        }

        if ($erreurs !== []) {
            throw new PlanValidationException(array_values(array_unique($erreurs)));
        }
        return $v;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Création atomique : l'INSERT et la trace d'audit (stricte) réussissent ensemble ou sont annulés ensemble.
     *
     * @return int identifiant du plan créé
     * @throws PlanValidationException  données invalides ou code déjà pris (y compris collision avec une requête concurrente)
     * @throws PlanAuditException       la trace d'audit n'a pas pu être écrite : le plan n'est PAS créé
     */
    public function create(array $data, int $operatorUserId): int
    {
        $v = $this->normaliser($data, null);

        $existing = $this->pdo->prepare("SELECT id FROM platform_plans WHERE code = ?");
        $existing->execute([$v['code']]);
        if ($existing->fetchColumn() !== false) {
            throw new PlanValidationException(["Le code de plan « {$v['code']} » existe déjà."]);
        }

        $id = $this->transaction(function () use ($v, $operatorUserId): int {
            $stmt = $this->pdo->prepare(
                "INSERT INTO platform_plans (code, nom, description, max_users, max_eleves, storage_quota_mb, api_calls_per_day, modules_inclus,
                                             prix_mensuel, prix_annuel, devise, duree_essai_jours, jours_grace, ordre, visible_catalogue, actif)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            try {
                $stmt->execute([
                    $v['code'], $v['nom'], $v['description'], $v['max_users'], $v['max_eleves'], $v['storage_quota_mb'], $v['api_calls_per_day'],
                    json_encode($v['modules_inclus']), $v['prix_mensuel'], $v['prix_annuel'], $v['devise'],
                    $v['duree_essai_jours'], $v['jours_grace'], $v['ordre'], $v['visible_catalogue'],
                ]);
            } catch (\PDOException $e) {
                // 1062 = clé en double : un autre opérateur a créé ce code entre la vérification et l'insertion. Jamais d'erreur SQL brute.
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    throw new PlanValidationException(["Le code de plan « {$v['code']} » existe déjà."]);
                }
                throw $e;
            }
            $id = (int)$this->pdo->lastInsertId();

            // Trace STRICTE dans la même transaction : si elle ne peut pas être écrite, le plan n'est pas créé.
            $this->tracerStrict($operatorUserId, 'plan.created', 'Création annulée', "Aucun plan n'a été créé.", [
                'entity_type' => 'platform_plan', 'entity_id' => $id, 'apres' => $this->instantane($id),
            ], "code={$v['code']}");
            return $id;
        });

        Logger::security('PLATFORM_PLAN_CREATED', "plan_id={$id} code={$v['code']} par operator_id={$operatorUserId}");
        return $id;
    }

    /**
     * Modification PARTIELLE. Renvoie false si le plan n'existe pas ou si rien ne change.
     *
     * @param array<string,mixed> $data  `confirmer_conditions` = 1 requis pour changer prix/devise d'un plan utilisé
     * @throws PlanValidationException
     */
    public function update(int $id, array $data, int $operatorUserId): bool
    {
        $existant = $this->find($id);
        if ($existant === null) {
            return false;
        }
        $avant = $this->instantane($id);
        $v = $this->normaliser($data, $existant);

        $prixOuDeviseChange = $v['devise'] !== $existant['devise']
            || $v['prix_mensuel'] !== number_format((float)$existant['prix_mensuel'], 2, '.', '')
            || $v['prix_annuel'] !== number_format((float)$existant['prix_annuel'], 2, '.', '');
        $utilisations = $this->usageCount($id);
        $confirme = filter_var($data['confirmer_conditions'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($prixOuDeviseChange && $utilisations > 0 && !$confirme) {
            throw new PlanValidationException([
                "Ce plan est utilisé par {$utilisations} établissement(s) : modifier le prix ou la devise exige une confirmation explicite.",
            ]);
        }

        // Atomique : l'UPDATE, la date de modification et l'écriture d'audit réussissent ensemble ou sont tous annulés.
        $modifie = $this->transaction(function () use ($id, $v, $avant, $prixOuDeviseChange, $utilisations, $operatorUserId): bool {
            $stmt = $this->pdo->prepare(
                "UPDATE platform_plans SET nom = ?, description = ?, max_users = ?, max_eleves = ?, storage_quota_mb = ?, api_calls_per_day = ?,
                    modules_inclus = ?, prix_mensuel = ?, prix_annuel = ?, devise = ?, duree_essai_jours = ?, jours_grace = ?, ordre = ?,
                    visible_catalogue = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $v['nom'], $v['description'], $v['max_users'], $v['max_eleves'], $v['storage_quota_mb'], $v['api_calls_per_day'],
                json_encode($v['modules_inclus']), $v['prix_mensuel'], $v['prix_annuel'], $v['devise'],
                $v['duree_essai_jours'], $v['jours_grace'], $v['ordre'], $v['visible_catalogue'], $id,
            ]);

            $apres = $this->instantane($id);
            if ($apres === $avant) {
                return false;   // rien n'a changé : ni mise à jour de date, ni écriture d'audit
            }
            $this->pdo->prepare('UPDATE platform_plans SET updated_at = NOW() WHERE id = ?')->execute([$id]);
            if ($prixOuDeviseChange && $utilisations > 0) {
                $apres['confirmation_explicite'] = true;
                $apres['etablissements_concernes'] = $utilisations;
            }

            // Trace STRICTE dans la même transaction : si elle ne peut pas être écrite, la modification est annulée.
            $this->tracerStrict($operatorUserId, 'plan.updated', 'Modification annulée', 'Le plan est resté inchangé.', [
                'entity_type' => 'platform_plan', 'entity_id' => $id, 'avant' => $avant, 'apres' => $apres,
            ], "plan_id={$id}");
            return true;
        });

        if ($modifie) {
            Logger::security('PLATFORM_PLAN_UPDATED', "plan_id={$id} par operator_id={$operatorUserId}");
        }
        return $modifie;
    }

    /**
     * Archive (actif = 0) ou restaure (actif = 1) un plan. Les établissements déjà sur ce plan le conservent.
     * Idempotente : si le plan a déjà l'état demandé (ou n'existe pas), rien n'est modifié ni journalisé et false est renvoyé.
     */
    public function toggleActive(int $id, bool $active, int $operatorUserId): bool
    {
        $change = $this->transaction(function () use ($id, $active, $operatorUserId): bool {
            $stmt = $this->pdo->prepare('SELECT actif FROM platform_plans WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $actuel = $stmt->fetchColumn();
            if ($actuel === false || (int)$actuel === ($active ? 1 : 0)) {
                return false;
            }

            $this->pdo->prepare('UPDATE platform_plans SET actif = ?, updated_at = NOW() WHERE id = ?')->execute([$active ? 1 : 0, $id]);
            // Trace STRICTE dans la même transaction : si elle ne peut pas être écrite, l'état du plan n'est pas modifié.
            $this->tracerStrict($operatorUserId, 'plan.toggled', $active ? 'Restauration annulée' : 'Archivage annulé', "L'état du plan est resté inchangé.", [
                'entity_type' => 'platform_plan', 'entity_id' => $id,
                'avant' => ['actif' => (int)$actuel],
                'apres' => ['actif' => $active ? 1 : 0, 'etablissements_conserves' => $active ? null : $this->usageCount($id)],
            ], "plan_id={$id} actif=" . ($active ? '1' : '0'));
            return true;
        });

        if ($change) {
            Logger::security('PLATFORM_PLAN_TOGGLED', "plan_id={$id} actif=" . ($active ? '1' : '0') . " par operator_id={$operatorUserId}");
        }
        return $change;
    }

    /**
     * Écrit la trace d'audit en mode STRICT, à appeler DANS la transaction de l'opération : si l'écriture échoue, une PlanAuditException est
     * levée et l'opération métier est annulée avec elle (aucun fichier de secours : il n'y a rien à rejouer pour une opération annulée).
     *
     * @param array<string,mixed> $options options de PlatformAuditService::recordPar() (sans `strict`)
     * @throws PlanAuditException
     */
    private function tracerStrict(int $operatorId, string $action, string $debutMessage, string $suiteMessage, array $options, string $contexteLog): void
    {
        try {
            PlatformAuditService::make($this->pdo)->recordPar($operatorId, $action, $options + ['strict' => true]);
        } catch (\Throwable $e) {
            Logger::security('PLATFORM_PLAN_AUDIT_ABORTED', "{$action} {$contexteLog} par operator_id={$operatorId} : journal d'audit indisponible (" . get_class($e) . ')');
            throw new PlanAuditException("{$debutMessage} : la trace d'audit n'a pas pu être enregistrée. {$suiteMessage}", 0, $e);
        }
    }

    /**
     * Exécute $travail dans une transaction ; toute exception l'annule entièrement puis est relancée. Si une transaction englobante est
     * déjà ouverte (appelant, tests), un point de sauvegarde est utilisé : seule cette opération est annulée.
     *
     * @template T
     * @param callable():T $travail
     * @return T
     */
    private function transaction(callable $travail): mixed
    {
        if ($this->pdo->inTransaction()) {
            $point = 'plan_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT $point");
            try {
                $r = $travail();
                $this->pdo->exec("RELEASE SAVEPOINT $point");
                return $r;
            } catch (\Throwable $e) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $point");
                throw $e;
            }
        }
        $this->pdo->beginTransaction();
        try {
            $r = $travail();
            $this->pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    /** Valeurs d'un plan relues en base (ancienne/nouvelle valeur du journal d'audit). */
    private function instantane(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT ' . implode(', ', self::CHAMPS_AUDIT) . ' FROM platform_plans WHERE id = ?');
        $s->execute([$id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r === false) {
            return null;
        }
        $m = json_decode((string)$r['modules_inclus'], true);
        $r['modules_inclus'] = is_array($m) ? array_values($m) : [];
        return $r;
    }
}
