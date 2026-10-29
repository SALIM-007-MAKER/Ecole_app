<?php
/**
 * Met à jour le jeu de données de référence des tests après l'ajout de migrations.
 *
 *   php tests/Support/fixtures/mettre_a_jour.php
 *
 * Étapes : copie jetable depuis le jeu actuel -> application des migrations en attente sur cette copie (MIGRATE_DB) -> export -> neutralisation
 * (un seul hachage de mot de passe de test, aucune adresse hors domaines de démonstration) -> remplacement du fichier. La base vivante n'est jamais lue
 * ni écrite. Relancer ensuite la suite de tests ; `tests/Unit/JeuDeReferenceTest.php` vérifie que le jeu est à jour.
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 3));
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Tests\Support\DbCopy;

$fixture = ROOT_PATH . '/tests/Support/fixtures/ecole_app_reference.sql';
$copie = DbCopy::create(ROOT_PATH);
if ($copie === null) { fwrite(STDERR, "Copie impossible (MySQL ou droits CREATE DATABASE indisponibles)\n"); exit(1); }
try {
    // 1) migrations en attente, sur la copie uniquement
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/database/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), ['MIGRATE_DB' => $copie->name]));
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($p);
    if ($code !== 0) { fwrite(STDERR, "Échec des migrations sur la copie :\n$sortie\n"); exit(1); }
    echo preg_match('/Résultat : (\d+) appliqués/', $sortie, $m) ? "Migrations appliquées sur la copie : {$m[1]}\n" : '';

    // 2) export
    $c = $copie->connection(); $dump = DbCopy::findBinary('mysqldump');
    if ($dump === null) { fwrite(STDERR, "mysqldump introuvable\n"); exit(1); }
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ref_' . bin2hex(random_bytes(4)) . '.sql';
    putenv('MYSQL_PWD=' . $c['pass']);
    exec(escapeshellarg($dump) . ' -h ' . escapeshellarg($c['host']) . ' -P ' . escapeshellarg($c['port']) . ' -u ' . escapeshellarg($c['user'])
        . ' --single-transaction --routines --triggers --skip-comments --no-tablespaces --default-character-set=utf8mb4 ' . escapeshellarg($c['db']) . ' > ' . escapeshellarg($tmp) . ' 2>NUL', $o, $r);
    putenv('MYSQL_PWD');
    if ($r !== 0 || !is_file($tmp)) { fwrite(STDERR, "Export impossible\n"); exit(1); }
    $sql = (string)file_get_contents($tmp); @unlink($tmp);

    // 3) neutralisation
    $hash = password_hash('Test-1234!', PASSWORD_BCRYPT, ['cost' => 10]);
    $sql = preg_replace_callback('/\$2y\$\d\d\$[.\/A-Za-z0-9]{53}/', fn() => $hash, $sql, -1, $nHash);   // callback : « $2y » n'est pas une référence arrière
    preg_match_all('/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[a-z]{2,})/', $sql, $e);
    $etranges = array_diff(array_unique(array_map('strtolower', $e[1])), ['ecole.dz', 'demo.ecole.local', 'edu.dz', 'ecole.example', 'example.test']);
    if ($etranges) { fwrite(STDERR, "REFUS : adresses hors démonstration dans les données : " . implode(', ', $etranges) . " — neutralisez-les avant d'enregistrer le jeu.\n"); exit(1); }
    $entete = "-- Jeu de données de référence des tests (démonstration). Schéma + données, 166+ tables.\n"
        . "-- Mots de passe : tous les comptes partagent le même hachage de test. Aucun secret, aucune adresse réelle.\n"
        . "-- Régénération : php tests/Support/fixtures/mettre_a_jour.php (voir tests/Support/fixtures/LISEZMOI.md).\n";
    file_put_contents($fixture, $entete . preg_replace('/^-- Jeu de données de référence.*?\n(-- .*?\n){2}/', '', $sql));
    echo "Jeu de données mis à jour : " . round(filesize($fixture) / 1024) . " Ko, $nHash hachage(s) neutralisé(s).\n";
} finally {
    $copie->drop();
}
