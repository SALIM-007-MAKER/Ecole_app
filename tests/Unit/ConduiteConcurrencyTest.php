<?php
/**
 * Tests d'acceptation — Lot 2 (Conduite), partie « concurrence » : verrous de la publication (CC-1), deux publications
 * simultanées (CC-2), interblocage avec reprise unique (CC-3), sur une COPIE jetable avec plusieurs processus.
 *
 * CC-1 rejoue le tableau du plan sur le VRAI service de complétude : si une requête cessait d'utiliser un index de plage,
 * ce test échouerait. Les variantes « lignes existantes seulement », « READ COMMITTED » et « sans verrou » servent de
 * témoins négatifs : elles prouvent que le test sait détecter l'absence de protection.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ConduiteConcurrencyTest.php
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
function spawn(array $cmd): array { $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH); return [$p, $pipes]; }
function collect(array $h): string { [$p, $pipes] = $h; $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); return trim($o); }

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$_ENV['BULLETIN_TOKEN_SECRET'] = hash('sha256', 'conduite-lot2-test-secret');
$_ENV['APP_ENV'] = 'local';
$_SESSION = [];
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $pre => $dir) {
        if (str_starts_with($c, $pre)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($pre))) . '.php';
            if (is_file($f)) { require_once $f; return; }
        }
    }
});

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }
$php = PHP_BINARY; $dir = ROOT_PATH . '/tests/Support/ConduiteWorkers';
$flags = sys_get_temp_dir() . '/cc_flags_' . bin2hex(random_bytes(4));
@mkdir($flags);

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $nom = $copy->name;
    $_ENV['DB_DATABASE'] = $nom;
    ConduiteCopy::migrer($db, ROOT_PATH);
    $one = fn(string $sql) => $db->query($sql)->fetchColumn();
    $classe = 131; $periode = 90;
    $bul = (int)$one("SELECT id FROM bulletins_v2 WHERE eleve_id = 1 AND periode_id = $periode");
    $typeId = (int)$one('SELECT type_evaluation_id FROM evaluations WHERE id = 4');
    $noteAutre = (int)$one("SELECT n.id FROM notes_v2 n JOIN evaluations e ON e.id = n.evaluation_id WHERE e.classe_id = $classe AND e.periode_scolaire_id = $periode AND e.matiere_id <> " . (int)$one('SELECT id FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1') . ' LIMIT 1');

    // ═════════════════════════════════════════════════════════════════════
    section('CC-1 — verrous de la publication : quelles écritures concurrentes sont réellement empêchées ?');
    $tests = [
        ['insert_note',        ['eval' => 4, 'eleve' => 121],                                  'insérer la note MANQUANTE de l\'élève 121'],
        ['update_note',        ['eval' => 4, 'eleve' => 1],                                    'modifier une note existante'],
        ['deactivate_student', ['eleve' => 4],                                                 'désactiver un élève de la classe'],
        ['move_student_in',    ['classe' => $classe, 'eleve' => 109],                          'faire entrer un élève dans la classe'],
        ['insert_student',     ['classe' => $classe],                                          'ajouter un NOUVEL élève dans la classe'],
        ['archive_eval',       ['eval' => 4],                                                  'archiver l\'évaluation de Conduite'],
        ['insert_eval',        ['classe' => $classe, 'periode' => $periode, 'type' => $typeId], 'créer une AUTRE évaluation de Conduite active'],
        ['update_other_note',  ['note' => $noteAutre],                                         'TÉMOIN : modifier la note d\'une autre matière'],
    ];
    // résultat attendu par variante : B = bloquée, A = autorisée
    $attendu = [
        'full'    => ['B', 'B', 'B', 'B', 'B', 'B', 'B', 'A'],
        'weak_pk' => ['A', 'B', 'B', 'A', 'A', 'B', 'A', 'A'],
        'full_rc' => ['A', 'B', 'B', 'A', 'A', 'B', 'A', 'A'],
        'none'    => ['A', 'A', 'A', 'A', 'A', 'A', 'A', 'A'],
    ];
    foreach ($attendu as $variante => $att) {
        $ready = "$flags/ready_$variante.json"; $release = "$flags/release_$variante.flag";
        $h = spawn([$php, "$dir/holder.php", $nom, $variante, $ready, $release, (string)$classe, (string)$periode, (string)$bul]);
        $t0 = time(); while (!file_exists($ready) && time() - $t0 < 40) { usleep(200000); }
        if (!file_exists($ready)) { check(false, "détenteur « $variante » non prêt"); collect($h); continue; }
        $info = json_decode(file_get_contents($ready), true);
        if ($variante === 'full') { check($info['iso'] === 'REPEATABLE-READ', 'le détenteur de la publication travaille bien sous REPEATABLE-READ'); }
        foreach ($tests as $i => [$action, $params, $desc]) {
            $w = json_decode(collect(spawn([$php, "$dir/worker.php", $nom, $action, '2', json_encode($params)])), true);
            $res = ($w['resultat'] ?? '?') === 'BLOQUEE' ? 'B' : (($w['resultat'] ?? '') === 'AUTORISEE' ? 'A' : '?');
            check($res === $att[$i], "[$variante] $desc : " . ($att[$i] === 'B' ? 'bloquée' : 'autorisée'), (string)($w['resultat'] ?? 'sortie illisible'));
        }
        touch($release);
        collect($h);
        if ($variante === 'full') {
            $w = json_decode(collect(spawn([$php, "$dir/worker.php", $nom, 'insert_note', '2', json_encode(['eval' => 4, 'eleve' => 121])])), true);
            check(($w['resultat'] ?? '') === 'AUTORISEE', 'après validation du détenteur, l\'insertion de la note manquante passe');
        }
    }

    // Préparation d'une publication possible : classe complète (121 inactif), jeton officiel, instantané à jour
    $db->exec('UPDATE eleves SET actif = 0 WHERE id = 121');
    $tokens = App\Modules\Academique\Services\BulletinTokenService::fromEnvironment();
    $calc = new App\Modules\Academique\Services\AcademicCalculationService(['note_passage' => 10.0]);
    $rank = new App\Modules\Academique\Services\RankingEngine($calc, new App\Modules\Academique\Repositories\RankingRepository(), ['note_passage' => 10.0]);
    $gen = new App\Modules\Academique\Services\BulletinGenerator($calc, $rank, new App\Modules\Academique\Repositories\BulletinRepository(), 8.0, 10.0, null, null, null, $tokens);
    $prepare = function () use ($db, $bul, $tokens, $gen): void {
        $tok = $tokens->issue(1, 1, 90);
        $db->prepare("UPDATE bulletins_v2 SET verification_token = ?, token_version = 2, statut = 'brouillon', published_at = NULL, published_by = NULL WHERE id = ?")->execute([$tok, $bul]);
        $d = json_decode($db->query("SELECT data_json FROM bulletins_v2 WHERE id = $bul")->fetchColumn(), true); $d['verification_token'] = $tok;
        $db->prepare('UPDATE bulletins_v2 SET data_json = ? WHERE id = ?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $bul]);
        $gen->genererBulletin(1, 90, 1, pourPublication: true);
    };
    $nbPubliees = fn() => (int)$db->query("SELECT COUNT(*) FROM bulletins_v2 WHERE id = $bul AND statut = 'publie'")->fetchColumn();

    // ═════════════════════════════════════════════════════════════════════
    section('CC-2 — deux publications simultanées du même bulletin : une seule effective');
    $prepare();
    $go = "$flags/go_cc2";
    $a = spawn([$php, "$dir/publisher.php", $nom, '1', (string)$periode, $go]);
    $b = spawn([$php, "$dir/publisher.php", $nom, '1', (string)$periode, $go]);
    usleep(1500000); touch($go);
    $ra = json_decode(collect($a), true); $rb = json_decode(collect($b), true);
    $res = [$ra['resultat'] ?? '?', $rb['resultat'] ?? '?'];
    check(count(array_filter($res, fn($r) => $r === 'PUBLIEE')) === 1 && count(array_filter($res, fn($r) => str_starts_with($r, 'REFUSEE'))) === 1, 'exactement une publication réussie et un refus', implode(' | ', $res));
    check($nbPubliees() === 1, 'une seule publication effective en base');
    check($db->query("SELECT published_by FROM bulletins_v2 WHERE id = $bul")->fetchColumn() !== null, 'published_by renseigné');

    // ═════════════════════════════════════════════════════════════════════
    section('CC-3 — interblocage : le publicateur repart d\'une transaction neuve (une seule reprise), une seule publication');
    foreach ([['lourd', null, 'écrivain lourd (le publicateur est la victime probable)'], ['leger', 1, 'écrivain léger (l\'écrivain est la victime probable)']] as [$mode, $eleveEcrit, $desc]) {
        $prepare();
        @unlink("$flags/w_$mode");
        $cmd = [$php, "$dir/writer_deadlock.php", $nom, '4', "$flags/w_$mode", '2'];
        if ($eleveEcrit !== null) { $cmd[] = (string)$eleveEcrit; }
        $w = spawn($cmd);
        $t0 = time(); while (!file_exists("$flags/w_$mode") && time() - $t0 < 20) { usleep(100000); }
        $p = spawn([$php, "$dir/publisher.php", $nom, '1', (string)$periode]);
        $rp = json_decode(collect($p), true); $rw = json_decode(collect($w), true);
        check(($rp['resultat'] ?? '') === 'PUBLIEE', "[$desc] le bulletin est publié (au besoin après une reprise)", (string)($rp['resultat'] ?? '?') . ' | ' . json_encode($rw));
        check(in_array($rw['issue'] ?? '', ['VALIDE', 'ECHEC'], true) && (($rw['issue'] ?? '') === 'VALIDE' || ($rw['errno'] ?? 0) === 1213), "[$desc] l'écrivain est validé ou victime de l'interblocage (1213)", json_encode($rw));
        check($nbPubliees() === 1, "[$desc] une seule publication effective");
    }
} finally {
    $copy->drop();
    foreach (glob("$flags/*") ?: [] as $f) { @unlink($f); }
    @rmdir($flags);
}

section('Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
