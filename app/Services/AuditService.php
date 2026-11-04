<?php

namespace App\Services;

use App\Shared\Http\ClientIp;
use Core\Database;
use Core\Logger;
use Core\Session;
use Core\Tenant\TenantContext;
use PDO;

/**
 * Journal d'audit des actions sensibles, PAR ÉTABLISSEMENT (table `audit_logs`, migration T065).
 *
 * ÉCRITURE
 *  - `log*()` : au mieux (best effort). Si l'écriture échoue, l'opération métier continue mais l'événement n'est JAMAIS perdu en silence : il est
 *    écrit dans un fichier de secours (`audit_fallback-AAAA-MM-JJ.jsonl`), l'erreur va au journal technique et une alerte est affichée aux seuls
 *    titulaires de `audit.view` de l'établissement concerné (bandeau). `database/audit-rejouer.php` réinscrit ensuite les événements.
 *  - `logStrict()` / `logCreateStrict()` / `logUpdateStrict()` : OPÉRATIONS CRITIQUES. L'écriture se fait sur la connexion PDO et dans la transaction de
 *    l'opération métier, sans repli : en cas d'échec, `AuditIndisponibleException` est levée et l'appelant annule la transaction.
 *  - `etablissement_id` vient EXCLUSIVEMENT du contexte tenant validé (`TenantContext::current()`). Sans contexte : NULL. Jamais l'établissement par
 *    défaut, jamais déduit de l'utilisateur (un compte peut appartenir à plusieurs établissements).
 *  - L'acteur est figé (nom, rôle) pour survivre à la suppression du compte ; aucun nom n'est conservé pour les rôles élève et parent.
 *
 * LECTURE : toutes les méthodes exigent un identifiant d'établissement explicite et filtrent dessus (jamais de lecture sans établissement).
 *
 * Tolérance de schéma : sur une base où T065 n'est pas encore appliquée, seules les colonnes existantes sont écrites (comportement historique).
 */
class AuditService
{
    public const PAR_PAGE = 50;
    private const JOURS_ALERTE = 30;

    /** Valeurs jamais écrites (recherche par sous-chaîne dans le nom de la clé). */
    private static array $sensitiveKeys = [
        'password', 'mot_de_passe', 'mdp', 'token', 'reset_token',
        'api_key', 'vapid_private_key', 'smtp_password', 'sms_api_key',
        '_csrf_token', 'secret',
    ];

    /** Données personnelles non nécessaires à la traçabilité (noms d'élèves, coordonnées) : clés exactes, remplacées par ***. */
    private static array $clesPersonnelles = [
        'prenom', 'nom_eleve', 'eleve_nom', 'prenom_eleve', 'eleve_prenom', 'nom_complet', 'nom_prenom', 'payeur_nom',
        'telephone', 'adresse', 'date_naissance', 'lieu_naissance', 'email',
    ];

    private static array $rolesSansNom = ['eleve', 'parent'];

    private static int $echecs = 0;
    private static ?string $dossierSecours = null;
    /** @var array<string,list<string>> colonnes de audit_logs par connexion */
    private static array $colonnes = [];
    /** @var array<int,array{nom:?string,role:?string}> */
    private static array $acteurs = [];

    // ── Écriture au mieux (best effort) ─────────────────────────────────────────────────────────────────────────────────────

    public function log(
        ?int    $userId,
        string  $action,
        string  $module,
        ?string $entite   = null,
        ?int    $entiteId = null,
        ?array  $avant    = null,
        ?array  $apres    = null
    ): void {
        $this->ecrire($this->ligne($userId, $action, $module, $entite, $entiteId, $avant, $apres));
    }

    public function logCreate(int $userId, string $module, string $entite, ?int $newId, array $data): void
    {
        $this->log($userId, 'create', $module, $entite, $newId, null, $data);
    }

    public function logUpdate(int $userId, string $module, string $entite, int $id, array $avant, array $apres): void
    {
        $diff = $this->diff($avant, $apres);
        if (empty($diff['avant']) && empty($diff['apres'])) {
            return; // Aucun changement réel — ne pas polluer les logs
        }
        $this->log($userId, 'update', $module, $entite, $id, $diff['avant'], $diff['apres']);
    }

