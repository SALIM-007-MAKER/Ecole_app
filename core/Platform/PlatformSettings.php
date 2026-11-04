<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use DateTimeZone;
use PDO;

/**
 * Paramètres globaux de la plateforme (étape 6).
 *
 * Priorité d'une valeur : surcharge en base (`platform_settings`) > variable d'environnement > valeur par défaut du code.
 * Une valeur vide enregistrée = suppression de la surcharge (retour à l'environnement / au défaut).
 *
 * Lecture : sans erreur et sans coût si la table n'existe pas encore (migration T071 non appliquée) ou si aucune connexion n'est ouverte
 * (code « pur » des règles d'abonnement) : on retombe alors sur l'environnement. Le contenu de la table est lu UNE fois par processus.
 *
 * Écriture : transaction (point de sauvegarde si l'appelant en a une) contenant la trace d'audit globale en mode strict (`settings.updated`,
 * uniquement les clés modifiées, ancienne et nouvelle valeur) : audit impossible = aucune modification.
 *
 * Aucun secret n'est géré ici (mots de passe, clés API restent dans le .env).
 */
final class PlatformSettings
{
    public const DEVISE = 'devise_reference';
    public const FUSEAU = 'fuseau';
    public const ESSAI = 'essai_defaut_jours';
    public const GRACE = 'grace_defaut_jours';
    public const PAIEMENT = 'paiement_instructions';
    public const DEUX_FACTEURS = 'deux_facteurs_obligatoire';

    /** @var array<string,array{libelle:string,aide:string,type:string,env:?string,defaut:string}> */
    public const DEFINITIONS = [
        self::DEVISE => [
            'libelle' => 'Devise de référence', 'type' => 'devise', 'env' => 'PLATFORM_DEFAULT_CURRENCY', 'defaut' => 'XOF',
            'aide' => 'Devise proposée pour un nouveau plan (3 lettres, ex. XOF). Aucun montant existant n\'est converti.',
        ],
        self::FUSEAU => [
            'libelle' => 'Fuseau horaire', 'type' => 'fuseau', 'env' => 'PLATFORM_TIMEZONE', 'defaut' => 'Africa/Niamey',
            'aide' => 'Définit le « jour » des abonnements et des paiements (bascule à minuit local). Un changement décale les prochaines échéances ; les dates déjà enregistrées ne sont pas modifiées.',
        ],
        self::ESSAI => [
            'libelle' => 'Durée d\'essai par défaut (jours)', 'type' => 'entier:0:365', 'env' => null, 'defaut' => '0',
            'aide' => 'Valeur proposée à la création d\'un plan. Les plans existants et les abonnements en cours ne changent pas.',
        ],
        self::GRACE => [
            'libelle' => 'Jours de grâce par défaut', 'type' => 'entier:0:90', 'env' => null, 'defaut' => '0',
            'aide' => 'Valeur proposée à la création d\'un plan. Les plans existants et les abonnements en cours ne changent pas.',
        ],
        self::DEUX_FACTEURS => [
            'libelle' => 'Double authentification des opérateurs', 'type' => 'bool', 'env' => 'PLATFORM_REQUIRE_2FA', 'defaut' => '0',
            'aide' => 'Si obligatoire, tout opérateur sans 2FA est conduit à l\'activer dès sa prochaine connexion (1 = obligatoire, 0 = facultative). Vous devez avoir activé la vôtre avant de la rendre obligatoire.',
        ],
        self::PAIEMENT => [
            'libelle' => 'Coordonnées de paiement', 'type' => 'texte:500', 'env' => null, 'defaut' => '',
            'aide' => 'Texte affiché aux opérateurs sur la fiche d\'un établissement et sur les reçus (n° Orange Money, banque, bénéficiaire…). Pas de mot de passe ni de secret.',
        ],
    ];

    /** @var array<int,array<string,string>> contenu de la table, par connexion ; un tableau vide si la table est absente */
    private static array $cache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /** Oublie le contenu lu (après une écriture, ou entre deux scénarios de test). */
    public static function oublier(): void
    {
        self::$cache = [];
    }

