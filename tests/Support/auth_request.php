<?php
/**
 * Banc d'essai HTTP à SESSION PERSISTANTE (connexion commune, sélecteur d'établissement, rafraîchissement des droits).
 *
 *   php tests/Support/auth_request.php <fichier-session.json> <GET|POST> "<uri>" ["<json post>"]
 *
 * Contrairement à request_as.php (session simulée à partir d'un utilisateur existant), AUCUNE session n'est préparée : l'application
 * complète (routeur, garde de session, AuthController…) est exécutée et la session est relue puis réécrite dans le fichier indiqué, ce qui
 * permet d'enchaîner plusieurs requêtes (connexion → choix d'établissement → page protégée) dans des processus séparés.
 *
 * Réservé aux tests : REQUEST_AS_DB doit désigner une COPIE jetable (ecole_app_copy_*), jamais la base vivante ; les écritures sont conservées
 * dans la copie. Variables : AUTH_THROTTLE_DIR (limitation de débit isolée), AUTH_REQ_IP (adresse client simulée).
 * Sortie : une ligne « RESULT:{json} » (status, en-têtes, flash, session résumée).
 */

[$_, $fichierSession, $method, $uri, $postJson] = $argv + [null, null, 'GET', '/ecole_app/login', '{}'];

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$cibleCopie = getenv('REQUEST_AS_DB');
if ($cibleCopie === false || !preg_match('/^ecole_app_copy_[a-f0-9]+$/', $cibleCopie)) {
    fwrite(STDERR, "REFUS : REQUEST_AS_DB doit désigner une copie jetable ecole_app_copy_*\n");
    exit(9);
}
$_ENV['DB_DATABASE'] = $cibleCopie;

// AUCUN e-mail réel ne peut partir d'un test : la configuration SMTP du fichier .env (Gmail…) est remplacée par un serveur local. Sans
// AUTH_REQ_MAIL_PORT (port d'un faux serveur SMTP, tests/Support/fake_smtp_server.php), le port 9 est fermé : l'envoi échoue aussitôt.
foreach (['MAIL_DRIVER' => 'smtp', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => (string)(getenv('AUTH_REQ_MAIL_PORT') ?: '9'), 'MAIL_ENCRYPTION' => 'none',
    'MAIL_USERNAME' => 'u', 'MAIL_PASSWORD' => 'p', 'MAIL_FROM_EMAIL' => 'noreply@test.local', 'MAIL_FROM_NAME' => 'EduNova Test', 'MAIL_REPLY_TO' => ''] as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
}

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

// Dossier du fichier de secours du journal global (isolé par les tests : une panne d'audit ne doit pas écrire dans storage/logs).
if (($dossierSecours = getenv('AUTH_REQ_AUDIT_DIR')) !== false && $dossierSecours !== '') {
    \Core\Platform\PlatformAuditService::definirDossierSecours($dossierSecours);
}

parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
$_POST = $method === 'POST' ? (json_decode((string)$postJson, true) ?: []) : [];
if ($method === 'POST' && getenv('REQUEST_AS_NO_CSRF') !== '1') { $_POST['_csrf_token'] = 'test-csrf-token'; }
$_REQUEST = array_merge($_GET, $_POST);
$_SERVER['REQUEST_METHOD']  = $method;
$_SERVER['REQUEST_URI']     = $uri;
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REMOTE_ADDR']     = getenv('AUTH_REQ_IP') ?: '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'auth-test-cli';
$_SERVER['SCRIPT_NAME']     = '/ecole_app/public/index.php';

ob_start();
register_shutdown_function(function () use ($fichierSession, $uri, $method): void {
    $body = ob_get_clean();
    $code = http_response_code() ?: 200;
    $session = $_SESSION ?? [];
    @file_put_contents($fichierSession, json_encode($session, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    // Le stockage de session PHP n'est qu'un relais : il est supprimé (le fichier JSON fait foi).
    $sid = 'authtest' . md5((string)$fichierSession);
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $chemin = (session_save_path() !== '' ? session_save_path() : sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'sess_' . $sid;
    @unlink($chemin);
    $user = $session['_auth_user'] ?? null;
    $location = null;
    foreach ((function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list()) ?: [] as $h) {
        if (stripos($h, 'Location:') === 0) { $location = trim(substr($h, 9)); }
    }
    echo 'RESULT:' . json_encode([
        'method' => $method, 'uri' => $uri, 'status' => $code, 'location' => $location,
        'flash' => $session['_flash'] ?? [],
        'connecte' => is_array($user),
        'user' => is_array($user) ? ['id' => $user['id'] ?? null, 'role' => $user['role'] ?? null, 'etablissement_id' => $user['etablissement_id'] ?? null,
            'permissions' => $user['permissions'] ?? [], 'nom' => $user['nom'] ?? null, 'email' => $user['email'] ?? null,
            'available' => array_map(static fn($e) => (int)($e['id'] ?? 0), $user['available_etablissements'] ?? [])] : null,
        'cles_session' => array_keys($session),
        'attente_choix' => isset($session['_pending_auth_user_id']),
        'bytes' => strlen((string)$body),
        'html' => (string)$body,
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
});

// La session persistée est placée dans le stockage de session PHP AVANT Session::start() : l'application la relit comme une vraie session
// (y compris l'expiration par inactivité, évaluée à partir de _last_activity). L'identifiant est dérivé du fichier : stable d'une requête à l'autre.
$sid = 'authtest' . md5((string)$fichierSession);
$configApp = require ROOT_PATH . '/config/app.php';
session_name($configApp['session']['name']);
session_id($sid);
session_start();
$precedente = is_file($fichierSession) ? json_decode((string)file_get_contents($fichierSession), true) : null;
$_SESSION = is_array($precedente) ? $precedente : [];
session_write_close();
\Core\Session::start();
$_SESSION['_csrf_token'] = 'test-csrf-token';
\Core\Application::getInstance()->run();
