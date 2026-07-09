<?php

/**
 * EduNova — Queue Worker (invocation manuelle/planifiée)
 *
 * Usage : C:/wamp64/bin/php/php8.2.29/php.exe database/queue-worker.php [--limit=50]
 *
 * IMPORTANT (Phase 14.9) : ce script traite un lot de tâches PUIS se
 * termine — ce n'est PAS un démon. Cet environnement (Windows/WAMP local)
 * n'a pas de Supervisord/systemd pour maintenir un worker persistant ; en
 * production, ce même script serait invoqué en boucle par un vrai process
 * manager (Supervisord côté Linux) — voir
 * MULTI_TENANT_CACHE_QUEUE_IMPLEMENTATION_REPORT.md §"Hors périmètre". En
 * l'état, il peut être appelé manuellement ou via une tâche planifiée
 * Windows (schtasks) pour un traitement quasi-asynchrone.
 */

declare(strict_types=1);
set_time_limit(120);

define('ROOT_PATH', dirname(__DIR__));

$envFile = ROOT_PATH . '/.env';
if (!file_exists($envFile)) {
    die("ERREUR : .env introuvable dans " . ROOT_PATH . "\n");
}
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

// Aligne le fuseau horaire sur celui de l'application — voir database/migrate.php
// pour le contexte (trouvé Phase 15.1 / RC1).
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'         => ROOT_PATH . '/core/',
        'App\\Jobs\\'    => ROOT_PATH . '/app/Jobs/',
        'App\\Services\\' => ROOT_PATH . '/app/Services/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use Core\Queue\QueueWorker;

$limit = 50;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
}

echo "=== EduNova — Queue Worker (lot de " . $limit . " tâches max) ===\n\n";

$worker = QueueWorker::make();
$processed = $worker->processBatch($limit);

echo "Tâches traitées : {$processed}\n";
echo $processed === 0 ? "(file vide — rien à faire)\n" : "OK\n";
