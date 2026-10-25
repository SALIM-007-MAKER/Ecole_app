<?php
/**
 * Tests de sécurité — bulletins officiels et brouillons : impression, QR de vérification, publication
 *
 * IMPRESSION (BulletinController::imprimer, vues réelles rendues) :
 *   - brouillon : filigrane « BROUILLON », AUCUN QR, aucun jeton dans le HTML ;
 *   - publié / archivé à jeton valide : QR encodant l'URL absolue construite à partir de APP_URL et du jeton de la colonne ;
 *   - publié mais jeton hérité / invalide / secret absent / APP_URL inutilisable : impression REFUSÉE (503), jamais dégradée ;
 *   - génération à la volée : jeton HMAC v2 cohérent (colonne = JSON), refusée sans secret, sans droit de génération.
 *
 * PUBLICATION (publier / archiver) : CSRF, interrupteur fermé par défaut, politique serveur, statut contrôlé, jeton
 * officiel exigé, transition atomique (une seule publication), audit SANS jeton complet. Aucune permission RBAC
 * n'est modifiée : les droits testés sont des listes de permissions injectées dans la session de test.
 *
 * Tout s'exécute dans une transaction annulée à la fin : aucune donnée ne subsiste.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinOfficialityTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}
$SECRET = hash('sha256', 'officialite-test-secret');       // jetable, propre à ce test
$_ENV['BULLETIN_TOKEN_SECRET'] = $SECRET;
unset($_ENV['BULLETIN_PUBLICATION_ENABLED'], $_ENV['TRUSTED_PROXIES']);
$_ENV['APP_URL'] = 'https://ecole.example.org';
$_ENV['APP_ENV'] = 'production';
$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
$_SESSION = [];

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\' => '/core/', 'App\\Controllers\\' => '/app/Controllers/', 'App\\Models\\' => '/app/Models/',
        'App\\Services\\' => '/app/Services/', 'App\\Modules\\' => '/app/Modules/', 'App\\Shared\\' => '/app/Shared/',
        'Tests\\Support\\' => '/tests/Support/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = ROOT_PATH . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
});

use App\Modules\Academique\Controllers\BulletinController;
use App\Modules\Academique\Events\BulletinArchived;
use App\Modules\Academique\Events\BulletinPublished;
use App\Modules\Academique\Listeners\BulletinHandler;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\BulletinTokenService;
use Core\Database;
use Core\EventDispatcher;
use Core\QrCode\QrEncoder;
use Core\Session;
use Core\Tenant\TenantContext;
use Tests\Support\BulletinFixtures;

$passed = 0; $failed = 0; $skipped = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function skip(string $label, string $why): void { global $skipped; $skipped++; echo "\033[33m  - SKIP $label ($why)\033[0m\n"; }
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

/** Signale une redirection (le contrôleur de production termine le script, ce qui est impossible à tester en direct). */
final class RedirectionTest extends \RuntimeException {}

class ControleurTest extends BulletinController
{
    public ?array $rendu = null;
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        if ($view === 'errors/403') { $this->rendu = [$view, $data]; return; }
        parent::render($view, $data, $layout);       // vraies vues pour l'impression
    }
    protected function redirect(string $url, int $status = 302): never { throw new RedirectionTest($url); }
    public function urlQr(array $row): string { return $this->urlQrOfficiel($row); }
}

function seConnecter(string $role, array $permissions, int $id = 1): array   // utilisateur réel : l'audit référence users(id)
{
    $user = ['id' => $id, 'nom' => 'Test', 'prenom' => 'Test', 'email' => "t$id@test.local", 'role' => $role,
             'permissions' => $permissions, 'etablissement_id' => 1, 'available_etablissements' => []];
    $_SESSION = ['_auth_user' => $user, '_csrf_token' => 'jeton-csrf-test'];
    return $user;
}

/** @return array{0:int, 1:string, 2:?array} statut HTTP, HTML produit, rendu capturé (403) */
function imprimer(int $eleveId, int $periodeId): array
{
    http_response_code(200);
    $c = new ControleurTest();
    ob_start();
    try { $c->imprimer((string)$eleveId, (string)$periodeId); } finally { $html = (string)ob_get_clean(); }
    return [http_response_code() ?: 200, $html, $c->rendu];
}

