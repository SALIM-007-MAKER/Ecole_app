<?php

declare(strict_types=1);

// ─── Constantes globales ─────────────────────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// ─── Chargement des variables d'environnement ────────────────────────────────
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

// ─── Autoloader PSR-4 maison ─────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    // Mapper les namespaces vers les répertoires
    $namespaces = [
        'Core\\'              => ROOT_PATH . '/core/',
        'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
        'App\\Models\\'      => ROOT_PATH . '/app/Models/',
        'App\\Middleware\\'  => ROOT_PATH . '/app/Middleware/',
        'App\\Services\\'   => ROOT_PATH . '/app/Services/',
        'App\\Events\\'     => ROOT_PATH . '/app/Events/',
        'App\\Listeners\\'  => ROOT_PATH . '/app/Listeners/',
        'App\\Modules\\'    => ROOT_PATH . '/app/Modules/',
        'App\\Jobs\\'       => ROOT_PATH . '/app/Jobs/',
        'App\\Shared\\'     => ROOT_PATH . '/app/Shared/',
    ];

    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// ─── Lancer l'application ────────────────────────────────────────────────────
use Core\Application;

Application::getInstance()->run();
