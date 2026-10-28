<?php
/**
 * Tests — database/backup.php : sauvegarde, restauration de contrôle, échec, purge, refus de la base réelle.
 *
 * Tout vise une COPIE jetable de la base (variable BACKUP_DB, refusée pour toute autre base) et un dossier temporaire (BACKUP_DIR).
 * La base réelle n'est jamais écrite (empreinte avant / après). Les sauvegardes produites ici ne sont jamais écrites dans database/backups.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BackupScriptTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
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

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_test_' . bin2hex(random_bytes(4));
mkdir($dir, 0775, true);
$admin = new PDO("mysql:host={$conn['host']};port={$conn['port']};charset=utf8mb4", $conn['user'], $conn['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$restaures = [];

try {
    $db = $copy->pdo();
    $nom = $copy->name;
    $lancer = function (array $args, array $env = []) use ($nom, $dir): array {
        $p = proc_open(array_merge([PHP_BINARY, ROOT_PATH . '/database/backup.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH,
            array_merge(getenv(), ['BACKUP_DB' => $nom, 'BACKUP_DIR' => $dir], $env));
        $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($p), $o];
    };
    $verifs = fn() => (int)$admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE '{$nom}\\_verif\\_%'")->fetchColumn();
    $fichiers = fn(string $motif = 'auto_*.sql') => glob($dir . DIRECTORY_SEPARATOR . $motif) ?: [];
    $nbTables = (int)$admin->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$nom' AND table_type = 'BASE TABLE'")->fetchColumn();

    section('1. Sauvegarde + restauration de contrôle (copie jetable)');
    [$code, $out] = $lancer(['--verify-restore']);
    check($code === 0 && str_contains($out, 'Sauvegarde validée par restauration'), 'code 0 et « validée par restauration »', "code=$code " . substr($out, 0, 200));
    check(str_contains($out, "Restauration de contrôle : $nbTables/$nbTables tables, lignes identiques"), "contrôle : $nbTables/$nbTables tables, lignes identiques");
    check($verifs() === 0, 'la base temporaire de contrôle a été supprimée');
    $f = $fichiers()[0] ?? null;
    check($f !== null && filesize($f) > 100000, 'un fichier de sauvegarde est créé dans le dossier prévu (pas dans database/backups)', (string)$f);
    $dump = (string)file_get_contents($f);
    check(str_contains(substr($dump, -300), 'Dump completed'), 'le dump se termine par « Dump completed » (non tronqué)');
    check(substr_count($dump, "\nCREATE TABLE ") === $nbTables, "le dump contient les $nbTables tables");
    check(substr_count($dump, 'trg_matieres_conduite_') >= 3, 'les déclencheurs (protection de la Conduite) sont dans le dump');
    check(!preg_match('/^\s*(DROP|CREATE) DATABASE/mi', $dump) && !preg_match('/^\s*USE /mi', $dump), 'le dump ne crée, ne supprime ni ne change de base : il ne peut pas toucher une autre base à la restauration');
    check(!str_contains($dump, 'AES') && str_contains($dump, 'INSERT INTO'), 'LIMITE CONNUE : le dump est du SQL en clair, non chiffré (à protéger et à chiffrer avant tout transfert)');

    section('2. Vérification indépendante : restaurer ce fichier nous-mêmes et comparer le CONTENU (somme de contrôle)');
    $tmp = $nom . '_verif_' . bin2hex(random_bytes(4)); $restaures[] = $tmp;
    $admin->exec("CREATE DATABASE `$tmp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql = glob('C:/wamp64/bin/mysql/*/bin/mysql.exe')[0] ?? 'mysql';
    $pr = proc_open([$mysql, "--host={$conn['host']}", "--port={$conn['port']}", "--user={$conn['user']}", '--default-character-set=utf8mb4', $tmp], [0 => ['file', $f, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp);
    stream_get_contents($pp[1]); stream_get_contents($pp[2]); $rc = proc_close($pr);
    check($rc === 0, 'la restauration du fichier dans une base vide réussit (code 0)');
    $tables = $admin->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '$nom' AND table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $diff = [];
    foreach ($tables as $t) {
        $a = $admin->query("CHECKSUM TABLE `$nom`.`$t`")->fetch(PDO::FETCH_NUM)[1]; $b = $admin->query("CHECKSUM TABLE `$tmp`.`$t`")->fetch(PDO::FETCH_NUM)[1];
        if ($a !== $b) { $diff[] = $t; }
    }
    check($diff === [], 'somme de contrôle identique pour les ' . count($tables) . ' tables (contenu, pas seulement le nombre de lignes)', implode(',', $diff));
    $fk = fn(string $s) => (int)$admin->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = '$s'")->fetchColumn();
    $tr = fn(string $s) => (int)$admin->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = '$s'")->fetchColumn();
    check($fk($tmp) === $fk($nom) && $tr($tmp) === $tr($nom), 'clés étrangères (' . $fk($nom) . ') et déclencheurs (' . $tr($nom) . ') identiques');
    $admin->exec("DROP DATABASE `$tmp`"); array_pop($restaures);

    section('3. Échec de la restauration de contrôle : sauvegarde déclarée NON valide, aucune base temporaire orpheline');
    [$code, $out] = $lancer(['--verify-restore'], ['MYSQL_BIN' => $dir . DIRECTORY_SEPARATOR . 'client_inexistant.exe']);
    check($code !== 0 && str_contains($out, 'PAS validée'), 'code non nul et « PAS validée »', "code=$code " . substr($out, 0, 160));
    check($verifs() === 0, 'aucune base temporaire « _verif_ » ne reste (le bloc de nettoyage s\'exécute même en cas d\'échec)');
    check(count($fichiers('auto_*.NON_VALIDE.sql')) === 1, 'le dump non validé est renommé « .NON_VALIDE.sql » (jamais pris pour une sauvegarde valable)');

    section('4. Purge : seules les anciennes sauvegardes automatiques de CETTE base disparaissent');
    foreach (['20200101_000000', '20200102_000000', '20200103_000000'] as $d) { file_put_contents("$dir/auto_{$nom}_{$d}.sql", "-- vieux dump $d\n"); }
    file_put_contents("$dir/manuel_important.sql", "-- sauvegarde manuelle\n");
    file_put_contents("$dir/auto_autre_base_20200101_000000.sql", "-- autre base\n");
    $avantNonValide = count($fichiers('auto_*.NON_VALIDE.sql'));
    [$code, $out] = $lancer(['--keep=2']);
    $restantes = array_map('basename', $fichiers('auto_' . $nom . '_*.sql'));
    $valides = array_values(array_filter($restantes, fn($n) => !str_contains($n, 'NON_VALIDE')));
    check($code === 0 && count($valides) === 2, '--keep=2 : 2 sauvegardes automatiques conservées (la plus récente et la précédente)', json_encode($restantes));
    check(is_file("$dir/manuel_important.sql") && is_file("$dir/auto_autre_base_20200101_000000.sql"), 'sauvegarde manuelle et sauvegarde d\'une autre base : intactes');
    check(!is_file("$dir/auto_{$nom}_20200101_000000.sql") && !is_file("$dir/auto_{$nom}_20200102_000000.sql"), 'les plus anciennes sont supprimées');
    check(count($fichiers('auto_*.NON_VALIDE.sql')) === $avantNonValide, 'un dump « NON_VALIDE » n\'est ni compté ni supprimé par la purge (preuve conservée pour analyse)');

    section('5. Garde-fou : la base réelle est refusée');
    [$code, $out] = $lancer(['--verify-restore'], ['BACKUP_DB' => 'ecole_app']);
    check($code === 2 && str_contains($out, 'REFUS') && count(glob($dir . '/auto_ecole_app_2*.sql') ?: []) === 0, 'BACKUP_DB=ecole_app : refus (code 2), aucun dump créé');

    section('Base réelle intacte');
    check(DbCopy::allDataHashes($source) === $avantSource, 'empreinte de la base réelle identique avant / après');
} finally {
    foreach ($restaures as $t) { if (preg_match('/_verif_[0-9a-f]{8}$/', $t)) { $admin->exec("DROP DATABASE IF EXISTS `$t`"); } }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $x) { @unlink($x); }
    @rmdir($dir);
    $copy->drop();
}
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
