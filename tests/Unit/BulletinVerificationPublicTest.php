<?php
/**
 * Tests de sécurité — page PUBLIQUE de vérification des bulletins (/v2/academique/bulletins/verify/{jeton})
 *
 * Règles :
 *   - seul un bulletin ÉMIS (publié/archivé) à jeton OFFICIEL (version 2, HMAC valide) est authentique ;
 *   - brouillon, jeton inconnu, tronqué, modifié, en majuscules, hérité (version 1), retiré, faible, non
 *     concordant : MÊME réponse 404, sans nom, moyenne, classe, période, statut ni établissement ;
 *   - la page authentique ne transmet AUCUNE donnée de bulletin à la vue (résultat d'authenticité uniquement) ;
 *   - 30 requêtes/minute/IP (429 au-delà), X-Forwarded-For ignoré sans proxy de confiance configuré ;
 *   - secret absent : 503 neutre, jamais d'authentification par défaut ;
 *   - journaux : ancien QR et incohérence signalés par empreinte, jamais le jeton complet ;
 *   - en-têtes : no-store, no-referrer, noindex.
 *
 * Partie A : contrôleur appelé en direct, bulletins de test dans une transaction annulée à la fin.
 * Partie B : requêtes HTTP réelles (visiteur non connecté, tests/Support/request_as.php) sur les bulletins réels.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinVerificationPublicTest.php
 */

declare(strict_types=1);

// Les en-têtes ne peuvent être lus que tant que rien n'a été envoyé : on met toute la sortie en mémoire tampon.
ob_start();
register_shutdown_function(static function () { while (ob_get_level() > 0) { ob_end_flush(); } });

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}
$SECRET = hash('sha256', 'verification-publique-test-secret');   // jetable, propre à ce test
$_ENV['BULLETIN_TOKEN_SECRET'] = $SECRET;
unset($_ENV['TRUSTED_PROXIES']);

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
use App\Modules\Academique\Services\BulletinTokenService;
use App\Shared\Http\PublicThrottle;
use Core\Database;
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

/** Contrôleur de test : capture la vue et les données (la vue déclare des fonctions globales), limiteur injecté. */
class ControleurVerificationTest extends BulletinController
{
    public array $rendu = [];
    public static ?PublicThrottle $limiteur = null;
    protected function render(string $view, array $data = [], string $layout = 'main'): void { $this->rendu = [$view, $data, $layout]; }
    protected function throttle(): PublicThrottle { return self::$limiteur ?? parent::throttle(); }
}

/** En ligne de commande headers_list() est vide : on lit les en-têtes via Xdebug quand il est disponible (sinon null). */
function entetesEnvoyes(): ?array
{
    return function_exists('xdebug_get_headers') ? xdebug_get_headers() : null;
}

/** @return array{0:int, 1:array, 2:?array} statut HTTP, rendu [vue, données, layout], en-têtes (null si illisibles) */
function verifier(string $token): array
{
    header_remove();
    http_response_code(200);
    $c = new ControleurVerificationTest();
    $c->verifier($token);
    return [http_response_code() ?: 200, $c->rendu, entetesEnvoyes()];
}
function empreinte(array $rendu): string { return md5(serialize($rendu)); }

/** Journal de sécurité : texte ajouté pendant l'exécution de $f. */
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
$_SERVER['REMOTE_ADDR'] = '203.0.113.200';

