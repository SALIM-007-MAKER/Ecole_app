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

// Charger .env s'il existe (installation classique) ; sinon on suppose que
// les variables sont déjà fournies par l'environnement réel (conteneur,
// plateforme PaaS) — même comportement que public/index.php (Phase 16+,
// portabilité conteneur). On n'échoue plus juste parce que le fichier est
// absent : la connexion PDO plus bas donnera une erreur claire si la config
// manque réellement.
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
}

// Cible alternative RÉSERVÉE aux tests : une copie jetable (ecole_app_copy_*), jamais une autre base.
$cibleCopie = getenv('MIGRATE_DB');
if ($cibleCopie !== false && $cibleCopie !== '') {
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $cibleCopie)) { fwrite(STDERR, "REFUS : MIGRATE_DB doit être une copie ecole_app_copy_*
"); exit(2); }
    $_ENV['DB_DATABASE'] = $cibleCopie;
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ─── Options CLI ────────────────────────────────────────────────────────────

$dryRun  = in_array('--dry-run', $argv ?? [], true);
$onlyId  = null;
$rollbackId = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $onlyId = substr($arg, 5);
    }
    if (str_starts_with($arg, '--rollback=')) {
        $rollbackId = substr($arg, 11);
    }
}
$confirm = in_array('--confirm', $argv ?? [], true);

// ─── Retour arrière d'UNE migration : php database/migrate.php --rollback=T062 --confirm ──────────────────
// Sans --confirm : simulation. Refuse si la migration n'est pas appliquée, est marquée non réversible ou n'a pas de 'rollback'.
// Toujours faire une sauvegarde vérifiée avant (php database/backup.php --verify-restore).
if ($rollbackId !== null) {
    foreach (glob(ROOT_PATH . '/database/migrations/*.php') as $f) {
        $m = require $f;
        if (!is_array($m) || ($m['id'] ?? null) !== $rollbackId) { continue; }
        $ok = $pdo->prepare("SELECT COUNT(*) FROM migrations_log WHERE migration = ? AND status = 'success'");
        $ok->execute([$rollbackId]);
        if (!(int)$ok->fetchColumn()) { fwrite(STDERR, "REFUS : {$rollbackId} n'est pas appliquée.\n"); exit(2); }
        if (empty($m['reversible']) || !isset($m['rollback'])) { fwrite(STDERR, "REFUS : {$rollbackId} n'est pas réversible (aucun 'rollback') — restaurer la sauvegarde.\n"); exit(2); }
        echo "Retour arrière de {$rollbackId} — " . ($m['name'] ?? '') . ($confirm ? '' : ' (simulation : ajoutez --confirm)') . "\n";
        if (!$confirm) { exit(0); }
        try {
            ($m['rollback'])($pdo);
            $pdo->prepare('DELETE FROM migrations_log WHERE migration = ?')->execute([$rollbackId]);
            echo "→ OK : {$rollbackId} retirée du journal.\n";
            exit(0);
        } catch (\Throwable $e) {
            fwrite(STDERR, "ÉCHEC du retour arrière : " . $e->getMessage() . "\n"); exit(1);
        }
    }
    fwrite(STDERR, "REFUS : migration {$rollbackId} introuvable.\n"); exit(2);
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

$sqlHorsRunner = glob($migDir . '*.sql') ?: [];
if ($sqlHorsRunner) {
    echo "\n[ATTENTION] " . count($sqlHorsRunner) . " fichier(s) .sql de database/migrations/ ne sont PAS gérés par ce runner (ni journal, ni retour arrière) ; certains contiennent DROP TABLE : ne jamais les rejouer à la main.\n";
}

echo "\n=== Résultat : {$successCount} appliqués, {$skipCount} ignorés, {$errorCount} erreurs ===\n\n";
exit($errorCount > 0 ? 1 : 0);