    public function logDelete(int $userId, string $module, string $entite, int $id, array $snapshot): void
    {
        $this->log($userId, 'delete', $module, $entite, $id, $snapshot, null);
    }

    /**
     * Connexion (réussie ou non). $etablissementId n'est fourni que lorsqu'il est établi sans ambiguïté (session venant d'être ouverte) ; sinon NULL.
     */
    public function logLogin(?int $userId, string $email, bool $success, string $ip, ?int $etablissementId = null): void
    {
        $ligne = $this->ligne($userId, $success ? 'login' : 'login_failed', 'auth', 'users', $userId, null, ['email_empreinte' => substr(hash('sha256', mb_strtolower($email)), 0, 16)],
            null, $success ? 'success' : 'failure');
        $ligne['etablissement_id'] = $etablissementId;
        $ligne['ip'] = $ip !== '' ? mb_substr($ip, 0, 45) : $ligne['ip'];
        $this->ecrire($ligne);
    }

    public function logPermissionDenied(int $userId, string $permission, string $route): void
    {
        $this->log($userId, 'permission_denied', 'auth', null, null, null, [
            'permission' => $permission,
            'route'      => $route,
        ]);
    }

    // ── Écriture CRITIQUE : dans la transaction de l'opération, sans repli ───────────────────────────────────────────────────

    /**
     * @throws AuditIndisponibleException si la ligne n'a pas pu être écrite (l'appelant doit annuler sa transaction)
     */
    public function logStrict(
        PDO     $pdo,
        ?int    $userId,
        string  $action,
        string  $module,
        ?string $entite   = null,
        ?int    $entiteId = null,
        ?array  $avant    = null,
        ?array  $apres    = null
    ): void {
        try {
            $this->inserer($this->ligne($userId, $action, $module, $entite, $entiteId, $avant, $apres, $pdo), $pdo);
        } catch (\Throwable $e) {
            try {
                Logger::error('[AuditService] écriture critique refusée (' . $module . '.' . $action . ') : ' . get_class($e) . ' ' . $e->getMessage());
            } catch (\Throwable) {
                @error_log('[AuditService] écriture critique refusée (' . $module . '.' . $action . ')');
            }
            throw new AuditIndisponibleException($e);
        }
    }

    public function logCreateStrict(PDO $pdo, int $userId, string $module, string $entite, ?int $newId, array $data): void
    {
        $this->logStrict($pdo, $userId, 'create', $module, $entite, $newId, null, $data);
    }

    public function logUpdateStrict(PDO $pdo, int $userId, string $module, string $entite, int $id, array $avant, array $apres): void
    {
        $diff = $this->diff($avant, $apres);
        if (empty($diff['avant']) && empty($diff['apres'])) {
            return;
        }
        $this->logStrict($pdo, $userId, 'update', $module, $entite, $id, $diff['avant'], $diff['apres']);
    }

    // ── Lecture : établissement explicite OBLIGATOIRE ───────────────────────────────────────────────────────────────────────

    /** La migration T065 est-elle appliquée (colonne etablissement_id présente) ? Sans elle, aucune lecture par établissement n'est possible. */
    public function schemaPret(): bool
    {
        return in_array('etablissement_id', $this->colonnes(Database::getInstance()->getConnection()), true);
    }

    /**
     * @param array{q?:string,user_id?:int|string,module?:string,action?:string,entite?:string,entite_id?:int|string,date_debut?:string,date_fin?:string,resultat?:string} $filtres
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, par_page:int}
     */
    public function rechercher(int $etablissementId, array $filtres, int $page = 1, int $parPage = self::PAR_PAGE): array
    {
        $this->exigerEtablissement($etablissementId);
        [$where, $params] = $this->filtres($etablissementId, $filtres);
        $pdo = Database::getInstance()->getConnection();
        $parPage = max(10, min(200, $parPage));

        $c = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al {$where}");
        $c->execute($params);
        $total = (int)$c->fetchColumn();
        $pages = max(1, (int)ceil($total / $parPage));
        $page  = max(1, min($page, $pages));

        $s = $pdo->prepare(
            'SELECT al.*, ' . self::LIBELLE_ACTEUR . " AS acteur_libelle FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             {$where} ORDER BY al.id DESC LIMIT " . $parPage . ' OFFSET ' . (($page - 1) * $parPage)
        );
        $s->execute($params);
        return ['rows' => $s->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages, 'par_page' => $parPage];
    }

