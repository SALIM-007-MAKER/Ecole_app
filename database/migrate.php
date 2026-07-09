<?php

/**
 * SCOLARIS V2 — Migration Runner
 *
 * Usage : C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php [--dry-run] [--id=M001]
 *
 * Options :
 *   --dry-run   Affiche ce qui serait exécuté sans l'appliquer
 *   --id=XXXX   Exécute uniquement la migration avec cet identifiant
 *   --rollback  (non implémenté — voir notes ci-dessous)
 *
 * Chaque fichier dans database/migrations/ retourne un tableau :
 *   [
 *     'id'          => 'S001',               // identifiant unique
 *     'name'        => 'audit_logs table',    // description
 *     'reversible'  => true|false,
 *     'run'         => function(PDO $pdo): void { ... },
 *     'rollback'    => function(PDO $pdo): void { ... },  // optionnel
 *   ]
 */

declare(strict_types=1);
set_time_limit(120);

// ─── Bootstrap minimal ───────────────────────────────────────────────────────

define('ROOT_PATH', dirname(__DIR__));

$envFile = ROOT_PATH . '/.env';
if (!file_exists($envFile)) {
    die("ERREUR : .env introuvable dans " . ROOT_PATH . "\n");
}

// Charger .env
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

// Aligne le fuseau horaire sur celui de l'application (Core\Application::run()
// l'applique pour les requêtes HTTP réelles ; ce script CLI tourne hors de ce
// bootstrap et utilisait jusqu'ici le défaut PHP nu — trouvé Phase 15.1 (RC1).
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// ─── Connexion PDO ───────────────────────────────────────────────────────────

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST']     ?? 'localhost',
    $_ENV['DB_PORT']     ?? '3306',
    $_ENV['DB_DATABASE'] ?? 'ecole_app'
);

try {
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\PDOException $e) {
    die("ERREUR connexion DB : " . $e->getMessage() . "\n");
}

// ─── Table de suivi des migrations ──────────────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `migrations_log` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration`  VARCHAR(50)  NOT NULL UNIQUE,
        `name`       VARCHAR(255) NOT NULL DEFAULT '',
        `applied_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `duration_ms` INT UNSIGNED NULL,
        `status`     ENUM('success','failed') NOT NULL DEFAULT 'success',
        `error`      TEXT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ─── Options CLI ────────────────────────────────────────────────────────────

$dryRun  = in_array('--dry-run', $argv ?? [], true);
$onlyId  = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $onlyId = substr($arg, 5);
    }
}

// ─── Charger et trier les migrations ────────────────────────────────────────

$migDir = ROOT_PATH . '/database/migrations/';
$files  = glob($migDir . '*.php');
sort($files);

if (empty($files)) {
    echo "Aucun fichier de migration trouvé dans {$migDir}\n";
    exit(0);
}

// ─── Récupérer les migrations déjà appliquées ────────────────────────────────

$applied = $pdo->query("SELECT migration FROM migrations_log WHERE status = 'success'")
               ->fetchAll(PDO::FETCH_COLUMN);

// ─── Exécuter les migrations ────────────────────────────────────────────────

$successCount = 0;
$skipCount    = 0;
$errorCount   = 0;

echo "\n=== SCOLARIS V2 — Migration Runner ===\n\n";

foreach ($files as $file) {
    $migration = require $file;

    if (!is_array($migration) || !isset($migration['id'], $migration['run'])) {
        echo "  [SKIP] {$file} — format invalide (doit retourner ['id', 'run', ...])\n";
        continue;
    }

    $id   = $migration['id'];
    $name = $migration['name'] ?? $id;

    // Filtre --id
    if ($onlyId !== null && $onlyId !== $id) {
        continue;
    }

    // Déjà appliqué ?
    if (in_array($id, $applied, true)) {
        echo "  [DÉJÀ APPLIQUÉ] {$id} — {$name}\n";
        $skipCount++;
        continue;
    }

    echo "  [APPLIQUER] {$id} — {$name}";

    if ($dryRun) {
        echo " (--dry-run)\n";
        continue;
    }

    $start = microtime(true);

    try {
        ($migration['run'])($pdo);
        $duration = (int)((microtime(true) - $start) * 1000);

        $stmt = $pdo->prepare(
            "INSERT INTO migrations_log (migration, name, duration_ms, status)
             VALUES (?, ?, ?, 'success')
             ON DUPLICATE KEY UPDATE applied_at = NOW(), status = 'success', error = NULL, duration_ms = VALUES(duration_ms)"
        );
        $stmt->execute([$id, $name, $duration]);

        echo " → OK ({$duration}ms)\n";
        $successCount++;

    } catch (\Throwable $e) {
        $duration = (int)((microtime(true) - $start) * 1000);
        $errMsg   = $e->getMessage();

        $stmt = $pdo->prepare(
            "INSERT INTO migrations_log (migration, name, duration_ms, status, error)
             VALUES (?, ?, ?, 'failed', ?)
             ON DUPLICATE KEY UPDATE applied_at = NOW(), status = 'failed', error = VALUES(error), duration_ms = VALUES(duration_ms)"
        );
        $stmt->execute([$id, $name, $duration, $errMsg]);

        echo " → ÉCHEC : {$errMsg}\n";
        $errorCount++;
    }
}

echo "\n=== Résultat : {$successCount} appliqués, {$skipCount} ignorés, {$errorCount} erreurs ===\n\n";
exit($errorCount > 0 ? 1 : 0);