// ════════════════════════════════════════════════════════════════════════════
// PARTIE A — contrôleur, bulletins de test dans une transaction annulée
// ════════════════════════════════════════════════════════════════════════════
$dirLimiteur = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'verif_throttle_' . bin2hex(random_bytes(4));
$pdo->beginTransaction();
try {
    TenantContext::set(1);
    $svc = BulletinTokenService::fromEnvironment();
    $fx  = new BulletinFixtures($pdo);
    $per = $fx->periode();
    if (!$per || (int)$pdo->query("SELECT COUNT(*) FROM bulletins_v2")->fetchColumn() === 0) {
        skip('Partie A', 'aucune période ou aucun bulletin modèle');
    } else {
        $horloge = 1_800_000_000;
        ControleurVerificationTest::$limiteur = new PublicThrottle($dirLimiteur, 30, 60, function () use (&$horloge) { return $horloge; });

        $cas = function (string $statut, int $version, ?string $tokenForce = null) use ($fx, $per, $svc, $pdo): array {
            $eid = $fx->eleve('SECRETNOM');
            $tok = $tokenForce ?? $svc->issue(1, $eid, $per);
            return $fx->bulletin($eid, $per, $tok, $statut, $version) + ['nom' => 'SECRETNOM'];
        };

        $publie    = $cas('publie', 2);
        $archive   = $cas('archive', 2);
        $brouillon = $cas('brouillon', 2);
        $heritePublie = $cas('publie', 1);                                            // jeton HMAC mais version héritée
        $faussePublie = $cas('publie', 2, bin2hex(random_bytes(32)));                 // version 2 mais pas le HMAC du bulletin
        $eFaible = $fx->eleve('SECRETNOM');
        $faible = $fx->bulletin($eFaible, $per, hash('sha256', "bulletin:{$eFaible}:{$per}:ecole_app_v2"), 'publie', 1);   // ancien schéma, public

        section('A1. Bulletin émis, jeton officiel : authentique, résultat seul');
        foreach (['publié' => $publie, 'archivé' => $archive] as $label => $b) {
            [$code, $rendu] = verifier($b['token']);
            check($code === 200 && $rendu[1] === ['trouve' => true], "$label + jeton officiel : 200, la vue ne reçoit que « trouve = true »");
        }
        $donnees = serialize(verifier($publie['token'])[1]);
        check(!str_contains($donnees, 'SECRETNOM') && !str_contains($donnees, '12.34') && !str_contains($donnees, 'publie') && !str_contains($donnees, 'Test'),
              'aucune donnée de bulletin (nom, moyenne, statut, classe, période) transmise à la vue');

        section('A2. Tout le reste : MÊME 404');
        [$codeRef, $renduRef] = verifier(bin2hex(random_bytes(32)));
        check($codeRef === 404 && $renduRef[1] === ['trouve' => false], 'jeton inconnu : 404, vérification négative');
        $reference = empreinte($renduRef);
        $mauvais = [
            'brouillon avec son vrai jeton (HMAC valide)' => $brouillon['token'],
            'publié mais version héritée (v1)'            => $heritePublie['token'],
            'publié, v2, mais jeton ≠ HMAC du bulletin'   => $faussePublie['token'],
            'ancien jeton faible (littéral public)'       => $faible['token'],
            'dernier caractère modifié'                   => substr($publie['token'], 0, 63) . ($publie['token'][63] === 'a' ? 'b' : 'a'),
            'tronqué'                                     => substr($publie['token'], 0, 32),
            'préfixe seul'                                => substr($publie['token'], 0, 8),
            'majuscules'                                  => strtoupper($publie['token']),
            'espaces autour'                              => ' ' . $publie['token'] . ' ',
            'fin de ligne'                                => $publie['token'] . "\n",
            'vide'                                        => '',
            'injection SQL'                               => "' OR '1'='1",
            'traversée de chemin'                         => '../../etc/passwd%00',
        ];
        foreach ($mauvais as $label => $t) {
            [$c, $r] = verifier($t);
            check($c === 404 && empreinte($r) === $reference, "$label : 404, réponse identique au jeton inconnu");
        }
        check(!str_contains(serialize(verifier($brouillon['token'])[1]), 'SECRETNOM'), 'brouillon : aucune donnée de l\'élève dans la réponse');

        section('A3. Secret absent : 503 neutre, aucune authentification par défaut');
        unset($_ENV['BULLETIN_TOKEN_SECRET']);
        [$code, $rendu] = verifier($publie['token']);
        check($code === 503 && $rendu[1] === ['trouve' => false, 'indisponible' => true], 'jeton valide mais secret absent : 503 « indisponible », jamais « authentique »');
        [$code2, $rendu2] = verifier(bin2hex(random_bytes(32)));
        check($code2 === 404 && empreinte($rendu2) === $reference, 'jeton inconnu sans secret : toujours 404 (inchangé)');
        $_ENV['BULLETIN_TOKEN_SECRET'] = $SECRET;
        check(verifier($publie['token'])[0] === 200, 'secret rétabli : de nouveau 200');

        section('A4. Journaux : signalement par empreinte, jamais le jeton');
        $jMismatch = journalPendant(function () use ($faussePublie) { verifier($faussePublie['token']); });
        check(str_contains($jMismatch, 'BULLETIN_TOKEN_MISMATCH') && str_contains($jMismatch, 'fp=' . BulletinTokenService::fingerprint($faussePublie['token'])) && !str_contains($jMismatch, $faussePublie['token']),
              'jeton non concordant : événement BULLETIN_TOKEN_MISMATCH avec empreinte, sans jeton complet');
        $jLegacy = journalPendant(function () use ($heritePublie) { verifier($heritePublie['token']); });
        check(str_contains($jLegacy, 'BULLETIN_LEGACY_QR_SCANNED') && !str_contains($jLegacy, $heritePublie['token']), 'jeton hérité : événement BULLETIN_LEGACY_QR_SCANNED sans jeton');
        $ancien = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO bulletin_tokens_retired (token_sha256, bulletin_id, motif) VALUES (?, ?, 'test')")->execute([BulletinTokenService::retiredDigest($ancien), $publie['id']]);
        $retiredId = (int)$pdo->lastInsertId();
        $retire = null;
        $jRetire = journalPendant(function () use ($ancien, &$retire) { $retire = verifier($ancien); });
        check($retire[0] === 404 && empreinte($retire[1]) === $reference, 'ancien jeton RETIRÉ : refusé, réponse identique à un jeton inconnu');
        check(str_contains($jRetire, 'BULLETIN_LEGACY_QR_SCANNED') && str_contains($jRetire, "retired_id=$retiredId") && !str_contains($jRetire, $ancien),
              'ancien jeton retiré : usage journalisé (par identifiant), jeton absent du journal');
        $jInconnu = journalPendant(function () { verifier(bin2hex(random_bytes(32))); });
        check($jInconnu === '', 'jeton simplement inconnu : aucune alerte');

        section('A5. En-têtes de la page publique');
        $h = verifier($publie['token'])[2];
        if ($h === null) {
            skip('A5 en-têtes', 'Xdebug indisponible : en-têtes illisibles en ligne de commande');
        } else {
            $h = array_map('strtolower', $h);
            check(in_array('cache-control: no-store, max-age=0', $h, true), 'Cache-Control: no-store');
            check(in_array('referrer-policy: no-referrer', $h, true), 'Referrer-Policy: no-referrer (le jeton est dans l\'URL)');
            check(in_array('x-robots-tag: noindex, nofollow', $h, true), 'X-Robots-Tag: noindex');
            $h404 = array_map('strtolower', verifier(bin2hex(random_bytes(32)))[2]);
            check(in_array('cache-control: no-store, max-age=0', $h404, true) && in_array('referrer-policy: no-referrer', $h404, true), 'mêmes en-têtes sur la réponse 404');
        }

        section('A6. Limitation : 30 requêtes par minute et par IP');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        $rate = []; $retryAfter = null;
        for ($i = 1; $i <= 31; $i++) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.' . $i;            // en-tête forgé, différent à chaque requête
            [$c, $r, $hd] = verifier(bin2hex(random_bytes(32)));
            $rate[] = $c;
            if ($c === 429) { foreach ($hd ?? [] as $l) { if (stripos($l, 'retry-after:') === 0) { $retryAfter = trim(substr($l, 12)); } } $rendu429 = $r; }
        }
        check(count(array_filter($rate, fn($c) => $c === 404)) === 30 && $rate[30] === 429, '30 réponses normales puis 429 à la 31e');
        check($rendu429[1] === ['trouve' => false, 'limite' => true], 'réponse 429 neutre (aucune donnée)');
        if (!function_exists('xdebug_get_headers')) {
            skip('A6 Retry-After', 'Xdebug indisponible : en-têtes illisibles en ligne de commande');
        } else {
            check($retryAfter !== null && (int)$retryAfter >= 1 && (int)$retryAfter <= 60, 'en-tête Retry-After présent (' . ($retryAfter ?? 'absent') . ' s)');
        }
        check(verifier(bin2hex(random_bytes(32)))[0] === 429, 'X-Forwarded-For forgé et changeant : sans proxy de confiance, la limite s\'applique quand même');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.51'; unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        check(verifier(bin2hex(random_bytes(32)))[0] === 404, 'une autre adresse IP n\'est pas limitée');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.77';
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $r1 = []; for ($i = 0; $i < 30; $i++) { $r1[] = verifier(bin2hex(random_bytes(32)))[0]; }
        check(!in_array(429, $r1, true), 'proxy de confiance configuré : le client réel (X-Forwarded-For) a son propre compteur');
        check(verifier(bin2hex(random_bytes(32)))[0] === 429, 'proxy de confiance : le client réel est limité à son tour');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.78';
        check(verifier(bin2hex(random_bytes(32)))[0] === 404, 'proxy de confiance : un autre client réel n\'est pas limité');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.200';
        $horloge += 61;
        check(verifier(bin2hex(random_bytes(32)))[0] === 404, 'après la fenêtre d\'une minute : compteur remis à zéro');
    }
} finally {
    $pdo->rollBack();
    TenantContext::clear();
    ControleurVerificationTest::$limiteur = null;
    foreach (glob($dirLimiteur . '/*') ?: [] as $f) { @unlink($f); }
    @rmdir($dirLimiteur);
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

// ════════════════════════════════════════════════════════════════════════════
// PARTIE B — HTTP réel, visiteur non connecté, bulletins réels (lecture seule)
// ════════════════════════════════════════════════════════════════════════════
function http_guest(string $token): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 ' . escapeshellarg(ROOT_PATH . '/tests/Support/request_as.php')
         . ' guest 0 GET ' . escapeshellarg('/ecole_app/v2/academique/bulletins/verify/' . $token);
    foreach (explode("\n", (string)shell_exec($cmd)) as $l) {
        if (str_starts_with($l, 'RESULT:')) { return json_decode(substr($l, 7), true) ?: ['status' => 0]; }
    }
    return ['status' => 0];
}
$fichierLimite = ROOT_PATH . '/storage/cache/throttle/' . hash('sha256', 'bulletin-verify|127.0.0.1') . '.json';
@unlink($fichierLimite);

