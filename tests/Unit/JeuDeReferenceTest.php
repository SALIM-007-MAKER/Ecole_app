<?php
/**
 * Tests — jeu de données de référence des tests (tests/Support/fixtures/ecole_app_reference.sql) : présent, neutralisé, à jour, et utilisé par
 * DbCopy indépendamment du contenu de la base vivante. Une partie est statique ; la dernière crée une copie jetable (la base vivante n'est jamais lue
 * pour son contenu).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/JeuDeReferenceTest.php
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

$f = ROOT_PATH . '/tests/Support/fixtures/ecole_app_reference.sql';
$sql = is_file($f) ? (string)file_get_contents($f) : '';

section('1. Présence et neutralisation');
check($sql !== '' && filesize($f) > 500000, 'le jeu de données existe (' . ($sql !== '' ? round(filesize($f) / 1024) . ' Ko' : 'absent') . ')');
check(substr_count($sql, "\nCREATE TABLE ") >= 166, 'schéma complet : ' . substr_count($sql, "\nCREATE TABLE ") . ' tables');
check(substr_count($sql, 'TRIGGER') >= 3, 'déclencheurs présents');
preg_match_all('/\$2y\$\d\d\$[.\/A-Za-z0-9]{53}/', $sql, $m);
$hachages = array_unique($m[0]);
check(count($hachages) === 1 && password_verify('Test-1234!', (string)reset($hachages)), 'un seul hachage de mot de passe, celui du mot de passe de test', count($hachages) . ' hachage(s)');
preg_match_all('/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[a-z]{2,})/', $sql, $e);
$etranges = array_diff(array_unique(array_map('strtolower', $e[1])), ['ecole.dz', 'demo.ecole.local', 'edu.dz', 'ecole.example', 'example.test']);
check($etranges === [], 'aucune adresse e-mail hors domaines de démonstration', implode(', ', $etranges));
check(!preg_match('/@(gmail|yahoo|hotmail|outlook)\./i', $sql) && !preg_match('/BEGIN [A-Z ]*PRIVATE KEY/', $sql), 'aucune adresse de messagerie publique ni clé privée');
check(preg_match("/'bulletin_secret','a{16,}'/", $sql) === 1 || preg_match("/bulletin_secret[^)]{0,30}a{32,}/", $sql) === 1, 'le paramètre bulletin_secret est neutre');

section('2. À jour par rapport aux migrations du dépôt');
$ids = [];
foreach (glob(ROOT_PATH . '/database/migrations/*.php') ?: [] as $mf) {
    if (preg_match('/[\'"]id[\'"]\s*=>\s*[\'"]([A-Za-z0-9_]+)[\'"]/', (string)file_get_contents($mf), $mm)) { $ids[$mm[1]] = basename($mf); }
}
$i = strpos($sql, 'INSERT INTO `migrations_log`');
$ligne = $i === false ? '' : substr($sql, $i, (int)strpos($sql, "\n", $i) - $i);
preg_match_all("/\(\d+,'([A-Za-z0-9_]+)',/", $ligne, $lg);
$manque = array_diff_key($ids, array_flip($lg[1]));
check($ids !== [] && $manque === [], count($ids) . ' migrations du dépôt, toutes présentes dans le jeu de données', 'manquantes : ' . implode(', ', array_keys($manque)) . ' — lancer php tests/Support/fixtures/mettre_a_jour.php');

section('3. DbCopy charge ce jeu, pas la base vivante');
$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$copie = DbCopy::create(ROOT_PATH);
if ($copie === null) { check(false, 'copie impossible'); }
else {
    try {
        $p = $copie->pdo();
        $q = fn(string $s) => (int)$p->query($s)->fetchColumn();
        check($q('SELECT COUNT(*) FROM eleves') >= 20 && $q('SELECT COUNT(*) FROM bulletins_v2') === 36 && $q('SELECT COUNT(*) FROM notes_v2') > 400, 'la copie contient les données de démonstration (élèves, 36 bulletins, notes)', 'élèves ' . $q('SELECT COUNT(*) FROM eleves') . ' ; bulletins ' . $q('SELECT COUNT(*) FROM bulletins_v2'));
        check($q('SELECT COUNT(*) FROM users') === 16 && $q("SELECT COUNT(*) FROM users WHERE role IN ('admin','directeur','secretaire','comptable','enseignant','parent','eleve')") === 16, 'les 16 comptes de démonstration, tous les rôles');
        check($q('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE()') === 3, 'les 3 déclencheurs de la Conduite sont présents');
        check($q("SELECT COUNT(*) FROM eleves WHERE nom NOT REGEXP '^Nom[0-9]+$' OR prenom NOT REGEXP '^Prenom[0-9]+$'") === 0, 'élèves anonymisés (Nom01 / Prenom01)');
        check($q("SELECT COUNT(*) FROM professeurs WHERE nom NOT REGEXP '^Nom[0-9]+$' OR prenom NOT REGEXP '^Prenom[0-9]+$'") === 0 && $q("SELECT COUNT(*) FROM rh_employes WHERE nom NOT REGEXP '^Nom[0-9]+$' OR prenom NOT REGEXP '^Prenom[0-9]+$'") === 0, 'enseignants et employés anonymisés');
        check($q("SELECT COUNT(*) FROM users WHERE prenom IS NOT NULL AND prenom <> '' AND prenom NOT REGEXP '^(Prenom|Nom)[0-9]+$'") === 0 && $q("SELECT COUNT(*) FROM users WHERE nom NOT REGEXP '^(Nom[0-9]+|Administrateur)$'") === 0, 'comptes anonymisés');
        check($q("SELECT COUNT(*) FROM familles WHERE nom NOT REGEXP '^Famille Nom[0-9]+$'") === 0, 'familles anonymisées');
        check($q("SELECT COUNT(*) FROM bulletins_v2 WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.eleve_nom')) NOT REGEXP '^Nom[0-9]+$'") === 0, 'bulletins : le nom recopié dans le JSON est anonymisé');
        $live = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass']);
        check(true, 'indépendance : contenu de la base vivante aujourd\'hui = ' . (int)$live->query('SELECT COUNT(*) FROM users')->fetchColumn() . ' compte(s), ' . (int)$live->query('SELECT COUNT(*) FROM bulletins_v2')->fetchColumn() . ' bulletin(s) — sans effet sur les tests');
    } finally { $copie->drop(); }
}
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