/** @return array{type:string, url:?string, code:int, rendu:?array} */
function agir(string $action, int $eleveId, int $periodeId, ?string $csrf = 'jeton-csrf-test'): array
{
    http_response_code(200);
    $_POST = $csrf === null ? [] : ['_csrf_token' => $csrf];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $c = new ControleurTest();
    try {
        $action === 'publier' ? $c->publier((string)$eleveId, (string)$periodeId) : $c->archiver((string)$eleveId, (string)$periodeId);
        $res = ['type' => 'rendu', 'url' => null];
    } catch (RedirectionTest $r) {
        $res = ['type' => 'redirection', 'url' => $r->getMessage()];
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    return $res + ['code' => http_response_code() ?: 200, 'rendu' => $c->rendu];
}

function journalPendant(callable $f): string
{
    $file = ROOT_PATH . '/storage/logs/security-' . date('Y-m-d') . '.log';
    clearstatcache();
    $avant = is_file($file) ? filesize($file) : 0;
    $f();
    clearstatcache();
    return is_file($file) && filesize($file) > $avant ? (string)substr((string)file_get_contents($file), $avant) : '';
}

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repo = new BulletinRepository();
EventDispatcher::listen(BulletinPublished::class, new BulletinHandler());
EventDispatcher::listen(BulletinArchived::class, new BulletinHandler());

$statutDe  = fn(int $id) => (string)$pdo->query("SELECT statut FROM bulletins_v2 WHERE id = $id")->fetchColumn();
$auditPour = fn(int $eleveId, string $action) => array_map(
    fn($j) => json_decode((string)$j, true) ?? [],
    $pdo->query("SELECT apres FROM audit_logs WHERE entite = 'bulletin' AND entite_id = $eleveId AND action = '$action' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN)
);

$pdo->beginTransaction();
try {
    TenantContext::set(1);
    $svc = BulletinTokenService::fromEnvironment();
    $fx  = new BulletinFixtures($pdo);
    $per = $fx->periode();
    if (!$per || (int)$pdo->query("SELECT COUNT(*) FROM bulletins_v2")->fetchColumn() === 0) {
        skip('tout', 'aucune période ou aucun bulletin modèle');
    } else {
        $nouveau = function (string $statut, int $version = 2, ?string $tokenForce = null) use ($fx, $per, $svc): array {
            $eid = $fx->eleve('SECRETNOM');
            return $fx->bulletin($eid, $per, $tokenForce ?? $svc->issue(1, $eid, $per), $statut, $version);
        };
        $voir = ['academique.bulletin.view', 'academique.bulletin.generate'];

        // ════════════════════════════════════════════════════════════════════
        section('1. Impression d\'un BROUILLON : filigrane, aucun QR, aucun jeton');
        seConnecter('admin', $voir);
        $b = $nouveau('brouillon');
        [$code, $html] = imprimer($b['eleve_id'], $per);
        check($code === 200 && str_contains($html, 'class="watermark"') && str_contains($html, 'BROUILLON'), 'brouillon : 200 avec le filigrane « BROUILLON »');
        check(!str_contains($html, 'class="qr-block"') && !str_contains($html, '<svg'), 'brouillon : AUCUN bloc QR, aucun SVG');
        check(!str_contains($html, $b['token']), 'brouillon : le jeton n\'apparaît nulle part dans le document');
        check(str_contains($html, 'non officiel'), 'brouillon : mention « non officiel » dans la barre d\'outils');
        $audit = $auditPour($b['eleve_id'], 'imprimer');
        check(count($audit) === 1 && $audit[0]['officiel'] === false && $audit[0]['statut'] === 'brouillon' && !str_contains(json_encode($audit[0]), $b['token']), 'audit « imprimer » : bulletin non officiel, jeton absent');

        // ════════════════════════════════════════════════════════════════════
        section('2. Impression d\'un bulletin PUBLIÉ / ARCHIVÉ à jeton valide : QR officiel');
        foreach (['publie' => 'publié', 'archive' => 'archivé'] as $statut => $label) {
            $o = $nouveau($statut);
            [$code, $html, ] = imprimer($o['eleve_id'], $per);
            $url = "https://ecole.example.org/v2/academique/bulletins/verify/{$o['token']}";
            check($code === 200 && !str_contains($html, 'class="watermark"') && str_contains($html, 'class="qr-block"'), "$label : pas de filigrane, bloc QR présent");
            check(str_contains($html, (new QrEncoder())->generateSvg($url)), "$label : le QR encode exactement l'URL absolue HTTPS + jeton de la colonne");
            check(!str_contains($html, (new QrEncoder())->generateSvg('/v2/academique/bulletins/verify/' . $o['token'])), "$label : le QR n'encode pas une URL relative");
            check(!str_contains($html, $o['token']), "$label : le jeton en clair n'est pas imprimé (il n'est que dans le QR)");
            $a = $auditPour($o['eleve_id'], 'imprimer');
            check(count($a) === 1 && $a[0]['officiel'] === true && $a[0]['statut'] === $statut && !str_contains(json_encode($a[0]), $o['token']), "$label : audit « imprimer » officiel, sans jeton");
        }
        $o = $nouveau('publie');
        $ctl = new ControleurTest();
        $row = $repo->findByEleveEtPeriode($o['eleve_id'], $per);
        check($ctl->urlQr($row) === "https://ecole.example.org/v2/academique/bulletins/verify/{$o['token']}", 'urlQrOfficiel() = base APP_URL + chemin + jeton de la colonne');

        // ════════════════════════════════════════════════════════════════════
        section('3. Bulletin publié mais NON vérifiable : impression refusée, jamais dégradée');
        $refus = function (string $label, array $b, callable $avant = null, callable $apres = null) use ($per): void {
            $avant && $avant();
            [$code, $html, $rendu] = imprimer($b['eleve_id'], $per);
            check($code === 503 && !str_contains($html, 'class="qr-block"') && !str_contains($html, 'Moyenne') && !str_contains($html, $b['token']), "$label : 503, aucun document ni QR produit");
            $apres && $apres();
        };
        $refus('publié avec jeton hérité (version 1)', $nouveau('publie', 1));
        $refus('publié, version 2 mais jeton ≠ HMAC du bulletin', $nouveau('publie', 2, bin2hex(random_bytes(32))));
        $secretOk = $_ENV['BULLETIN_TOKEN_SECRET'];
        $refus('publié, secret BULLETIN_TOKEN_SECRET absent', $nouveau('publie'), function () { unset($_ENV['BULLETIN_TOKEN_SECRET']); }, function () use ($secretOk) { $_ENV['BULLETIN_TOKEN_SECRET'] = $secretOk; });
        $refus('publié, APP_URL locale en production', $nouveau('publie'), function () { $_ENV['APP_URL'] = 'https://localhost/ecole_app'; }, function () { $_ENV['APP_URL'] = 'https://ecole.example.org'; });
        $refus('publié, APP_URL en HTTP en production', $nouveau('publie'), function () { $_ENV['APP_URL'] = 'http://ecole.example.org'; }, function () { $_ENV['APP_URL'] = 'https://ecole.example.org'; });
        $refus('publié, APP_URL relative', $nouveau('publie'), function () { $_ENV['APP_URL'] = '/ecole_app'; }, function () { $_ENV['APP_URL'] = 'https://ecole.example.org'; });
        // En développement, localhost est accepté (poste de travail local)
        $_ENV['APP_ENV'] = 'development'; $_ENV['APP_URL'] = 'http://localhost/ecole_app';
        $dev = $nouveau('publie');
        [$code, $html] = imprimer($dev['eleve_id'], $per);
        check($code === 200 && str_contains($html, (new QrEncoder())->generateSvg("http://localhost/ecole_app/v2/academique/bulletins/verify/{$dev['token']}")), 'développement : QR vers http://localhost accepté');
        $_ENV['APP_ENV'] = 'production'; $_ENV['APP_URL'] = 'https://ecole.example.org';

        // Un brouillon reste imprimable même quand tout le reste est mal configuré (il n'a pas de QR)
        unset($_ENV['BULLETIN_TOKEN_SECRET']); $_ENV['APP_URL'] = '/relative';
        $bd = $nouveau('brouillon', 1);
        [$code, $html] = imprimer($bd['eleve_id'], $per);
        check($code === 200 && str_contains($html, 'class="watermark"') && !str_contains($html, 'class="qr-block"'), 'brouillon (même à jeton hérité, sans secret, APP_URL invalide) : imprimable avec filigrane, sans QR');
        $_ENV['BULLETIN_TOKEN_SECRET'] = $secretOk; $_ENV['APP_URL'] = 'https://ecole.example.org';

        // ════════════════════════════════════════════════════════════════════
        section('4. Génération à la volée (aucune ligne encore enregistrée)');
        $cible = $pdo->query("SELECT e.id AS eid, p.id AS pid FROM eleves e CROSS JOIN periodes_scolaires p
                               WHERE e.classe_id IS NOT NULL AND e.nom NOT LIKE 'SECRETNOM%'
                                 AND NOT EXISTS (SELECT 1 FROM bulletins_v2 b WHERE b.eleve_id = e.id AND b.periode_id = p.id) LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$cible) {
            skip('génération à la volée', 'aucun couple (élève, période) sans bulletin');
        } else {
            [$eid, $pid] = [(int)$cible['eid'], (int)$cible['pid']];
            seConnecter('admin', ['academique.bulletin.view']);
            [$code] = imprimer($eid, $pid);
            check($code === 403 && $repo->findByEleveEtPeriode($eid, $pid) === null, 'sans droit de génération : 403, aucun bulletin créé');
            seConnecter('admin', $voir);
            unset($_ENV['BULLETIN_TOKEN_SECRET']);
            [$code, $html] = imprimer($eid, $pid);
            check($code === 503 && $repo->findByEleveEtPeriode($eid, $pid) === null && !str_contains($html, 'watermark'), 'secret absent : 503, aucun bulletin créé (échec explicite, pas de jeton par défaut)');
            $_ENV['BULLETIN_TOKEN_SECRET'] = $secretOk;
            [$code, $html] = imprimer($eid, $pid);
            $nv = $repo->findByEleveEtPeriode($eid, $pid);
            check($code === 200 && $nv !== null && $nv['statut'] === 'brouillon', 'avec secret : bulletin généré en brouillon');
            check($nv !== null && (int)$nv['token_version'] === 2 && $svc->matches($nv['verification_token'], (int)$nv['etablissement_id'], $eid, $pid), 'jeton généré = HMAC officiel (version 2) de ce bulletin');
            $json = json_decode((string)$nv['data_json'], true);
            check(($json['verification_token'] ?? null) === $nv['verification_token'] && ($json['qr_code_url'] ?? 'x') === '', 'colonne et JSON portent le même jeton ; aucune URL de QR figée dans le JSON');
            check(str_contains($html, 'class="watermark"') && !str_contains($html, 'class="qr-block"'), 'bulletin généré à la volée : filigrane, sans QR');
            $avantToken = $nv['verification_token'];
            seConnecter('admin', $voir);
            imprimer($eid, $pid);
            $re = $repo->findByEleveEtPeriode($eid, $pid);
            check($re['verification_token'] === $avantToken, 'réimpression : jeton inchangé');
        }

        // ════════════════════════════════════════════════════════════════════
        section('4b. Régénération d\'un bulletin existant : le jeton n\'est jamais changé silencieusement');
        // Lignes RÉELLES de la base (hérités, version 1) : la régénération se fait dans la transaction, annulée ensuite.
        $reels = $pdo->query("SELECT id, eleve_id, periode_id, verification_token AS tok, token_version FROM bulletins_v2 WHERE token_version = 1 ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        if (!$reels) {
            skip('régénération de lignes héritées', 'aucun bulletin hérité (version 1) en base');
        } else {
            $generateur = \App\Modules\Academique\Services\BulletinEngineFactory::make(1);
            $toutOk = true; $versionsOk = true; $jsonOk = true;
            foreach ($reels as $r) {
                $generateur->genererBulletin((int)$r['eleve_id'], (int)$r['periode_id'], 1);
                $apres = $pdo->query("SELECT verification_token AS tok, token_version, data_json FROM bulletins_v2 WHERE id = " . (int)$r['id'])->fetch(PDO::FETCH_ASSOC);
                if ($apres['tok'] !== $r['tok']) { $toutOk = false; }
                if ((int)$apres['token_version'] !== 1) { $versionsOk = false; }
                $j = json_decode($apres['data_json'], true);
                if (($j['verification_token'] ?? null) !== $apres['tok'] || ($j['qr_code_url'] ?? 'x') !== '') { $jsonOk = false; }
            }
            check($toutOk, count($reels) . ' bulletins hérités régénérés : jeton de la colonne INCHANGÉ');
            check($versionsOk, 'version du jeton inchangée (la rotation reste une opération distincte)');
            check($jsonOk, 'le JSON régénéré porte le jeton de la colonne (plus de divergence) et aucune URL de QR figée');
            $deux = $reels[0];
            $generateur->genererBulletin((int)$deux['eleve_id'], (int)$deux['periode_id'], 1);
            $generateur->genererBulletin((int)$deux['eleve_id'], (int)$deux['periode_id'], 1);
            check($pdo->query("SELECT verification_token FROM bulletins_v2 WHERE id = " . (int)$deux['id'])->fetchColumn() === $deux['tok'], 'régénérations répétées : toujours le même jeton');
        }
        // ════════════════════════════════════════════════════════════════════
        section('5. Bouton « Publier » : visible seulement si TOUT est réuni');
        $pub = ['academique.bulletin.view', 'academique.bulletin.publish'];
        $d  = $nouveau('brouillon');
        $boutonPresent = function (array $perms, bool $gate, array $b) use ($per): bool {
            seConnecter('admin', $perms);
            if ($gate) { $_ENV['BULLETIN_PUBLICATION_ENABLED'] = 'true'; } else { unset($_ENV['BULLETIN_PUBLICATION_ENABLED']); }
            [, $html] = imprimer($b['eleve_id'], $per);
            unset($_ENV['BULLETIN_PUBLICATION_ENABLED']);
            return str_contains($html, 'btn-publish') && str_contains($html, '/publier');
        };
        check($boutonPresent($pub, true, $d), 'interrupteur ouvert + droit + brouillon à jeton v2 : bouton présent');
        check(!$boutonPresent($pub, false, $d), 'interrupteur FERMÉ (défaut) : pas de bouton, même avec le droit');
        check(!$boutonPresent($voir, true, $d), 'interrupteur ouvert mais SANS droit de publication : pas de bouton');
        check(!$boutonPresent($pub, true, $nouveau('brouillon', 1)), 'brouillon à jeton hérité : pas de bouton (rotation requise)');
        check(!$boutonPresent($pub, true, $nouveau('publie')), 'bulletin déjà publié : pas de bouton');
        seConnecter('admin', $pub); $_ENV['BULLETIN_PUBLICATION_ENABLED'] = 'true';
        [, $htmlBtn] = imprimer($d['eleve_id'], $per); unset($_ENV['BULLETIN_PUBLICATION_ENABLED']);
        check(str_contains($htmlBtn, 'name="_csrf_token" value="jeton-csrf-test"') && str_contains($htmlBtn, 'method="post"'), 'le formulaire de publication est en POST avec le jeton CSRF de la session');

        // ════════════════════════════════════════════════════════════════════
        section('6. Publication : refus tant que les conditions ne sont pas toutes réunies');
        $p1 = $nouveau('brouillon');
        $tousRefuses = true;
        // interrupteur fermé (défaut) : refus même avec tous les droits
        seConnecter('admin', ['academique.bulletin.admin', 'academique.bulletin.publish', 'academique.bulletin.view']);
        $j = journalPendant(function () use ($p1, $per, &$r) { $r = agir('publier', $p1['eleve_id'], $per); });
        check($r['type'] === 'rendu' && $r['code'] === 403 && $statutDe($p1['id']) === 'brouillon', 'interrupteur fermé (défaut) : 403, bulletin inchangé, même avec .admin + .publish');
        check(str_contains($j, 'BULLETIN_PUBLICATION_REFUSED') && str_contains($j, 'interrupteur_ferme') && !str_contains($j, $p1['token']), 'refus journalisé (raison interrupteur_ferme), sans jeton');

        $_ENV['BULLETIN_PUBLICATION_ENABLED'] = 'true';
        // sans droit
        foreach (['eleve' => ['academique.bulletin.view'], 'parent' => ['academique.bulletin.view', 'notes.view_own'], 'enseignant' => ['academique.bulletin.view', 'academique.notes.manage'],
                  'secretaire' => ['bulletins.view', 'finance.paiements.view']] as $role => $perms) {
            seConnecter($role, $perms);
            $r = agir('publier', $p1['eleve_id'], $per);
            if (!($r['type'] === 'rendu' && $r['code'] === 403 && $statutDe($p1['id']) === 'brouillon')) { $tousRefuses = false; }
        }
        check($tousRefuses, 'interrupteur ouvert mais rôles sans droit de publication (élève, parent, enseignant, secrétaire) : 403, bulletin inchangé');
        // CSRF
        seConnecter('admin', $pub);
        foreach (['absent' => null, 'faux' => 'autre-jeton', 'vide' => ''] as $label => $csrf) {
            $r = agir('publier', $p1['eleve_id'], $per, $csrf);
            check($r['type'] === 'redirection' && $statutDe($p1['id']) === 'brouillon', "CSRF $label : requête rejetée, bulletin inchangé");
        }
        // bulletin inexistant
        $r = agir('publier', 999999999, $per);
        check($r['type'] === 'redirection' && Session::getFlash('error') !== null, 'bulletin inexistant : refus avec message');

        // ════════════════════════════════════════════════════════════════════
        section('7. Publication : conditions réunies, statut et jeton contrôlés');
        // jeton hérité
        $h = $nouveau('brouillon', 1);
        $r = agir('publier', $h['eleve_id'], $per);
        check($r['type'] === 'redirection' && $statutDe($h['id']) === 'brouillon' && str_contains((string)Session::getFlash('error'), 'rotation'), 'jeton hérité (v1) : publication refusée, message « rotation »');
        // jeton qui ne correspond pas
        $f = $nouveau('brouillon', 2, bin2hex(random_bytes(32)));
        $r = agir('publier', $f['eleve_id'], $per);
        check($statutDe($f['id']) === 'brouillon' && str_contains((string)Session::getFlash('error'), 'invalide'), 'version 2 mais jeton ≠ HMAC : publication refusée');
        // déjà publié / archivé
        foreach (['publie', 'archive'] as $st) {
            $dp = $nouveau($st);
            $r = agir('publier', $dp['eleve_id'], $per);
            check($statutDe($dp['id']) === $st && Session::getFlash('error') !== null, "bulletin déjà « $st » : publication refusée, statut inchangé");
        }
        // secret absent
        unset($_ENV['BULLETIN_TOKEN_SECRET']);
        $s = $nouveau('brouillon');
        $r = agir('publier', $s['eleve_id'], $per);
        check($statutDe($s['id']) === 'brouillon' && Session::getFlash('error') !== null, 'secret absent : publication refusée, bulletin inchangé');
        $_ENV['BULLETIN_TOKEN_SECRET'] = $secretOk;

        // succès
        $ok = $nouveau('brouillon');
        $j = journalPendant(function () use ($ok, $per, &$r) { $r = agir('publier', $ok['eleve_id'], $per); });
        $ligne = $pdo->query("SELECT statut, published_by, published_at, token_version, verification_token FROM bulletins_v2 WHERE id = {$ok['id']}")->fetch(PDO::FETCH_ASSOC);
        check($r['type'] === 'redirection' && str_contains((string)$r['url'], "/bulletins/{$ok['eleve_id']}/$per/imprimer"), 'succès : redirection vers l\'impression du bulletin');
        check($ligne['statut'] === 'publie' && (int)$ligne['published_by'] === 1 && $ligne['published_at'] !== null, 'statut « publie », publié par l\'utilisateur, date renseignée');
        check($ligne['verification_token'] === $ok['token'] && (int)$ligne['token_version'] === 2, 'le jeton n\'a pas changé à la publication');
        check(Session::getFlash('success') !== null, 'message de succès');
        $aud = $auditPour($ok['eleve_id'], 'publier');
        check(count($aud) === 1 && ($aud[0]['jeton_empreinte'] ?? null) === BulletinTokenService::fingerprint($ok['token']), 'audit « publier » : empreinte du jeton enregistrée (et non masquée)');
        check(!str_contains(json_encode($aud[0] ?? []), $ok['token']) && !str_contains($j, $ok['token']), 'audit et journal de sécurité : JETON COMPLET ABSENT');
        check(isset($aud[0]['periode_id']) && !str_contains(json_encode($aud[0] ?? []), 'SECRETNOM'), 'audit sans donnée personnelle de l\'élève');

        // le bulletin publié est maintenant vérifiable publiquement et imprimable comme officiel
        [$code, $html] = imprimer($ok['eleve_id'], $per);
        check($code === 200 && str_contains($html, 'class="qr-block"') && !str_contains($html, 'class="watermark"'), 'après publication : impression officielle (QR, sans filigrane)');

        // deuxième publication : refusée, pas de second audit
        $r = agir('publier', $ok['eleve_id'], $per);
        check($statutDe($ok['id']) === 'publie' && count($auditPour($ok['eleve_id'], 'publier')) === 1 && Session::getFlash('error') !== null,
              'deuxième publication : refusée, un seul événement d\'audit');
        // transition atomique
        $conc = $nouveau('brouillon');
        $now = date('Y-m-d H:i:s');
        check($repo->transitionStatut($conc['id'], 'brouillon', 'publie', 1, $now) === true && $repo->transitionStatut($conc['id'], 'brouillon', 'publie', 1, $now) === false,
              'transition atomique : une seule des deux publications simultanées est appliquée');

        // ════════════════════════════════════════════════════════════════════
        section('8. Archivage');
        seConnecter('admin', $pub);                                   // droit de PUBLIER uniquement
        $a1 = $nouveau('publie');
        $r = agir('archiver', $a1['eleve_id'], $per);
        check($r['code'] === 403 && $statutDe($a1['id']) === 'publie', 'le droit de publier ne suffit pas à archiver : 403, bulletin inchangé');
        seConnecter('admin', ['academique.bulletin.view', 'academique.bulletin.archive']);
        $a2 = $nouveau('brouillon');
        $r = agir('archiver', $a2['eleve_id'], $per);
        check($statutDe($a2['id']) === 'brouillon' && Session::getFlash('error') !== null, 'archiver un brouillon : refusé');
        $a3 = $nouveau('publie');
        $r = agir('archiver', $a3['eleve_id'], $per);
        $aA = $auditPour($a3['eleve_id'], 'archiver');
        check($r['type'] === 'redirection' && $statutDe($a3['id']) === 'archive', 'avec le droit d\'archiver : publié → archivé');
        check(count($aA) === 1 && ($aA[0]['jeton_empreinte'] ?? null) === BulletinTokenService::fingerprint($a3['token']) && !str_contains(json_encode($aA[0]), $a3['token']), 'audit « archiver » : empreinte seulement, jeton absent');
        $r = agir('archiver', $a3['eleve_id'], $per);
        check($statutDe($a3['id']) === 'archive' && count($auditPour($a3['eleve_id'], 'archiver')) === 1, 'archiver deux fois : refusé, un seul événement d\'audit');
        unset($_ENV['BULLETIN_PUBLICATION_ENABLED']);
        $a4 = $nouveau('publie');
        $r = agir('archiver', $a4['eleve_id'], $per);
        check($statutDe($a4['id']) === 'publie' && $r['code'] === 403, 'archivage avec interrupteur fermé : 403');
    }
} finally {
    $pdo->rollBack();
    TenantContext::clear();
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées"
   . ($skipped ? " ({$skipped} scénario(s) SKIP — non comptés)" : '') . "\033[0m\n";
exit($failed === 0 ? 0 : 1);
