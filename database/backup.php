<?php

/**
 * Sauvegarde planifiable de la base (CLI) — dump complet, contrôle de fin de fichier, rétention, restauration-test optionnelle.
 *
 * Usage : C:/wamp64/bin/php/php8.2.29/php.exe database/backup.php [--keep=14] [--verify-restore]
 *   --keep=N          conserve les N dernières sauvegardes automatiques (défaut 14) ; les dumps manuels ne sont jamais supprimés
 *   --verify-restore  restaure le dump dans une base temporaire « <base>_verif_<hex> », compare tables / lignes, puis la supprime
 *
 * Fichiers : database/backups/auto_<base>_AAAAMMJJ_HHMMSS.sql (dossier ignoré par git). Code de sortie ≠ 0 en cas d'échec.
 * Une sauvegarde n'est considérée valide que si --verify-restore a réussi.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) continue;
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
$host = $env['DB_HOST'] ?? '127.0.0.1'; $port = $env['DB_PORT'] ?? '3306'; $db = $env['DB_DATABASE'] ?? 'ecole_app';
$user = $env['DB_USERNAME'] ?? 'root'; $pass = $env['DB_PASSWORD'] ?? '';
$keep = 14; $verify = in_array('--verify-restore', $argv, true);
foreach ($argv as $a) if (preg_match('/^--keep=(\d+)$/', $a, $m)) $keep = max(1, (int)$m[1]);

function fail(string $m): never { fwrite(STDERR, "ECHEC : $m\n"); exit(1); }
function bin(string $name): string
{
    foreach (glob('C:/wamp64/bin/mysql/*/bin/' . $name . '.exe') ?: [] as $p) return $p;
    return $name;   // dans le PATH (Linux / conteneur)
}
function run(array $cmd, ?string $in = null, ?string $out = null, array $env = []): int
{
    $spec = [0 => $in ? ['file', $in, 'r'] : ['pipe', 'r'], 1 => $out ? ['file', $out, 'w'] : ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p = proc_open($cmd, $spec, $pipes, null, $env ? array_merge(getenv(), $env) : null);
    if (!is_resource($p)) return 127;
    if (isset($pipes[0])) fclose($pipes[0]);
    if (isset($pipes[1])) stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]); if ($err !== '' && !str_contains($err, 'Using a password')) fwrite(STDERR, $err);
    return proc_close($p);
}

$dir = ROOT_PATH . '/database/backups';
if (!is_dir($dir) && !mkdir($dir, 0775, true)) fail('dossier de sauvegarde non créable');
$file = sprintf('%s/auto_%s_%s.sql', $dir, $db, date('Ymd_His'));
$auth = ["--host=$host", "--port=$port", "--user=$user"];
$penv = $pass !== '' ? ['MYSQL_PWD' => $pass] : [];

if (run(array_merge([bin('mysqldump')], $auth, ['--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', $db]), null, $file, $penv) !== 0) {
    @unlink($file); fail('mysqldump a échoué');
}
$tail = (string)file_get_contents($file, false, null, max(0, filesize($file) - 300));
if (!str_contains($tail, 'Dump completed')) { fail("fin de dump absente (fichier tronqué ?) : $file"); }
echo 'Sauvegarde : ' . basename($file) . ' (' . round(filesize($file) / 1024) . " Ko)\n";

if ($verify) {
    $tmp = $db . '_verif_' . bin2hex(random_bytes(4));
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $ok = false;
    try {
        $pdo->exec("CREATE DATABASE `$tmp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (run(array_merge([bin('mysql')], $auth, ['--default-character-set=utf8mb4', $tmp]), $file, null, $penv) !== 0) fail('restauration de contrôle impossible');
        $ts = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='$db' AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $bad = [];
        foreach ($ts as $t) {
            $a = $pdo->query("SELECT COUNT(*) FROM `$db`.`$t`")->fetchColumn(); $b = $pdo->query("SELECT COUNT(*) FROM `$tmp`.`$t`")->fetchColumn();
            if ($a !== $b) $bad[] = "$t ($a ≠ $b)";   // une écriture survenue pendant le dump peut expliquer un écart : à relire
        }
        $nt = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$tmp' AND table_type='BASE TABLE'")->fetchColumn();
        $ok = $nt === count($ts) && !$bad;
        echo "Restauration de contrôle : $nt/" . count($ts) . ' tables' . ($bad ? ', écarts : ' . implode(', ', $bad) : ', lignes identiques') . "\n";
    } finally {
        if (preg_match('/^' . preg_quote($db, '/') . '_verif_[0-9a-f]{8}$/', $tmp)) $pdo->exec("DROP DATABASE IF EXISTS `$tmp`");
    }
    if (!$ok) fail('la sauvegarde n\'est PAS validée (voir ci-dessus)');
    echo "Sauvegarde validée par restauration.\n";
}

$auto = glob($dir . '/auto_' . $db . '_*.sql') ?: []; rsort($auto);
foreach (array_slice($auto, $keep) as $old) { @unlink($old); echo 'Purge : ' . basename($old) . "\n"; }
