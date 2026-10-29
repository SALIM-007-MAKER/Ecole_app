<?php
/**
 * Tests — listes déroulantes de classes : chaque page qui en propose une doit afficher, pour chaque classe de la base, le libellé complet
 * « Niveau — Nom » (jamais d'option vide), et ClasseModel::findForSelect() doit fournir id, nom, niveau et label.
 * Régression corrigée : des vues lisaient $c->nom sur des lignes qui ne portaient que id et label (option vide + avertissement PHP).
 *
 * Tout tourne sur une COPIE jetable de la base ; la base réelle n'est jamais écrite (empreinte avant / après).
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ListesClassesTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';

use Tests\Support\ConduiteCopy;
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

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    $nom = $copy->name;
    ConduiteCopy::migrer($db, ROOT_PATH);
    ConduiteCopy::accorderDroitsBulletin($db);

    $classes = $db->query('SELECT id, niveau, nom FROM classes WHERE etablissement_id = 1 ORDER BY id')->fetchAll();
    $attendus = array_map(fn($c) => $c['niveau'] . ' — ' . $c['nom'], $classes);
    sort($attendus);
    $admin = (int)$db->query("SELECT id FROM users WHERE role = 'admin' AND actif = 1 ORDER BY id LIMIT 1")->fetchColumn();

    section('1. Le modèle fournit id, nom, niveau et label');
    check(count($classes) > 0, count($classes) . ' classe(s) dans la copie');
    $model = (function () use ($nom) {
        $_ENV['DB_DATABASE'] = $nom;
        spl_autoload_register(function (string $c): void {
            foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $pre => $dir) {
                if (str_starts_with($c, $pre)) { $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($pre))) . '.php'; if (is_file($f)) { require_once $f; return; } }
            }
        });
        return new \App\Models\ClasseModel();
    })();
    $lignes = $model->findForSelect();
    check(count($lignes) === count($classes), 'findForSelect() renvoie toutes les classes de l\'établissement (' . count($lignes) . ')');
    $champsOk = true; foreach ($lignes as $l) { foreach (['id', 'nom', 'niveau', 'label'] as $p) { if (!isset($l->$p) || $l->$p === '') { $champsOk = false; } } }
    check($champsOk, 'chaque ligne porte id, nom, niveau et label non vides');
    $labels = array_map(fn($l) => $l->label, $lignes); sort($labels);
    check($labels === $attendus, 'les libellés sont « Niveau — Nom »', json_encode(array_slice($labels, 0, 2), JSON_UNESCAPED_UNICODE));

    section('2. Pages avec liste de classes : une option complète par classe, aucune option vide');
    $pages = ['/v2/finance/factures', '/v2/finance/factures/generer', '/v2/vie-scolaire/absences', '/v2/vie-scolaire/absences/create', '/v2/vie-scolaire/absences/statistiques',
              '/v2/vie-scolaire/presences', '/v2/vie-scolaire/presences/statistiques', '/v2/vie-scolaire/presences/create'];
    foreach ($pages as $p) {
        [$r, $corps] = ConduiteCopy::http(ROOT_PATH, $nom, 'admin', $admin, 'GET', '/ecole_app' . $p);
        $statut = (int)($r['status'] ?? 0);
        $options = [];
        if (preg_match_all('#<select[^>]*name="(?:classe_id|classe)"[^>]*>(.*?)</select>#s', $corps, $sel)) {
            foreach ($sel[1] as $bloc) {
                if (preg_match_all('#<option[^>]*value="(\d+)"[^>]*>\s*(.*?)\s*</option>#s', $bloc, $o, PREG_SET_ORDER)) { foreach ($o as $x) { $options[] = trim(html_entity_decode(strip_tags($x[2]), ENT_QUOTES)); } }
            }
        }
        $vides = count(array_filter($options, fn($t) => $t === ''));
        $trie = $options; sort($trie);
        check($statut === 200 && $trie === $attendus && $vides === 0, "$p : " . count($options) . ' option(s), ' . $vides . ' vide(s)', "statut $statut ; options " . json_encode(array_slice($trie, 0, 3), JSON_UNESCAPED_UNICODE));
    }

    section('3. Aucun avertissement PHP « Undefined property » sur ces pages');
    $journal = ROOT_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
    $avant = is_file($journal) ? filesize($journal) : 0;
    foreach ($pages as $p) { ConduiteCopy::http(ROOT_PATH, $nom, 'admin', $admin, 'GET', '/ecole_app' . $p); }
    clearstatcache();
    $nouveau = is_file($journal) ? (string)file_get_contents($journal, false, null, $avant) : '';
    $warn = preg_match_all('/Undefined property: stdClass::\$(nom|niveau|label)|htmlspecialchars\(\): Passing null/', $nouveau, $mm);
    check($warn === 0, 'aucun avertissement de propriété manquante dans le journal pendant le parcours des 8 pages', "$warn avertissement(s)");

    section('4. Autres listes de classes : niveau affiché avec le nom (pas le nom court seul)');
    foreach (['VieScolaire/Views/retards/statistiques', 'VieScolaire/Views/retards/create', 'VieScolaire/Views/discipline/create', 'VieScolaire/Views/recompenses/classement',
              'VieScolaire/Views/recompenses/create', 'Finance/Views/rapports/paiements', 'Finance/Views/rapports/impayes'] as $v) {
        $src = (string)file_get_contents(ROOT_PATH . "/app/Modules/$v.php");
        check(!str_contains($src, 'htmlspecialchars($c->nom)') && str_contains($src, '$c->niveau'), "$v : l'option affiche « niveau — nom »");
    }

    section('Base réelle intacte');
    check(DbCopy::allDataHashes($source) === $avantSource, 'empreinte de la base réelle identique avant / après');
} finally {
    $copy->drop();
}
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