section('B. HTTP réel (visiteur non connecté) sur les bulletins réels');
$ref = http_guest(bin2hex(random_bytes(32)));
check($ref['status'] === 404, 'jeton aléatoire : 404');
if (($ref['headers'] ?? null) === null) {
    skip('B en-têtes', 'Xdebug indisponible : en-têtes illisibles en ligne de commande');
} else {
    $hdr = array_map('strtolower', $ref['headers']);
    check(in_array('cache-control: no-store, max-age=0', $hdr, true) && in_array('referrer-policy: no-referrer', $hdr, true) && in_array('x-robots-tag: noindex, nofollow', $hdr, true),
          'en-têtes no-store / no-referrer / noindex présents sur la réponse réelle');
}

$rows = $pdo->query("SELECT eleve_id, periode_id, verification_token, statut, token_version FROM bulletins_v2 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$brouillons = array_values(array_filter($rows, fn($r) => $r['statut'] === 'brouillon'));
$faibles = array_values(array_filter($brouillons, fn($r) => hash_equals(hash('sha256', "bulletin:{$r['eleve_id']}:{$r['periode_id']}:ecole_app_v2"), $r['verification_token'])));
$forts = array_values(array_filter($brouillons, fn($r) => !in_array($r, $faibles, true)));
if (!$brouillons) {
    skip('B brouillons réels', 'aucun brouillon en base');
} else {
    $echantillon = array_merge($faibles, array_slice($forts, 0, 3));
    $tousRefuses = true; $memeReponse = true;
    foreach ($echantillon as $r) {
        $res = http_guest($r['verification_token']);
        if ($res['status'] !== 404) { $tousRefuses = false; }
        if (($res['md5'] ?? null) !== ($ref['md5'] ?? 'x')) { $memeReponse = false; }
    }
    check($tousRefuses, 'brouillons réels (' . count($faibles) . ' à jeton faible + ' . min(3, count($forts)) . ' à jeton fort) avec leur VRAI jeton : tous en 404');
    check($memeReponse, 'brouillons réels : corps strictement identique à celui d\'un jeton inconnu (même empreinte)');
    $attaque = true;
    foreach ($rows as $r) {
        $calc = hash('sha256', "bulletin:{$r['eleve_id']}:{$r['periode_id']}:ecole_app_v2");
        if (http_guest($calc)['status'] === 200) { $attaque = false; break; }
    }
    check($attaque, 'attaque par jeton calculé avec l\'ancien littéral : aucun bulletin n\'est exposé');
}
$publies = array_values(array_filter($rows, fn($r) => $r['statut'] !== 'brouillon' && (int)$r['token_version'] === 2));
if ($publies) {
    putenv('BULLETIN_TOKEN_SECRET');   // le secret de production n'est connu que du serveur : ce test ne le possède pas
    skip('bulletin réel publié à jeton officiel', 'la vérification positive nécessite le secret du serveur — couverte en partie A');
} else {
    skip('bulletin réel publié à jeton officiel', 'aucun bulletin publié en base (cas couvert en partie A)');
}

@unlink($fichierLimite);
$codes = [];
for ($i = 1; $i <= 31; $i++) { $codes[] = http_guest(bin2hex(random_bytes(32)))['status']; }
check(count(array_filter($codes, fn($c) => $c === 404)) === 30 && $codes[30] === 429, 'HTTP réel : 30 réponses 404 puis 429 à la 31e requête depuis la même adresse');
@unlink($fichierLimite);   // ne laisse aucun compteur actif derrière le test

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées"
   . ($skipped ? " ({$skipped} scénario(s) SKIP — non comptés)" : '') . "\033[0m\n";
exit($failed === 0 ? 0 : 1);
