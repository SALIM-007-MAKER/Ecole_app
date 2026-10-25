<?php
/**
 * Banc d'essai HTTP en ligne de commande : exécute UNE requête de l'application complète
 * (routeur, middlewares, contrôleur, policies) avec une session authentifiée simulée.
 *
 *   php tests/Support/request_as.php <rôle> <userId> <GET|POST> "<uri avec query>" ["<json post>"]
 *
 * Les permissions de la session sont celles que l'application chargerait réellement à la
 * connexion (UserModel::getPermissions), pas une liste écrite à la main. Sortie : une ligne
 * « RESULT:{json} » (status, taille, extrait de texte). Aucune écriture n'est faite par le
 * banc d'essai lui-même ; une requête POST est envoyée avec un jeton CSRF valide, de sorte
 * que le refus éventuel vienne bien de l'autorisation et non du CSRF.
 */

[$_, $role, $uid, $method, $uri, $postJson] = $argv + [null, 'parent', 0, 'GET', '/ecole_app/login', '{}'];

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
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

parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
$_POST = $method === 'POST' ? (json_decode($postJson, true) ?: []) : [];
if ($method === 'POST') { $_POST['_csrf_token'] = 'test-csrf-token'; }
$_REQUEST = array_merge($_GET, $_POST);
$_SERVER['REQUEST_METHOD']  = $method;
$_SERVER['REQUEST_URI']     = $uri;
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'rbac-test-cli';
$_SERVER['SCRIPT_NAME']     = '/ecole_app/public/index.php';

$pdo = new PDO(
    'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'ecole_app') . ';charset=utf8mb4',
    $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '',
    [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$u = null; $etab = 0; $perms = [];
if ($role !== 'guest') {   // « guest » = visiteur non connecté (pages publiques)
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$uid]);
    $u = $stmt->fetch();
    if (!$u) { echo 'RESULT:' . json_encode(['error' => 'utilisateur introuvable']) . "\n"; exit(2); }
    $etab = (int)$u['etablissement_id'];
    $perms = (new \App\Models\UserModel())->getPermissions($role, (int)$uid, $etab);
}

// Toutes les écritures de la requête simulée (audit des impressions, journaux en base…) sont ANNULÉES à la fin :
// un test HTTP ne laisse aucune trace dans la base. Désactivable avec REQUEST_AS_COMMIT=1 (jamais utilisé par les tests).
$transactionTest = null;
if (getenv('REQUEST_AS_COMMIT') !== '1') {
    $transactionTest = \Core\Database::getInstance()->getConnection();
    $transactionTest->beginTransaction();
}

ob_start();
register_shutdown_function(function () use ($role, $uri, $method, &$transactionTest) {
    if ($transactionTest !== null && $transactionTest->inTransaction()) {
        $transactionTest->rollBack();
    }
    $body = ob_get_clean();
    $code = http_response_code() ?: 200;
    $txt  = trim(preg_replace('/\s+/', ' ', strip_tags((string)$body)));
    echo 'RESULT:' . json_encode([
        'role' => $role, 'method' => $method, 'uri' => $uri, 'status' => $code, 'bytes' => strlen((string)$body),
        'forbidden' => $code === 403 || str_contains($txt, 'Permission insuffisante'),
        'md5' => md5((string)$body),
        'headers' => function_exists('xdebug_get_headers') ? xdebug_get_headers() : null,   // headers_list() est vide en ligne de commande
        'text' => mb_substr($txt, 0, 120),
    ], JSON_UNESCAPED_UNICODE) . "\n";
});

\Core\Session::start();
$_SESSION['_csrf_token'] = 'test-csrf-token';
if ($u !== null) {
    \Core\Session::setUser([
        'id' => (int)$u['id'], 'nom' => $u['nom'], 'prenom' => $u['prenom'] ?? '', 'email' => $u['email'],
        'role' => $role, 'photo' => null, 'permissions' => $perms, 'etablissement_id' => $etab,
        'available_etablissements' => [],
    ]);
}
\Core\Application::getInstance()->run();
