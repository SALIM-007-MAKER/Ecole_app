<?php
/**
 * Tests — installation NEUVE comme chez un hébergeur : base vide + schéma de référence + migrations + installateur, sans fichier .env
 * (paramètres par variables d'environnement). Travaille sur une base jetable ecole_app_copy_* ; la base réelle n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/InstallationNeuveTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return array{0:int,1:string} */
function lancer(array $cmd, array $env): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$mysql = DbCopy::findBinary('mysql');
$schema = ROOT_PATH . '/database/schema/ecole_app_schema_v2.0.0_T062.sql';
$nom = 'ecole_app_copy_' . bin2hex(random_bytes(4));
$root = new PDO("mysql:host={$conn['host']};port={$conn['port']};charset=utf8mb4", $conn['user'], $conn['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$avant = (int)$root->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$conn['db']}'")->fetchColumn();
$mdp = 'Essai-Install-' . bin2hex(random_bytes(3)) . '!';

section('1. Schéma de référence sur une base vide');
check($mysql !== null && is_file($schema), 'client mysql et schéma de référence présents');
$root->exec("CREATE DATABASE `$nom` CHARACTER SET utf8mb4");
try {
    putenv('MYSQL_PWD=' . $conn['pass']);
    exec(escapeshellarg($mysql) . ' -h ' . escapeshellarg($conn['host']) . ' -P ' . escapeshellarg($conn['port']) . ' -u ' . escapeshellarg($conn['user']) . ' --default-character-set=utf8mb4 ' . escapeshellarg($nom) . ' < ' . escapeshellarg($schema) . ' 2>&1', $o, $r);
    putenv('MYSQL_PWD');
    check($r === 0, 'import du schéma', implode(' ', array_slice($o, 0, 2)));
    $db = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname=$nom;charset=utf8mb4", $conn['user'], $conn['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $q = fn(string $s) => (int)$db->query($s)->fetchColumn();
    check($q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()') >= 160, 'schéma complet : ' . $q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()') . ' tables');
    check($q('SELECT COUNT(*) FROM users') === 0 && $q('SELECT COUNT(*) FROM eleves') === 0, 'aucune donnée personnelle dans le schéma');

    section('2. Migrations en attente : aucune erreur');
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/database/migrate.php'], ['MIGRATE_DB' => $nom]);
    check($code === 0 && str_contains($sortie, ' 0 erreurs'), 'migrate.php se termine sans erreur', trim((string)(preg_match('/Résultat[^\n]*/', $sortie, $m) ? $m[0] : substr($sortie, -200))));
    check($q("SELECT COUNT(*) FROM migrations_log WHERE status = 'failed'") === 0, 'aucune migration en échec dans le journal');
    $ids = []; foreach (glob(ROOT_PATH . '/database/migrations/*.php') ?: [] as $f) { if (preg_match('/[\'"]id[\'"]\s*=>\s*[\'"]([A-Za-z0-9_]+)[\'"]/', (string)file_get_contents($f), $mm)) { $ids[] = $mm[1]; } }
    $faits = $db->query("SELECT migration FROM migrations_log WHERE status = 'success'")->fetchAll(PDO::FETCH_COLUMN);
    check(array_diff($ids, $faits) === [], 'toutes les migrations du dépôt sont appliquées', implode(', ', array_diff($ids, $faits)));

    section('3. Installateur : paramètres par variables d\'environnement, sans .env');
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/database/installer.php', '--slug=essai', '--nom=Établissement Essai', '--nom-court=Essai', '--admin-email=admin@exemple.test', '--admin-nom=Admin Essai'],
        ['DB_HOST' => $conn['host'], 'DB_PORT' => (string)$conn['port'], 'DB_DATABASE' => $nom, 'DB_USERNAME' => $conn['user'], 'DB_PASSWORD' => $conn['pass'], 'INSTALL_ADMIN_PASSWORD' => $mdp]);
    check($code === 0 && str_contains($sortie, 'créé'), 'installer.php crée l\'établissement et l\'administrateur', trim(substr($sortie, 0, 160)));
    check($q('SELECT COUNT(*) FROM users') === 1 && $q("SELECT COUNT(*) FROM users WHERE email = 'admin@exemple.test' AND role = 'admin' AND actif = 1") === 1, 'un seul compte : l\'administrateur');
    check($q('SELECT COUNT(*) FROM etablissements') === 1 && $q('SELECT COUNT(*) FROM eleves') === 0, 'un établissement, aucune fiche élève');
    $h = (string)$db->query("SELECT password FROM users WHERE email = 'admin@exemple.test'")->fetchColumn();
    check(password_verify($mdp, $h), 'le mot de passe fourni par variable d\'environnement est bien celui du compte');
    [$code2, ] = lancer([PHP_BINARY, ROOT_PATH . '/database/installer.php', '--slug=autre', '--nom=Autre', '--admin-email=autre@exemple.test', '--admin-nom=Autre'],
        ['DB_HOST' => $conn['host'], 'DB_PORT' => (string)$conn['port'], 'DB_DATABASE' => $nom, 'DB_USERNAME' => $conn['user'], 'DB_PASSWORD' => $conn['pass'], 'INSTALL_ADMIN_PASSWORD' => $mdp]);
    check($code2 !== 0 && $q('SELECT COUNT(*) FROM users') === 1, 'relancer l\'installateur est refusé (pas d\'écrasement)');
} finally {
    $root->exec("DROP DATABASE IF EXISTS `$nom`");
}

section('Base réelle intacte');
$apres = (int)$root->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$conn['db']}'")->fetchColumn();
check($apres === $avant, 'nombre de tables de la base réelle inchangé');
check((int)$root->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$nom'")->fetchColumn() === 0, 'aucune base temporaire restante');
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