    /** La table est-elle là (T071 appliquée) ? */
    public static function disponible(PDO $pdo): bool
    {
        try {
            return (int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_settings'")->fetchColumn() === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Valeur EFFECTIVE d'un réglage (surcharge > environnement > défaut). Ne lève jamais d'exception. */
    public static function valeur(string $cle): string
    {
        return self::resoudre($cle)['valeur'];
    }

    /** Pour le code « pur » : lit la surcharge uniquement si une connexion est déjà ouverte dans ce processus. */
    public static function valeurSiConnecte(string $cle): string
    {
        return self::resoudre($cle, false)['valeur'];
    }

    /** @return array{valeur:string,source:string} source : base | environnement | defaut */
    private static function resoudre(string $cle, bool $connecter = true): array
    {
        $def = self::DEFINITIONS[$cle] ?? null;
        if ($def === null) {
            return ['valeur' => '', 'source' => 'defaut'];
        }
        $base = self::surcharges($connecter)[$cle] ?? null;
        if ($base !== null && $base !== '' && self::valide($cle, $base) === null) {
            return ['valeur' => $base, 'source' => 'base'];
        }
        if ($def['env'] !== null) {
            $env = trim((string)($_ENV[$def['env']] ?? getenv($def['env']) ?: ''));
            $env = self::normalisee($cle, $env);
            if ($env !== '' && self::valide($cle, $env) === null) {
                return ['valeur' => $env, 'source' => 'environnement'];
            }
        }
        return ['valeur' => $def['defaut'], 'source' => 'defaut'];
    }

    /** @return array<string,string> */
    private static function surcharges(bool $connecter): array
    {
        if (!$connecter && !Database::estConnectee()) {
            return [];
        }
        try {
            $pdo = Database::getInstance()->getConnection();
        } catch (\Throwable) {
            return [];
        }
        $id = spl_object_id($pdo);
        if (!isset(self::$cache[$id])) {
            self::$cache[$id] = [];
            try {
                foreach ($pdo->query('SELECT cle, valeur FROM platform_settings')->fetchAll(PDO::FETCH_ASSOC) as $l) {
                    if ($l['valeur'] !== null) {
                        self::$cache[$id][(string)$l['cle']] = (string)$l['valeur'];
                    }
                }
            } catch (\Throwable) {
                // Table absente (T071 non appliquée) : aucune surcharge.
            }
        }
        return self::$cache[$id];
    }

    /**
     * État de chaque réglage pour l'écran : valeur effective, surcharge éventuelle, source, auteur et date de la dernière modification.
     *
     * @return array<string,array<string,mixed>>
     */
    public function tous(): array
    {
        $lignes = [];
        if (self::disponible($this->pdo)) {
            foreach ($this->pdo->query(
                'SELECT s.cle, s.valeur, s.updated_at, o.email FROM platform_settings s LEFT JOIN platform_operators o ON o.id = s.updated_by'
            )->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $lignes[$l['cle']] = $l;
            }
        }
        $r = [];
        foreach (self::DEFINITIONS as $cle => $def) {
            $effective = self::resoudre($cle);
            $r[$cle] = $def + [
                'cle' => $cle, 'effective' => $effective['valeur'], 'source' => $effective['source'],
                'surcharge' => $lignes[$cle]['valeur'] ?? null, 'modifie_le' => $lignes[$cle]['updated_at'] ?? null, 'modifie_par' => $lignes[$cle]['email'] ?? null,
            ];
        }
        return $r;
    }

    /**
     * Enregistre les réglages soumis (clés connues seulement ; une clé absente de $donnees n'est pas touchée, une valeur vide supprime la
     * surcharge). Renvoie la liste des clés réellement modifiées (vide = rien à faire, aucune écriture ni audit).
     *
     * @param array<string,mixed> $donnees
     * @return list<string>
     * @throws PlanValidationException erreurs de saisie (rien n'est écrit)
     * @throws PlanAuditException audit impossible ou erreur technique (rien n'est écrit)
     */
    public function enregistrer(array $donnees, int $operatorId): array
    {
        $erreurs = [];
        $nouveau = [];
        foreach (self::DEFINITIONS as $cle => $def) {
            if (!array_key_exists($cle, $donnees)) {
                continue;
            }
            $v = trim(str_replace("\r\n", "\n", (string)$donnees[$cle]));
            if ($v === '') {
                $nouveau[$cle] = null;
                continue;
            }
            $v = self::normalisee($cle, $v);
            if (($e = self::valide($cle, $v)) !== null) {
                $erreurs[] = $def['libelle'] . ' : ' . $e;
                continue;
            }
            $nouveau[$cle] = $v;
        }
        if ($erreurs !== []) {
            throw new PlanValidationException($erreurs);
        }
        if (!self::disponible($this->pdo)) {
            throw new PlanAuditException('Les paramètres globaux ne sont pas encore activés (migration T071 à appliquer).');
        }

        $point = null;
        if ($this->pdo->inTransaction()) {
            $point = 'set_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT $point");
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $courant = [];
            foreach ($this->pdo->query('SELECT cle, valeur FROM platform_settings FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $courant[$l['cle']] = $l['valeur'];
            }
            $avant = [];
            $apres = [];
            foreach ($nouveau as $cle => $v) {
                $ancien = $courant[$cle] ?? null;
                if ($ancien === '') {
                    $ancien = null;
                }
                if ($ancien === $v) {
                    continue;
                }
                $avant[$cle] = $ancien;
                $apres[$cle] = $v;
                if ($v === null) {
                    $this->pdo->prepare('DELETE FROM platform_settings WHERE cle = ?')->execute([$cle]);
                } else {
                    $this->pdo->prepare('INSERT INTO platform_settings (cle, valeur, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_by = VALUES(updated_by)')
                        ->execute([$cle, $v, $operatorId ?: null]);
                }
            }
            if ($apres !== []) {
                try {
                    PlatformAuditService::make($this->pdo)->recordPar($operatorId, 'settings.updated', [
                        'entity_type' => 'platform_settings', 'avant' => $avant, 'apres' => $apres, 'strict' => true,
                    ]);
                } catch (\Throwable $e) {
                    Logger::security('PLATFORM_SETTINGS_AUDIT_ABORTED', "settings.updated par operator_id={$operatorId} : journal d'audit indisponible (" . get_class($e) . ')');
                    throw new PlanAuditException('Opération annulée : la trace d\'audit n\'a pas pu être enregistrée. Aucune modification n\'a été appliquée.', 0, $e);
                }
            }
            $point !== null ? $this->pdo->exec("RELEASE SAVEPOINT $point") : $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($point !== null) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $point");
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PlanAuditException) {
                throw $e;
            }
            $ref = strtoupper(bin2hex(random_bytes(3)));
            Logger::error("Paramètres [{$ref}] " . get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            throw new PlanAuditException("Opération impossible pour une raison technique (référence {$ref}). Aucune modification n'a été appliquée.", 0, $e);
        }
        self::oublier();
        return array_keys($apres);
    }

    /** Forme canonique avant validation / stockage. */
    private static function normalisee(string $cle, string $v): string
    {
        return match (self::DEFINITIONS[$cle]['type'] ?? '') {
            'devise' => strtoupper($v),
            'fuseau' => $v,
            'bool' => match (strtolower($v)) { '1', 'true', 'yes', 'on', 'oui' => '1', '0', 'false', 'no', 'off', 'non' => '0', default => $v },
            default => $v,
        };
    }

    /** Message d'erreur, ou null si la valeur est acceptable pour cette clé. */
    private static function valide(string $cle, string $v): ?string
    {
        $type = self::DEFINITIONS[$cle]['type'] ?? '';
        if ($type === 'devise') {
            return preg_match('/^[A-Z]{3}$/', $v) ? null : 'trois lettres majuscules attendues (ex. XOF).';
        }
        if ($type === 'bool') {
            return $v === '0' || $v === '1' ? null : 'choisissez 0 (facultative) ou 1 (obligatoire).';
        }
        if ($type === 'fuseau') {
            if (strlen($v) > 64 || !in_array($v, DateTimeZone::listIdentifiers(), true)) {
                return 'fuseau inconnu (ex. Africa/Niamey, Africa/Abidjan, Europe/Paris).';
            }
            return null;
        }
        if (str_starts_with($type, 'entier:')) {
            [, $min, $max] = explode(':', $type);
            if (!preg_match('/^\d{1,4}$/', $v) || (int)$v < (int)$min || (int)$v > (int)$max) {
                return "nombre entier entre {$min} et {$max} attendu.";
            }
            return null;
        }
        if (str_starts_with($type, 'texte:')) {
            $max = (int)substr($type, 6);
            if (mb_strlen($v) > $max) {
                return "{$max} caractères maximum.";
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)) {
                return 'caractères de contrôle interdits.';
            }
            return null;
        }
        return 'réglage inconnu.';
    }
}
