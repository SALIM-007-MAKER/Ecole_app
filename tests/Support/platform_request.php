<?php
/**
 * Banc d'essai HTTP du portail PLATEFORME : exécute UNE requête de l'application complète (routeur, contrôleur, vues) avec, au choix,
 * un visiteur anonyme ou une session d'opérateur simulée. Pendant de request_as.php (qui simule un utilisateur d'établissement).
 *
 *   php tests/Support/platform_request.php <guest|{"id":1,"email":"a@b.c","nom":"N","niveau":"admin","must_change":false}> <GET|POST> "<uri>" ["<json post>"]
 *
 * Exige REQUEST_AS_DB=ecole_app_copy_* (copie jetable) : jamais la base vivante. Les écritures sont conservées (la copie est jetable) pour que
 * le test puisse relire le journal d'audit. Variables facultatives : PLATFORM_REQ_IP (adresse source), REQUEST_AS_NO_CSRF=1, REQUEST_AS_BODY_OUT=<fichier>,
 * PLATFORM_REQ_AUDIT_DIR (dossier du fichier de secours), PLATFORM_REQ_BREAK_AUDIT=1 (simule une panne d'écriture du journal d'audit).
 * Sortie : une ligne « RESULT:{json} » (status, location, octets, session opérateur après la requête).
 */

[$_, $operatorArg, $method, $uri, $postJson] = $argv + [null, 'guest', 'GET', '/ecole_app/platform/login', '{}'];

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);

$cible = getenv('REQUEST_AS_DB');
if ($cible === false || !preg_match('/^ecole_app_copy_[a-f0-9]+$/', $cible)) { fwrite(STDERR, "REFUS : REQUEST_AS_DB doit être une copie ecole_app_copy_*\n"); exit(9); }

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$_ENV['DB_DATABASE'] = $cible;

spl_autoload_register(function (string $c): void {
    $map = [
        'Core\\' => '/core/', 'App\\Controllers\\' => '/app/Controllers/', 'App\\Models\\' => '/app/Models/',
        'App\\Middleware\\' => '/app/Middleware/', 'App\\Services\\' => '/app/Services/', 'App\\Events\\' => '/app/Events/',
        'App\\Listeners\\' => '/app/Listeners/', 'App\\Modules\\' => '/app/Modules/', 'App\\Jobs\\' => '/app/Jobs/',
        'App\\Shared\\' => '/app/Shared/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});

if (($dossier = getenv('PLATFORM_REQ_AUDIT_DIR')) !== false && $dossier !== '') {
    \Core\Platform\PlatformAuditService::definirDossierSecours($dossier);
}
// Capteur d'e-mails : les alertes de sécurité sont écrites (une ligne JSON chacune) dans ce fichier au lieu d'être envoyées.
if (($sink = getenv('PLATFORM_REQ_MAIL_SINK')) !== false && $sink !== '') {
    \Core\Platform\PlatformSecurityNotifier::definirEnvoyeur(static function (string $to, string $objet, string $html) use ($sink): bool {
        return file_put_contents($sink, json_encode(['to' => $to, 'objet' => $objet, 'html' => $html], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND) !== false;
    });
}
if (getenv('PLATFORM_REQ_BREAK_AUDIT') === '1') {
    // Panne simulée : la table du journal devient inutilisable pour cette requête (l'opération métier doit quand même aboutir).
    \Core\Database::getInstance()->getConnection()->exec('RENAME TABLE platform_audit_logs TO platform_audit_logs_hors_service');
    register_shutdown_function(static function (): void {
        try { \Core\Database::getInstance()->getConnection()->exec('RENAME TABLE platform_audit_logs_hors_service TO platform_audit_logs'); } catch (\Throwable) {}
    });
}

parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
$_POST = $method === 'POST' ? (json_decode($postJson, true) ?: []) : [];
if ($method === 'POST' && getenv('REQUEST_AS_NO_CSRF') !== '1') { $_POST['_csrf_token'] = 'test-csrf-token'; }
$_REQUEST = array_merge($_GET, $_POST);
$_SERVER['REQUEST_METHOD']  = $method;
$_SERVER['REQUEST_URI']     = $uri;
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REMOTE_ADDR']     = getenv('PLATFORM_REQ_IP') ?: '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'platform-test-cli';
$_SERVER['SCRIPT_NAME']     = '/ecole_app/public/index.php';

ob_start();
register_shutdown_function(function () use ($uri, $method) {
    $body = ob_get_clean();
    if (($sortie = getenv('REQUEST_AS_BODY_OUT')) !== false && $sortie !== '') { file_put_contents($sortie, (string)$body); }
    $location = null;
    foreach ((function_exists('xdebug_get_headers') ? xdebug_get_headers() : []) as $h) {
        if (stripos($h, 'Location:') === 0) { $location = trim(substr($h, 9)); }
    }
    $op = $_SESSION['_platform_operator'] ?? null;
    echo 'RESULT:' . json_encode([
        'method' => $method, 'uri' => $uri, 'status' => http_response_code() ?: 200, 'location' => $location, 'bytes' => strlen((string)$body),
        'operator' => is_array($op) ? ['id' => $op['id'] ?? null, 'niveau' => $op['niveau'] ?? null, 'must_change' => !empty($op['must_change'])] : null,
        'flash' => $_SESSION['_flash'] ?? [], 'mfa_pending' => isset($_SESSION['_platform_mfa_pending']),
        'mfa_method' => is_array($op) ? ($op['mfa'] ?? null) : null, 'must_2fa' => is_array($op) && !empty($op['must_2fa']),
        'text' => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)$body))), 0, 60000),
    ], JSON_UNESCAPED_UNICODE) . "\n";
});

\Core\Session::start();
$_SESSION['_csrf_token'] = 'test-csrf-token';
// Session préremplie (tests de parcours en plusieurs requêtes, ex. second facteur en attente) : PLATFORM_REQ_SESSION = JSON clé => valeur.
if (($graine = getenv('PLATFORM_REQ_SESSION')) !== false && $graine !== '') { $_SESSION = array_merge($_SESSION, json_decode($graine, true) ?: []); }
if ($operatorArg !== 'guest') {
    $op = json_decode($operatorArg, true);
    if (!is_array($op) || !isset($op['id'], $op['niveau'])) { fwrite(STDERR, "REFUS : opérateur JSON invalide\n"); exit(2); }
    \Core\Platform\PlatformAuth::login($op + ['email' => 'test@exemple.test', 'nom' => 'Test']);
}
\Core\Application::getInstance()->run();
