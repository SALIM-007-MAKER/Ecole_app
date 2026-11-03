<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use Core\Session;
use PDO;

/**
 * Journal d'audit GLOBAL de la plateforme (table `platform_audit_logs`, ajout seul).
 *
 * - `record()` écrit un événement. Une panne du journal ne bloque JAMAIS l'opération métier, mais elle n'est jamais passée sous silence :
 *   l'événement complet est écrit dans un fichier de secours (storage/logs/platform_audit_fallback-AAAA-MM-JJ.jsonl), l'erreur est
 *   consignée dans le journal technique, un avertissement est affiché à l'opérateur et le tableau de bord signale les événements non
 *   enregistrés tant qu'ils n'ont pas été rejoués (`replayFallback()`, ou `php database/audit-rejouer.php`).
 * - Option `strict` => true (opérations dont la trace conditionne la validité, ex. création d'un plan, dans UNE transaction) : en cas d'échec
 *   l'exception est relancée, sans fichier de secours ni avertissement (l'appelant annule l'opération : rien n'est à rejouer).
 * - `record()` renvoie true uniquement si la ligne est réellement dans la base : l'appelant ne doit jamais présenter l'opération comme
 *   auditée dans le cas contraire.
 * - Lecture : recherche/filtres/pagination (`search`), détail (`find`), export (`iterer`). Aucune méthode de modification ni de suppression.
 *
 * Indépendant des modules scolaires : ne dépend ni de `audit_logs` (journal par établissement) ni de `Core\Session::getUser()`.
 */
final class PlatformAuditService
{
    public const PAR_PAGE = 50;
    public const EXPORT_MAX = 50000;
    private const JOURS_ALERTE = 30;

    /** Clés dont la valeur n'est jamais écrite dans le journal. */
    private const SENSIBLES = ['password', 'mot_de_passe', 'mdp', 'password_hash', 'token', 'secret', 'api_key', '_csrf_token', 'current_password', 'new_password'];

    private static int $echecsRequete = 0;
    private static ?string $dossierSecours = null;

    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    public static function make(?PDO $pdo = null): self
    {
        return new self($pdo);
    }

    /** Dossier du fichier de secours (surchargeable pour les tests). */
    public static function definirDossierSecours(?string $dossier): void
    {
        self::$dossierSecours = $dossier;
    }

