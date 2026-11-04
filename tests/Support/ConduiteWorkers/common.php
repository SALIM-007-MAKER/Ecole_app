<?php
// Processus de travail des tests de concurrence de la Conduite — se connectent UNIQUEMENT à une copie jetable
// (ecole_app_copy_*), jamais à la base réelle. Chargé par holder.php, worker.php, publisher.php et writer_deadlock.php.

define('ROOT_PATH', dirname(__DIR__, 3));
define('BASE_URL', '/ecole_app');

function copie_pdo(string $db, int $lockTimeout = 5): PDO
{
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $db)) {
        fwrite(STDERR, "REFUS: base cible hors copie ($db)\n");
        exit(9);
    }
    $env = [];
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_PORT'] ?? '3306', $db),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . $lockTimeout);
    return $pdo;
}

function copie_errno(Throwable $e): int
{
    return $e instanceof PDOException && isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
}

/** Charge l'application pour un processus qui exécute le VRAI code (publisher.php) : DB_DATABASE vise la copie. */
function charger_application(string $db): void
{
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    $_ENV['DB_DATABASE'] = $db;
    $_ENV['BULLETIN_TOKEN_SECRET'] = hash('sha256', 'conduite-lot2-test-secret');
    $_ENV['APP_ENV'] = 'local';
    $_SESSION = [];
    spl_autoload_register(function (string $c): void {
        foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $pre => $dir) {
            if (str_starts_with($c, $pre)) {
                $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($pre))) . '.php';
                if (is_file($f)) { require_once $f; return; }
            }
        }
    });
}
