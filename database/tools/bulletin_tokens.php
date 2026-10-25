<?php

/**
 * Outil manuel de rotation des jetons de bulletins (voir BulletinTokenRotator et database/manual/T047_*).
 *
 *   --check                      lecture seule : état des jetons (comptages)
 *   --simulate                   lecture seule : plan de rotation + somme de contrôle (aucun jeton affiché)
 *   --apply --confirm=<somme> --authorized-by="<nom>" [--allow-production] [--allow-published]
 *   --restore=<fichier> --authorized-by="<nom>" [--force-published]
 *   --db=<nom>                   base cible (par défaut : DB_DATABASE de .env)
 *
 * Sans option d'écriture, l'outil ne modifie RIEN. Il ne lit le secret que par BulletinTokenService.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/app/Modules/Academique/Services/BulletinTokenException.php';
require_once ROOT_PATH . '/app/Modules/Academique/Services/BulletinTokenService.php';
require_once ROOT_PATH . '/app/Modules/Academique/Services/BulletinTokenRotator.php';

use App\Modules\Academique\Services\BulletinTokenException;
use App\Modules\Academique\Services\BulletinTokenRotator;
use App\Modules\Academique\Services\BulletinTokenService;

$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
foreach ($env as $k => $v) { if (getenv($k) === false) { $_ENV[$k] = $v; } }

$opts = getopt('', ['check', 'simulate', 'apply', 'confirm:', 'authorized-by:', 'allow-production', 'allow-published', 'restore:', 'force-published', 'db:', 'help']);
$modes = array_filter([isset($opts['check']), isset($opts['simulate']), isset($opts['apply']), isset($opts['restore'])]);
if (isset($opts['help']) || count($modes) !== 1) {
    fwrite(STDOUT, "Usage : --check | --simulate | --apply --confirm=<somme> --authorized-by=<nom> | --restore=<fichier> --authorized-by=<nom>  [--db=<base>]\n");
    exit(count($modes) === 1 || isset($opts['help']) ? 0 : 2);
}

$get = static fn(string $k, string $d = '') => $_ENV[$k] ?? (getenv($k) !== false ? (string)getenv($k) : $d);
$dbName = (string)($opts['db'] ?? $get('DB_DATABASE', 'ecole_app'));
try {
    $pdo = new PDO('mysql:host=' . $get('DB_HOST', 'localhost') . ';dbname=' . $dbName . ';charset=utf8mb4',
        $get('DB_USERNAME', 'root'), $get('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Throwable) {
    fwrite(STDERR, "Connexion à la base impossible.\n"); exit(2);
}

try { $tokens = BulletinTokenService::fromEnvironment(); }
catch (BulletinTokenException $e) { $tokens = null; $problemeSecret = $e->getMessage(); }

$rot = new BulletinTokenRotator($pdo, $tokens, ROOT_PATH . '/database/backups', $dbName, strtolower($get('APP_ENV', 'development')));
$out = static fn(array $d) => fwrite(STDOUT, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

try {
    if (isset($opts['check'])) { $out($rot->check()); }
    elseif (isset($opts['simulate'])) {
        if ($tokens === null) { throw new RuntimeException('Secret absent ou invalide : ' . ($problemeSecret ?? '')); }
        $out($rot->simulate());
    }
    elseif (isset($opts['apply'])) {
        if ($tokens === null) { throw new RuntimeException('Secret absent ou invalide : ' . ($problemeSecret ?? '')); }
        $out($rot->apply((string)($opts['confirm'] ?? ''), (string)($opts['authorized-by'] ?? ''), isset($opts['allow-production']), isset($opts['allow-published'])));
    }
    else { $out($rot->restore((string)$opts['restore'], (string)($opts['authorized-by'] ?? ''), isset($opts['force-published']))); }
} catch (Throwable $e) {
    fwrite(STDERR, 'REFUSÉ : ' . $e->getMessage() . "\n"); exit(1);
}