    public static function dossierSecours(): string
    {
        if (self::$dossierSecours !== null) {
            return self::$dossierSecours;
        }
        $env = getenv('PLATFORM_AUDIT_FALLBACK_DIR');   // dossier inscriptible alternatif (stockage en lecture seule, tests)
        return ($env !== false && $env !== '') ? $env : (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/storage/logs';
    }

    /** Nombre d'écritures du journal ayant échoué pendant la requête courante. */
    public static function echecsDeLaRequete(): int
    {
        return self::$echecsRequete;
    }

    public static function reinitialiserCompteur(): void
    {
        self::$echecsRequete = 0;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @param array{entity_type?:?string, entity_id?:?int, etablissement_id?:?int, avant?:?array, apres?:?array,
     *              resultat?:string, strict?:bool, actor?:array{type:string, id?:?int, email?:?string, niveau?:?string}, actor_email?:?string} $o
     * @return bool true = ligne présente dans platform_audit_logs ; false = repli sur le fichier de secours
     */
    public function record(string $action, array $o = []): bool
    {
        $acteur = $o['actor'] ?? $this->acteurCourant($o['actor_email'] ?? null);
        $resultat = in_array($o['resultat'] ?? 'success', ['success', 'failure', 'denied'], true) ? ($o['resultat'] ?? 'success') : 'success';
        $ligne = [
            'occurred_at'      => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.v'),
            'actor_type'       => $acteur['type'],
            'operator_id'      => $acteur['id'] ?? null,
            'actor_email'      => isset($acteur['email']) ? mb_substr((string)$acteur['email'], 0, 191) : null,
            'actor_niveau'     => $acteur['niveau'] ?? null,
            'etablissement_id' => isset($o['etablissement_id']) ? (int)$o['etablissement_id'] : null,
            'action'           => mb_substr($action, 0, 80),
            'entity_type'      => isset($o['entity_type']) ? mb_substr((string)$o['entity_type'], 0, 60) : null,
            'entity_id'        => isset($o['entity_id']) ? (int)$o['entity_id'] : null,
            'resultat'         => $resultat,
            'ancienne_valeur'  => isset($o['avant']) ? $this->enJson($o['avant']) : null,
            'nouvelle_valeur'  => isset($o['apres']) ? $this->enJson($o['apres']) : null,
            'ip'               => $this->ip(),
            'user_agent'       => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ];

        try {
            $this->inserer($ligne);
            return true;
        } catch (\Throwable $e) {
            if (!empty($o['strict'])) {
                throw $e;
            }
            self::$echecsRequete++;
            $this->enregistrerSecours($ligne, get_class($e));
            return false;
        }
    }

    /**
     * Comme record(), mais l'acteur est l'opérateur d'identifiant donné (instantané de son e-mail et de son niveau relu en base) : pour les
     * services métier qui reçoivent l'identifiant de l'opérateur en paramètre et ne dépendent donc pas de la session. 0 = système.
     *
     * @param array<string,mixed> $o mêmes options que record()
     */
    public function recordPar(int $operatorId, string $action, array $o = []): bool
    {
        $acteur = ['type' => 'system', 'id' => null, 'email' => null, 'niveau' => 'system'];
        if ($operatorId > 0) {
            $acteur = ['type' => 'operator', 'id' => $operatorId, 'email' => null, 'niveau' => null];
            try {
                $pdo = $this->pdo ?? Database::getInstance()->getConnection();
                $s = $pdo->prepare('SELECT email, niveau FROM platform_operators WHERE id = ?');
                $s->execute([$operatorId]);
                if (($r = $s->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $acteur['email'] = $r['email'];
                    $acteur['niveau'] = $r['niveau'];
                }
            } catch (\Throwable) {
                // L'instantané est facultatif : l'identifiant suffit, l'événement est enregistré quand même.
            }
        }
        return $this->record($action, ['actor' => $acteur] + $o);
    }

    /** @param array<string,mixed> $ligne */
    private function inserer(array $ligne): void
    {
        $pdo = $this->pdo ?? Database::getInstance()->getConnection();
        $cols = array_keys($ligne);
        $stmt = $pdo->prepare(
            'INSERT INTO platform_audit_logs (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')'
        );
        $stmt->execute(array_values($ligne));
    }

    /** @param array<string,mixed> $ligne */
    private function enregistrerSecours(array $ligne, string $cause): void
    {
        $ligne['_cause'] = $cause;
        $dossier = self::dossierSecours();
        $ecrit = false;
        if (is_dir($dossier) || @mkdir($dossier, 0775, true)) {
            $fichier = $dossier . '/platform_audit_fallback-' . date('Y-m-d') . '.jsonl';
            $ecrit = @file_put_contents($fichier, json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX) !== false;
        }
        try {
            Logger::security(
                'PLATFORM_AUDIT_WRITE_FAILED',
                "action={$ligne['action']} operateur=" . ($ligne['actor_email'] ?? '?') . " cause={$cause} secours=" . ($ecrit ? 'ecrit' : 'ECHEC')
            );
        } catch (\Throwable) {
            @error_log("PLATFORM_AUDIT_WRITE_FAILED action={$ligne['action']} secours=" . ($ecrit ? 'ecrit' : 'ECHEC'));
        }
        if (session_status() === PHP_SESSION_ACTIVE) {   // pas de session (script en ligne de commande) : le script signale lui-même l'échec
            Session::flash('audit_warning', "L'opération « {$ligne['action']} » a été effectuée mais sa traçabilité n'a PAS pu être enregistrée dans le journal d'audit"
                . ($ecrit ? ' (enregistrement de secours écrit sur le serveur).' : ' ET l\'enregistrement de secours a aussi échoué : prévenez l\'administrateur du serveur.'));
        }
    }

    /** @return array{type:string, id:?int, email:?string, niveau:?string} */
    private function acteurCourant(?string $emailSaisi): array
    {
        $op = PlatformAuth::current();
        if ($op !== null) {
            return ['type' => 'operator', 'id' => (int)$op['id'], 'email' => (string)$op['email'], 'niveau' => (string)$op['niveau']];
        }
        return ['type' => 'anonymous', 'id' => null, 'email' => $emailSaisi, 'niveau' => null];
    }

    /** @param array<mixed> $donnees */
    private function enJson(array $donnees): string
    {
        return json_encode($this->masquer($donnees), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }

    /** @param array<mixed> $donnees @return array<mixed> */
    private function masquer(array $donnees): array
    {
        foreach ($donnees as $cle => $valeur) {
            if (is_string($cle) && in_array(strtolower($cle), self::SENSIBLES, true)) {
                $donnees[$cle] = '***';
            } elseif (is_array($valeur)) {
                $donnees[$cle] = $this->masquer($valeur);
            } elseif (is_string($valeur) && mb_strlen($valeur) > 2000) {
                $donnees[$cle] = mb_substr($valeur, 0, 2000) . '…';
            }
        }
        return $donnees;
    }

    private function ip(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? mb_substr($ip, 0, 45) : 'cli';
    }

    // ── Fichier de secours : état et rejeu ─────────────────────────────────────────────────────────────────────────────────

    /** @return array{count:int, derniere:?string, fichiers:list<string>} événements en attente de rejeu (30 derniers jours) */
    public static function resumeSecours(): array
    {
        $count = 0; $derniere = null; $fichiers = [];
        $limite = date('Y-m-d', strtotime('-' . self::JOURS_ALERTE . ' days'));
        foreach (glob(self::dossierSecours() . '/platform_audit_fallback-*.jsonl') ?: [] as $f) {
            if (!preg_match('/platform_audit_fallback-(\d{4}-\d{2}-\d{2})\.jsonl$/', $f, $m) || $m[1] < $limite) {
                continue;
            }
            $lignes = array_filter(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
            if ($lignes === []) {
                continue;
            }
            $count += count($lignes);
            $fichiers[] = basename($f);
            $derniere = max($derniere ?? '', $m[1]);
        }
        return ['count' => $count, 'derniere' => $derniere, 'fichiers' => $fichiers];
    }

    /**
     * Rejoue dans la base les événements du fichier de secours (une fois la base de nouveau disponible). Les lignes rejouées sont retirées
     * du fichier ; un fichier entièrement rejoué est renommé en « .rejoue » (jamais supprimé).
     *
     * @return array{rejoues:int, echecs:int}
     */
    public function rejouerSecours(): array
    {
        $rejoues = 0; $echecs = 0;
        foreach (glob(self::dossierSecours() . '/platform_audit_fallback-*.jsonl') ?: [] as $fichier) {
            $restantes = [];
            foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $brut) {
                $ligne = json_decode($brut, true);
                if (!is_array($ligne)) {
                    $restantes[] = $brut; $echecs++;
                    continue;
                }
                unset($ligne['_cause']);
                try {
                    $this->inserer($ligne);
                    $rejoues++;
                } catch (\Throwable) {
                    $restantes[] = $brut; $echecs++;
                }
            }
            if ($restantes === []) {
                @rename($fichier, $fichier . '.rejoue');
            } else {
                @file_put_contents($fichier, implode("\n", $restantes) . "\n", LOCK_EX);
            }
        }
        return ['rejoues' => $rejoues, 'echecs' => $echecs];
    }

    // ── Lecture ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @param array{q?:string, date_from?:string, date_to?:string, operator_id?:int|string, action?:string, etablissement_id?:int|string, resultat?:string} $f
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, par_page:int}
     */
    public function search(array $f, int $page = 1, int $parPage = self::PAR_PAGE): array
    {
        [$where, $params] = $this->filtres($f);
        $pdo = $this->pdo ?? Database::getInstance()->getConnection();
        $parPage = max(10, min(200, $parPage));

        $c = $pdo->prepare("SELECT COUNT(*) FROM platform_audit_logs l {$where}");
        $c->execute($params);
        $total = (int)$c->fetchColumn();
        $pages = max(1, (int)ceil($total / $parPage));
        $page = max(1, min($page, $pages));

        $s = $pdo->prepare(
            "SELECT l.*, e.nom AS etablissement_nom FROM platform_audit_logs l
             LEFT JOIN etablissements e ON e.id = l.etablissement_id
             {$where} ORDER BY l.id DESC LIMIT " . $parPage . ' OFFSET ' . (($page - 1) * $parPage)
        );
        $s->execute($params);
        return ['rows' => $s->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages, 'par_page' => $parPage];
    }

    public function find(int $id): ?array
    {
        $pdo = $this->pdo ?? Database::getInstance()->getConnection();
        $s = $pdo->prepare(
            'SELECT l.*, e.nom AS etablissement_nom FROM platform_audit_logs l
             LEFT JOIN etablissements e ON e.id = l.etablissement_id WHERE l.id = ?'
        );
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<string> actions distinctes présentes dans le journal (pour le filtre) */
    public function actions(): array
    {
        $pdo = $this->pdo ?? Database::getInstance()->getConnection();
        return $pdo->query('SELECT DISTINCT action FROM platform_audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<array{id:int, email:string}> opérateurs ayant des entrées (pour le filtre) */
    public function operateurs(): array
    {
        $pdo = $this->pdo ?? Database::getInstance()->getConnection();
        return $pdo->query(
            "SELECT DISTINCT operator_id AS id, actor_email AS email FROM platform_audit_logs
             WHERE operator_id IS NOT NULL ORDER BY actor_email"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Parcourt les lignes correspondant aux filtres (export) sans tout charger en mémoire. @return \Generator<array<string,mixed>> */
    public function iterer(array $f, int $max = self::EXPORT_MAX): \Generator
    {
        [$where, $params] = $this->filtres($f);
        $pdo = $this->pdo ?? Database::getInstance()->getConnection();
        $s = $pdo->prepare(
            "SELECT l.*, e.nom AS etablissement_nom FROM platform_audit_logs l
             LEFT JOIN etablissements e ON e.id = l.etablissement_id
             {$where} ORDER BY l.id DESC LIMIT " . max(1, min(self::EXPORT_MAX, $max))
        );
        $s->execute($params);
        while (($row = $s->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $row;
        }
    }

    /** @return array{0:string, 1:list<mixed>} */
    private function filtres(array $f): array
    {
        $w = []; $p = [];
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $w[] = '(l.action LIKE ? OR l.actor_email LIKE ? OR l.entity_type LIKE ? OR l.ip LIKE ? OR CAST(l.entity_id AS CHAR) = ?)';
            array_push($p, $like, $like, $like, $like, $q);
        }
        if (self::dateValide($f['date_from'] ?? '')) {
            $w[] = 'l.occurred_at >= ?';
            $p[] = $f['date_from'] . ' 00:00:00';
        }
        if (self::dateValide($f['date_to'] ?? '')) {
            $w[] = 'l.occurred_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $p[] = $f['date_to'] . ' 00:00:00';
        }
        if (($f['operator_id'] ?? '') !== '' && (int)$f['operator_id'] > 0) {
            $w[] = 'l.operator_id = ?';
            $p[] = (int)$f['operator_id'];
        }
        if (trim((string)($f['action'] ?? '')) !== '') {
            $w[] = 'l.action = ?';
            $p[] = trim((string)$f['action']);
        }
        if (($f['etablissement_id'] ?? '') !== '' && (int)$f['etablissement_id'] > 0) {
            $w[] = 'l.etablissement_id = ?';
            $p[] = (int)$f['etablissement_id'];
        }
        if (in_array($f['resultat'] ?? '', ['success', 'failure', 'denied'], true)) {
            $w[] = 'l.resultat = ?';
            $p[] = $f['resultat'];
        }
        return [$w === [] ? '' : 'WHERE ' . implode(' AND ', $w), $p];
    }

    private static function dateValide(mixed $d): bool
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    }
}