    /** Un événement DE CET ÉTABLISSEMENT, ou null (un identifiant appartenant à un autre établissement est indiscernable d'un identifiant inexistant). */
    public function trouver(int $etablissementId, int $id): ?array
    {
        $this->exigerEtablissement($etablissementId);
        $s = Database::getInstance()->getConnection()->prepare(
            'SELECT al.*, ' . self::LIBELLE_ACTEUR . ' AS acteur_libelle FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
              WHERE al.etablissement_id = ? AND al.id = ?'
        );
        $s->execute([$etablissementId, $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function getEntityHistory(int $etablissementId, string $entite, int $id, int $limit = 50): array
    {
        $this->exigerEtablissement($etablissementId);
        $s = Database::getInstance()->getConnection()->prepare(
            'SELECT al.*, ' . self::LIBELLE_ACTEUR . ' AS acteur_libelle FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
              WHERE al.etablissement_id = ? AND al.entite = ? AND al.entite_id = ? ORDER BY al.id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $s->execute([$etablissementId, $entite, $id]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function getUserHistory(int $etablissementId, int $userId, int $limit = 100): array
    {
        $this->exigerEtablissement($etablissementId);
        $s = Database::getInstance()->getConnection()->prepare(
            'SELECT al.* FROM audit_logs al WHERE al.etablissement_id = ? AND al.user_id = ? ORDER BY al.id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $s->execute([$etablissementId, $userId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<string> */
    public function modules(int $etablissementId): array
    {
        return $this->distincts($etablissementId, 'module');
    }

    /** @return list<string> */
    public function actions(int $etablissementId): array
    {
        return $this->distincts($etablissementId, 'action');
    }

    /** Acteurs ayant des événements DANS cet établissement (pour le filtre) : jamais la liste des utilisateurs d'un autre établissement. @return list<array{id:int,libelle:string}> */
    public function acteurs(int $etablissementId): array
    {
        $this->exigerEtablissement($etablissementId);
        $s = Database::getInstance()->getConnection()->prepare(
            'SELECT al.user_id AS id, MAX(' . self::LIBELLE_ACTEUR . ') AS libelle FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
              WHERE al.etablissement_id = ? AND al.user_id IS NOT NULL GROUP BY al.user_id ORDER BY libelle'
        );
        $s->execute([$etablissementId]);
        return array_map(static fn($r) => ['id' => (int)$r['id'], 'libelle' => (string)($r['libelle'] ?? ('#' . $r['id']))], $s->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Libellé de l'acteur : instantané si présent ; sinon (anciennes lignes) nom du compte, sauf élève et parent dont le nom n'est jamais affiché. */
    private const LIBELLE_ACTEUR = "COALESCE(al.actor_nom, CASE WHEN u.role IN ('eleve','parent') THEN NULL ELSE NULLIF(TRIM(CONCAT_WS(' ', u.prenom, u.nom)), '') END)";

    private function exigerEtablissement(int $etablissementId): void
    {
        if ($etablissementId <= 0) {
            throw new \InvalidArgumentException('Un établissement explicite est obligatoire pour lire le journal d\'audit.');
        }
    }

    /** @return list<string> */
    private function distincts(int $etablissementId, string $colonne): array
    {
        $this->exigerEtablissement($etablissementId);
        $s = Database::getInstance()->getConnection()->prepare("SELECT DISTINCT {$colonne} FROM audit_logs WHERE etablissement_id = ? ORDER BY {$colonne}");
        $s->execute([$etablissementId]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array{0:string, 1:list<mixed>} */
    private function filtres(int $etablissementId, array $f): array
    {
        $w = ['al.etablissement_id = ?'];
        $p = [$etablissementId];
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $w[] = '(al.action LIKE ? OR al.module LIKE ? OR al.entite LIKE ? OR al.actor_nom LIKE ? OR CAST(al.entite_id AS CHAR) = ?)';
            array_push($p, $like, $like, $like, $like, $q);
        }
        if (($f['user_id'] ?? '') !== '' && (int)$f['user_id'] > 0) {
            $w[] = 'al.user_id = ?';
            $p[] = (int)$f['user_id'];
        }
        foreach (['module', 'action', 'entite'] as $c) {
            if (trim((string)($f[$c] ?? '')) !== '') {
                $w[] = "al.{$c} = ?";
                $p[] = trim((string)$f[$c]);
            }
        }
        if (($f['entite_id'] ?? '') !== '' && (int)$f['entite_id'] > 0) {
            $w[] = 'al.entite_id = ?';
            $p[] = (int)$f['entite_id'];
        }
        if (self::dateValide($f['date_debut'] ?? '')) {
            $w[] = 'al.created_at >= ?';
            $p[] = $f['date_debut'] . ' 00:00:00';
        }
        if (self::dateValide($f['date_fin'] ?? '')) {
            $w[] = 'al.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $p[] = $f['date_fin'] . ' 00:00:00';
        }
        if (in_array($f['resultat'] ?? '', ['success', 'failure'], true)) {
            $w[] = 'al.resultat = ?';
            $p[] = $f['resultat'];
        }
        return ['WHERE ' . implode(' AND ', $w), $p];
    }

    private static function dateValide(mixed $d): bool
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    }

    // ── Fichier de secours, alerte, rejeu ────────────────────────────────────────────────────────────────────────────────────

    /** Dossier du fichier de secours (surchargeable pour les tests ; sinon AUDIT_FALLBACK_DIR, sinon storage/logs). */
    public static function definirDossierSecours(?string $dossier): void
    {
        self::$dossierSecours = $dossier;
    }

    public static function dossierSecours(): string
    {
        if (self::$dossierSecours !== null) {
            return self::$dossierSecours;
        }
        $env = getenv('AUDIT_FALLBACK_DIR');
        return ($env !== false && $env !== '') ? $env : (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/storage/logs';
    }

    /** Nombre d'écritures au mieux ayant échoué pendant la requête courante. */
    public static function echecsDeLaRequete(): int
    {
        return self::$echecs;
    }

    public static function reinitialiserCompteur(): void
    {
        self::$echecs = 0;
    }

    /** Réservé aux tests : oublie le schéma et les acteurs mémorisés (changement de base en cours de processus). */
    public static function oublierSchema(): void
    {
        self::$colonnes = [];
        self::$acteurs  = [];
    }

    /**
     * Événements en attente de rejeu ET attribués à cet établissement (30 derniers jours) : c'est le seul chiffre qu'un établissement voit.
     *
     * @return array{count:int, derniere:?string}
     */
    public static function resumeSecours(int $etablissementId): array
    {
        $count = 0; $derniere = null;
        $limite = date('Y-m-d', strtotime('-' . self::JOURS_ALERTE . ' days'));
        foreach (glob(self::dossierSecours() . '/audit_fallback-*.jsonl') ?: [] as $f) {
            if (!preg_match('/audit_fallback-(\d{4}-\d{2}-\d{2})\.jsonl$/', $f, $m) || $m[1] < $limite) {
                continue;
            }
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $brut) {
                $l = json_decode($brut, true);
                if (is_array($l) && isset($l['etablissement_id']) && (int)$l['etablissement_id'] === $etablissementId) {
                    $count++;
                    $derniere = max($derniere ?? '', $m[1]);
                }
            }
        }
        return ['count' => $count, 'derniere' => $derniere];
    }

    /**
     * Nombre total d'événements en attente (tous établissements, y compris sans établissement) : réservé aux scripts d'administration du serveur,
     * jamais affiché dans l'interface d'un établissement.
     */
    public static function totalSecours(): int
    {
        $n = 0;
        foreach (glob(self::dossierSecours() . '/audit_fallback-*.jsonl') ?: [] as $f) {
            $n += count(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        }
        return $n;
    }

    /**
     * Rejoue dans la base les événements du fichier de secours (date et établissement d'origine conservés). Les lignes rejouées sont retirées du
     * fichier ; un fichier entièrement rejoué est renommé « .rejoue » (jamais supprimé).
     *
     * @return array{rejoues:int, echecs:int}
     */
    public function rejouerSecours(?PDO $pdo = null): array
    {
        $pdo ??= Database::getInstance()->getConnection();
        $rejoues = 0; $echecs = 0;
        foreach (glob(self::dossierSecours() . '/audit_fallback-*.jsonl') ?: [] as $fichier) {
            $restantes = [];
            foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $brut) {
                $ligne = json_decode($brut, true);
                if (!is_array($ligne) || !isset($ligne['action'], $ligne['module'])) {
                    $restantes[] = $brut; $echecs++;
                    continue;
                }
                unset($ligne['_cause']);
                try {
                    $this->inserer($ligne, $pdo, true);
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

    // ── Privé ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> ligne prête à écrire */
    private function ligne(?int $userId, string $action, string $module, ?string $entite, ?int $entiteId, ?array $avant, ?array $apres, ?PDO $pdo = null, string $resultat = 'success'): array
    {
        $userId = ($userId !== null && $userId > 0) ? $userId : null;   // 0 n'est pas un compte : la clé étrangère le refuserait
        $acteur = $this->acteur($userId, $pdo);
        return [
            'etablissement_id' => TenantContext::current(),    // contexte tenant validé UNIQUEMENT : sans contexte NULL, jamais l'établissement par défaut
            'user_id'          => $userId,
            'actor_nom'        => $acteur['nom'],
            'actor_role'       => $acteur['role'],
            'action'           => mb_substr($action, 0, 50),
            'module'           => mb_substr($module, 0, 50),
            'entite'           => $entite !== null ? mb_substr($entite, 0, 100) : null,
            'entite_id'        => $entiteId,
            'avant'            => $avant !== null ? json_encode($this->sanitize($avant), JSON_UNESCAPED_UNICODE) : null,
            'apres'            => $apres !== null ? json_encode($this->sanitize($apres), JSON_UNESCAPED_UNICODE) : null,
            'ip'               => $this->ip(),
            'user_agent'       => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'resultat'         => $resultat,
            'created_at'       => date('Y-m-d H:i:s'),
        ];
    }

    /** @return array{nom:?string, role:?string} */
    private function acteur(?int $userId, ?PDO $pdo): array
    {
        if ($userId === null) {
            return ['nom' => null, 'role' => null];
        }
        if (isset(self::$acteurs[$userId])) {
            return self::$acteurs[$userId];
        }
        $nom = null; $role = null;
        $session = Session::getUser();
        if (is_array($session) && (int)($session['id'] ?? 0) === $userId && isset($session['role'])) {
            $nom  = trim(((string)($session['prenom'] ?? '')) . ' ' . ((string)($session['nom'] ?? '')));
            $role = (string)$session['role'];
        } else {
            try {
                $s = ($pdo ?? Database::getInstance()->getConnection())->prepare('SELECT nom, prenom, role FROM users WHERE id = ?');
                $s->execute([$userId]);
                if (($u = $s->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $nom  = trim(((string)$u['prenom']) . ' ' . ((string)$u['nom']));
                    $role = (string)$u['role'];
                }
            } catch (\Throwable) {
                // L'instantané est un complément : son absence n'empêche pas l'écriture de l'événement.
            }
        }
        if ($role !== null && in_array($role, self::$rolesSansNom, true)) {
            $nom = null;    // aucun nom d'élève ou de parent dans le journal
        }
        return self::$acteurs[$userId] = ['nom' => ($nom !== null && $nom !== '') ? mb_substr($nom, 0, 150) : null, 'role' => $role !== null ? mb_substr($role, 0, 30) : null];
    }

    private function ip(): string
    {
        if (PHP_SAPI === 'cli' && !isset($_SERVER['REMOTE_ADDR'])) {
            return 'cli';
        }
        return ClientIp::resolve($_SERVER, ClientIp::trustedFromEnvironment());   // X-Forwarded-For n'est lu que d'un proxy de confiance configuré
    }

    /** @return list<string> */
    private function colonnes(PDO $pdo): array
    {
        $cle = (string)spl_object_id($pdo);
        if (!isset(self::$colonnes[$cle])) {
            $colonnes = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'")
                ->fetchAll(PDO::FETCH_COLUMN);
            if ($colonnes === []) {
                return [];      // table momentanément absente (panne, migration en cours) : ne JAMAIS mémoriser ce constat
            }
            self::$colonnes[$cle] = $colonnes;
        }
        return self::$colonnes[$cle];
    }

    /**
     * INSERT sur la connexion donnée. Ne retient que les colonnes existantes (base non migrée). `created_at` n'est écrit que pour un rejeu
     * (sinon l'horloge de la base fait foi). Lève l'exception PDO en cas d'échec.
     *
     * @param array<string,mixed> $ligne
     */
    private function inserer(array $ligne, ?PDO $pdo = null, bool $conserverDate = false): void
    {
        $pdo ??= Database::getInstance()->getConnection();
        $existantes = $this->colonnes($pdo);
        if ($existantes === []) {
            throw new \RuntimeException('Table audit_logs absente.');
        }
        $cols = array_values(array_filter(array_keys($ligne), static fn($c) => in_array($c, $existantes, true) && ($c !== 'created_at' || $conserverDate)));
        $stmt = $pdo->prepare('INSERT INTO audit_logs (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        $stmt->execute(array_map(static fn($c) => $ligne[$c], $cols));
    }

    /** Écriture au mieux : en cas d'échec, secours + journal technique + compteur (l'événement n'est jamais perdu en silence). */
    private function ecrire(array $ligne): void
    {
        try {
            $this->inserer($ligne);
        } catch (\Throwable $e) {
            self::$echecs++;
            $this->mettreDeCote($ligne, get_class($e));
        }
    }

    private function mettreDeCote(array $ligne, string $cause): void
    {
        $ligne['_cause'] = $cause;
        $dossier = self::dossierSecours();
        $ecrit = false;
        if (is_dir($dossier) || @mkdir($dossier, 0775, true)) {
            $ecrit = @file_put_contents($dossier . '/audit_fallback-' . date('Y-m-d') . '.jsonl',
                json_encode($ligne, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX) !== false;
        }
        $message = '[AuditService] écriture d\'audit échouée : ' . ($ligne['module'] ?? '?') . '.' . ($ligne['action'] ?? '?')
            . ' établissement=' . ($ligne['etablissement_id'] ?? 'NULL') . ' cause=' . $cause . ' secours=' . ($ecrit ? 'écrit' : 'ECHEC');
        try {
            Logger::error($message);
        } catch (\Throwable) {
            @error_log($message);
        }
    }

    private function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string)$key);
            $masked = in_array($lower, self::$clesPersonnelles, true);
            if (!$masked) {
                foreach (self::$sensitiveKeys as $sensitive) {
                    if (str_contains($lower, $sensitive)) {
                        $masked = true;
                        break;
                    }
                }
            }
            if ($masked) {
                $clean[$key] = '***';
            } else {
                $clean[$key] = is_array($value) ? $this->sanitize($value) : (is_string($value) && mb_strlen($value) > 2000 ? mb_substr($value, 0, 2000) . '…' : $value);
            }
        }
        return $clean;
    }

    public function diff(array $avant, array $apres): array
    {
        $changedAvant = [];
        $changedApres = [];
        $allKeys = array_unique(array_merge(array_keys($avant), array_keys($apres)));

        foreach ($allKeys as $key) {
            $a = $avant[$key] ?? null;
            $b = $apres[$key] ?? null;
            if ($a !== $b) {
                $changedAvant[$key] = $a;
                $changedApres[$key] = $b;
            }
        }

        return [
            'avant' => $this->sanitize($changedAvant),
            'apres' => $this->sanitize($changedApres),
        ];
    }
}
