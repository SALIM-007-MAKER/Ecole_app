<?php
/**
 * Tests — « Publier avec QR » (publication d'une classe) et rotation des jetons v1 → v2.
 *
 * Couvre : permissions, CSRF, interrupteur de publication, classe introuvable, échec total puis partiel, bulletins déjà publiés,
 * idempotence (2e et 3e clic), Conduite incomplète, problème de base de données, rotation des 36 bulletins et des 45 anciennes
 * empreintes (retirées, invalides, cohérence des instantanés), QR présent à l'impression, vérification publique par un visiteur.
 *
 * Tout tourne sur une COPIE jetable de la base réelle (DbCopy) ; secret de jetons JETABLE ; la source n'est jamais écrite
 * (empreinte avant / après). La rotation est appliquée UNIQUEMENT à la copie (--db=<copie>, refusée pour toute autre base).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinPublierClasseTest.php
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
$SECRET = hash('sha256', 'publier-classe-test-secret');   // jetable, propre à ce test
$_ENV['BULLETIN_TOKEN_SECRET'] = $SECRET;
$_ENV['APP_URL'] = 'http://localhost/ecole_app';
$_ENV['APP_ENV'] = 'local';
unset($_ENV['BULLETIN_PUBLICATION_ENABLED'], $_ENV['TRUSTED_PROXIES']);
$_SESSION = [];

spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $pre => $dir) {
        if (str_starts_with($c, $pre)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($pre))) . '.php';
            if (is_file($f)) { require_once $f; return; }
        }
    }
});

use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\BulletinClassePublisher;
use App\Modules\Academique\Services\BulletinGenerator;
use App\Modules\Academique\Services\BulletinTokenService;
use App\Modules\Academique\Services\RankingEngine;

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
    $_ENV['DB_DATABASE'] = $nom;   // tout code in-process vise la copie (avant la première connexion applicative)
    ConduiteCopy::migrer($db, ROOT_PATH);
    ConduiteCopy::accorderDroitsBulletin($db);

    $un   = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
    $tous = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(); })();
    $mkService = function () use ($db): BulletinClassePublisher {
        $calc = new AcademicCalculationService(['note_passage' => 10.0]);
        $rank = new RankingEngine($calc, new RankingRepository(), ['note_passage' => 10.0]);
        $repo = new BulletinRepository();
        $gen  = new BulletinGenerator($calc, $rank, $repo, 8.0, 10.0, null, null, null, BulletinTokenService::fromEnvironment());
        return new BulletinClassePublisher($gen, $repo);
    };
    $http = fn(string $role, int $uid, string $m, string $u, array $post = [], array $env = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post, $env);

    // Cible : la classe/période qui porte le plus de bulletins (12), tous encore en brouillon avec l'ancien jeton (v1)
    $cible = $db->query('SELECT classe_id, periode_id, COUNT(*) n FROM bulletins_v2 GROUP BY classe_id, periode_id ORDER BY n DESC, classe_id LIMIT 1')->fetch();
    $C = (int)$cible['classe_id']; $P = (int)$cible['periode_id'];
    $URI = "/v2/academique/bulletins/classe/$C/$P/publier-tout";
    $ENV_OK = ['BULLETIN_PUBLICATION_ENABLED' => '1', 'BULLETIN_TOKEN_SECRET' => $SECRET, 'APP_URL' => 'http://localhost/ecole_app', 'APP_ENV' => 'local'];
    $admin = (int)$un("SELECT id FROM users WHERE role = 'admin' AND actif = 1 ORDER BY id LIMIT 1");
    $uid = fn(string $role) => (int)$un("SELECT id FROM users WHERE role = ? AND actif = 1 ORDER BY id LIMIT 1", [$role]);
    $lignes = fn() => $tous('SELECT * FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? ORDER BY id', [$C, $P]);
    $etat = fn() => md5(json_encode($lignes()));
    $comptes = fn() => (array)$db->query("SELECT statut, COUNT(*) n FROM bulletins_v2 WHERE classe_id = $C AND periode_id = $P GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
    $eleves = $db->query("SELECT eleve_id FROM bulletins_v2 WHERE classe_id = $C AND periode_id = $P ORDER BY eleve_id")->fetchAll(PDO::FETCH_COLUMN);

    section("0. Point de départ : classe $C / période $P");
    check(count($eleves) === 12 && $comptes() === ['brouillon' => 12], '12 bulletins en brouillon', json_encode($comptes()));
    check((int)$un('SELECT COUNT(*) FROM bulletins_v2') === 36 && (int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE token_version = 1') === 36, '36 bulletins au total, tous au jeton v1');
    check($admin > 0, 'un administrateur actif existe dans la copie');

    // ═════════════════════════════════════════════════════════════════════
    section('1. Permissions, CSRF, interrupteur, classe introuvable — AUCUNE écriture');
    $avant = $etat();
    foreach (['enseignant', 'parent', 'eleve', 'secretaire', 'comptable'] as $role) {
        $u = $uid($role);
        if (!$u) { continue; }
        [$r] = $http($role, $u, 'POST', $URI, [], $ENV_OK);
        check(($r['status'] ?? 0) === 403 && $etat() === $avant, "$role : refusé (403), aucun bulletin modifié", 'statut ' . ($r['status'] ?? '?'));
    }
    [$r] = $http('admin', $admin, 'POST', $URI, [], array_diff_key($ENV_OK, ['BULLETIN_PUBLICATION_ENABLED' => 1]));
    check(($r['status'] ?? 0) === 403 && $etat() === $avant, 'interrupteur de publication fermé : refusé, aucun bulletin modifié', 'statut ' . ($r['status'] ?? '?'));
    [$r] = $http('admin', $admin, 'GET', $URI, [], $ENV_OK);
    check(($r['status'] ?? 0) === 404 && $etat() === $avant, 'méthode GET : route inexistante (404), aucune écriture', 'statut ' . ($r['status'] ?? '?'));
    [$r] = $http('admin', $admin, 'POST', "/v2/academique/bulletins/classe/999999/$P/publier-tout", [], $ENV_OK);
    check(($r['status'] ?? 0) === 302 && $etat() === $avant, 'classe inexistante : redirection sans effet', 'statut ' . ($r['status'] ?? '?'));

    // ═════════════════════════════════════════════════════════════════════
    section('2. Jetons hérités (v1) : la publication est refusée pour chacun, le bilan est clair, rien n\'est cassé');
    $avantV1 = $etat();
    $res = $mkService()->publier($C, $P, $admin);
    check($etat() === $avantV1, 'jetons hérités : aucune écriture (les brouillons ne sont même pas régénérés)');
    check($res['publies'] === 0 && $res['deja'] === 0 && count($res['echecs']) === 12, 'service : 0 publié, 12 échecs', json_encode([$res['publies'], count($res['echecs'])]));
    check(count(array_filter($res['echecs'], fn($e) => str_contains($e['raison'], 'Jeton hérité'))) === 12, 'chaque échec indique « Jeton hérité » (rotation à faire)');
    check($comptes() === ['brouillon' => 12], 'aucun bulletin publié, 12 toujours en brouillon');
    [$r] = $http('admin', $admin, 'POST', $URI, [], $ENV_OK);
    check(($r['status'] ?? 0) === 302 && $comptes() === ['brouillon' => 12], 'HTTP administrateur : redirection avec bilan, aucun bulletin publié', 'statut ' . ($r['status'] ?? '?'));

    $avantSecret = $etat(); $lignesAvant = $lignes();
    [$r] = $http('admin', $admin, 'POST', $URI, [], ['BULLETIN_PUBLICATION_ENABLED' => '1', 'BULLETIN_TOKEN_SECRET' => 'court', 'APP_URL' => 'http://localhost/ecole_app', 'APP_ENV' => 'local']);
    check(($r['status'] ?? 0) === 302 && $etat() === $avantSecret && !str_contains((string)($r['text'] ?? ''), $SECRET), 'secret de jetons absent : redirection avec message, traitement interrompu, aucun bulletin modifié, aucun secret affiché', 'statut ' . ($r['status'] ?? '?') . ' ; colonnes modifiées=' . json_encode(array_values(array_unique(array_merge(...array_map(fn($a, $b) => array_keys(array_diff_assoc($a, $b)), $lignesAvant, $lignes()))))));

    // ═════════════════════════════════════════════════════════════════════
    section('3. Rotation v1 → v2 (copie uniquement) : 36 bulletins, 45 anciennes empreintes');
    $anciens = [];   // tous les jetons d'avant rotation : colonne ET instantané JSON
    foreach ($tous('SELECT id, verification_token, data_json FROM bulletins_v2') as $b) {
        $anciens[$b['verification_token']] = true;
        $j = json_decode($b['data_json'], true);
        if (!empty($j['verification_token'])) { $anciens[$j['verification_token']] = true; }
    }
    $nbAvant = (int)$un('SELECT COUNT(*) FROM bulletins_v2');
    $instAvant = []; foreach ($tous('SELECT id, data_json FROM bulletins_v2') as $b) { $j = json_decode($b['data_json'], true); unset($j['verification_token'], $j['qr_code_url']); $instAvant[$b['id']] = md5(json_encode($j)); }
    $statutsAvant = (array)$db->query('SELECT id, statut FROM bulletins_v2')->fetchAll(PDO::FETCH_KEY_PAIR);
    check(count($anciens) === 45, '45 jetons distincts avant rotation (36 de la colonne + 9 de l\'instantané différents)', (string)count($anciens));

    $outil = function (array $args) use ($nom, $SECRET): array {
        $p = proc_open(array_merge([PHP_BINARY, ROOT_PATH . '/database/tools/bulletin_tokens.php', '--db=' . $nom], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), ['BULLETIN_TOKEN_SECRET' => $SECRET]));
        $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($p), $o, $e];
    };
    [$code, $out] = $outil(['--simulate']);
    $sim = json_decode($out, true);
    check($code === 0 && ($sim['summary']['bulletins_total'] ?? 0) === 36 && ($sim['summary']['a_roter'] ?? 0) === 36, 'simulation : 36 bulletins à pivoter', substr($out, 0, 120));
    check(($sim['summary']['empreintes_a_retirer'] ?? 0) === 45 && ($sim['summary']['anomalies'] ?? -1) === 0, 'simulation : 45 empreintes à retirer, 0 anomalie');
    check($comptes() === ['brouillon' => 12] && (int)$un('SELECT COUNT(*) FROM bulletin_tokens_retired') === 0, 'la simulation n\'écrit rien');
    [$codeRefus] = $outil(['--apply', '--confirm=0000', '--authorized-by=test']);
    check($codeRefus !== 0 && (int)$un('SELECT COUNT(*) FROM bulletin_tokens_retired') === 0, 'application avec une somme de contrôle fausse : refusée, rien d\'écrit');
    [$codeApp] = $outil(['--apply', '--confirm=' . ($sim['checksum'] ?? ''), '--authorized-by=Test sur copie jetable']);
    check($codeApp === 0, 'application sur la copie réussie (code 0)');

    check((int)$un('SELECT COUNT(*) FROM bulletins_v2') === $nbAvant, 'aucun bulletin perdu ni ajouté');
    check((int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE token_version = 2') === 36, 'les 36 bulletins sont au jeton v2');
    check((array)$db->query('SELECT id, statut FROM bulletins_v2')->fetchAll(PDO::FETCH_KEY_PAIR) === $statutsAvant, 'statuts inchangés (tous en brouillon)');
    $instApres = []; foreach ($tous('SELECT id, data_json FROM bulletins_v2') as $b) { $j = json_decode($b['data_json'], true); unset($j['verification_token'], $j['qr_code_url']); $instApres[$b['id']] = md5(json_encode($j)); }
    check($instApres === $instAvant, 'instantanés identiques (notes, moyennes, rangs…) hors jeton et URL du QR');
    check((int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE verification_token <> JSON_UNQUOTE(JSON_EXTRACT(data_json, \'$.verification_token\'))') === 0, 'colonne et instantané portent le même jeton pour les 36 bulletins');
    $service = BulletinTokenService::fromEnvironment();
    $mal = 0; foreach ($tous('SELECT b.eleve_id, b.periode_id, e.etablissement_id, b.verification_token FROM bulletins_v2 b JOIN eleves e ON e.id = b.eleve_id') as $b) { if (!BulletinTokenService::isWellFormed($b['verification_token']) || !$service->matches($b['verification_token'], (int)$b['etablissement_id'], (int)$b['eleve_id'], (int)$b['periode_id'])) { $mal++; } }
    check($mal === 0, 'les 36 nouveaux jetons sont bien formés et se recalculent (HMAC) pour leur élève, période et établissement', "$mal invalide(s)");
    $retires = (array)$db->query('SELECT token_sha256 FROM bulletin_tokens_retired')->fetchAll(PDO::FETCH_COLUMN);
    check(count($retires) === 45, '45 empreintes d\'anciens jetons retirées', (string)count($retires));
    $manque = 0; foreach (array_keys($anciens) as $t) { if (!in_array(BulletinTokenService::retiredDigest($t), $retires, true)) { $manque++; } }
    check($manque === 0, 'chacun des 45 anciens jetons figure (par empreinte SHA-256) parmi les jetons retirés', "$manque absent(s)");
    $reste = 0; foreach (array_keys($anciens) as $t) { if ((int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE verification_token = ? OR data_json LIKE ?', [$t, '%' . $t . '%']) > 0) { $reste++; } }
    check($reste === 0, 'aucun ancien jeton ne subsiste dans la colonne ni dans les instantanés', "$reste présent(s)");

    // ═════════════════════════════════════════════════════════════════════
    section('4a. CSRF : sans jeton, une requête qui publierait normalement est refusée');
    $avantCsrf = $etat();
    [$rc] = $http('admin', $admin, 'POST', $URI, [], $ENV_OK + ['REQUEST_AS_NO_CSRF' => '1']);
    check($comptes() === ['brouillon' => 12] && $etat() === $avantCsrf, 'sans jeton CSRF : aucun des 12 bulletins (désormais publiables) n\'est publié', 'statut ' . ($rc['status'] ?? '?'));
    // preuve que le refus vient bien du CSRF : la même requête, avec jeton, serait exécutée — on le vérifie plus bas (publication réelle)

    section('4. Publication partielle : un bulletin au jeton hérité, onze valides');
    $victime = (int)$eleves[0];
    $db->exec("UPDATE bulletins_v2 SET token_version = 1 WHERE eleve_id = $victime AND periode_id = $P");
    $res = $mkService()->publier($C, $P, $admin);
    check($res['publies'] === 11 && $res['deja'] === 0 && count($res['echecs']) === 1, 'service : 11 publiés, 1 échec', json_encode([$res['publies'], $res['deja'], count($res['echecs'])]));
    check(($res['echecs'][0]['eleve_id'] ?? 0) === $victime && str_contains($res['echecs'][0]['raison'] ?? '', 'Jeton hérité') && ($res['echecs'][0]['nom'] ?? '') !== '', 'l\'échec désigne l\'élève (nom) et la raison');
    check($comptes() === ['brouillon' => 1, 'publie' => 11], 'base : 11 publiés, 1 brouillon', json_encode($comptes()));
    check((int)$un("SELECT COUNT(*) FROM bulletins_v2 WHERE classe_id = $C AND periode_id = $P AND statut = 'publie' AND token_version = 2 AND published_at IS NOT NULL") === 11, 'les 11 publiés portent un jeton v2 et une date de publication');

    // ═════════════════════════════════════════════════════════════════════
    section('5. Idempotence : 2e et 3e clic ne touchent pas aux bulletins déjà publiés');
    $publiesAvant = md5(json_encode($tous("SELECT * FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? AND statut = 'publie' ORDER BY id", [$C, $P])));
    $res2 = $mkService()->publier($C, $P, $admin);
    check($res2['publies'] === 0 && $res2['deja'] === 11 && count($res2['echecs']) === 1, '2e exécution : 0 publié, 11 déjà publiés, même échec', json_encode([$res2['publies'], $res2['deja'], count($res2['echecs'])]));
    $res3 = $mkService()->publier($C, $P, $admin);
    check($res3['publies'] === 0 && $res3['deja'] === 11, '3e exécution : identique');
    check(md5(json_encode($tous("SELECT * FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? AND statut = 'publie' ORDER BY id", [$C, $P]))) === $publiesAvant, 'les 11 bulletins publiés sont strictement identiques (toutes colonnes, y compris dates et jetons)');
    $publiesAvantSansVictime = md5(json_encode($tous("SELECT * FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? AND statut = 'publie' AND eleve_id <> ? ORDER BY id", [$C, $P, $victime])));
    [$r] = $http('admin', $admin, 'POST', $URI, [], $ENV_OK);
    check(($r['status'] ?? 0) === 302 && $comptes() === ['brouillon' => 1, 'publie' => 11], 'HTTP : le clic répété ne change rien', 'statut ' . ($r['status'] ?? '?'));

    section('6. Reprise : incohérence détectée par l\'outil, réparation, puis seul le bulletin restant est publié');
    [$sim2] = [json_decode($outil(['--simulate'])[1], true)];
    check(($sim2['summary']['anomalies'] ?? 0) === 1 && ($sim2['summary']['a_roter'] ?? -1) === 0, 'l\'outil de rotation signale comme ANOMALIE un bulletin dont la version (v1) contredit son jeton (v2) et refuse de le pivoter', json_encode($sim2['summary'] ?? []));
    $db->exec("UPDATE bulletins_v2 SET token_version = 2 WHERE eleve_id = $victime AND periode_id = $P");   // réparation de l'incohérence que nous avions créée
    $res4 = $mkService()->publier($C, $P, $admin);
    check($res4['publies'] === 1 && $res4['deja'] === 11 && $res4['echecs'] === [], 'service : 1 publié, 11 déjà publiés, 0 échec', json_encode([$res4['publies'], $res4['deja'], count($res4['echecs'])]));
    check($comptes() === ['publie' => 12], 'base : 12 publiés', json_encode($comptes()));
    check(md5(json_encode($tous("SELECT * FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? AND statut = 'publie' AND eleve_id <> ? ORDER BY id", [$C, $P, $victime]))) === $publiesAvantSansVictime, 'les 11 premiers bulletins publiés sont restés strictement identiques après la reprise (toutes colonnes)');
    // ═════════════════════════════════════════════════════════════════════
    section('7. QR à l\'impression et vérification publique (parents)');
    // La page publique est limitée à 30 requêtes / 60 s / IP : on repart d'un compteur propre (état transitoire du limiteur, sans donnée métier).
    $viderLimiteur = function (): void { foreach (glob(ROOT_PATH . '/storage/cache/throttle/*') ?: [] as $f) { @unlink($f); } };
    $viderLimiteur();
    $pub = $tous("SELECT eleve_id, verification_token FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? AND statut = 'publie' ORDER BY eleve_id", [$C, $P]);
    $sansQr = 0; $imprimes = 0;
    foreach ($pub as $b) {
        [$r, $corps] = $http('admin', $admin, 'GET', "/v2/academique/bulletins/{$b['eleve_id']}/$P/imprimer", [], $ENV_OK);
        $imprimes++;
        if (($r['status'] ?? 0) !== 200 || !preg_match('#class="qr-block".*?<svg#s', $corps) || str_contains($corps, 'BROUILLON — non officiel')) { $sansQr++; }
    }
    check($imprimes === 12 && $sansQr === 0, 'les 12 bulletins s\'impriment avec leur QR (svg) et sans mention « brouillon »', "$sansQr sans QR");
    [$r, $corps] = $http('admin', $admin, 'GET', "/v2/academique/bulletins/{$pub[0]['eleve_id']}/$P/imprimer", [], $ENV_OK);
    $urlAttendue = \App\Modules\Academique\Services\BulletinVerificationUrl::build($pub[0]['verification_token'], 'http://localhost/ecole_app', 'local');
    $svgAttendu  = (new \Core\QrCode\QrEncoder())->generateSvg($urlAttendue);
    check(str_contains($urlAttendue, '/v2/academique/bulletins/verify/' . $pub[0]['verification_token']) && str_contains($corps, $svgAttendu), 'le QR imprimé est EXACTEMENT celui de l\'adresse de vérification du bulletin (' . substr($urlAttendue, 0, 60) . '…)');
    $ko = 0; $statuts = []; foreach ($pub as $b) { [$rv] = $http('guest', 0, 'GET', "/v2/academique/bulletins/verify/{$b['verification_token']}", [], $ENV_OK); $statuts[] = $rv['status'] ?? '?'; if (($rv['status'] ?? 0) !== 200) { $ko++; } }
    check($ko === 0, 'les 12 jetons sont vérifiables publiquement (200)', "$ko refusé(s) — statuts : " . implode(',', $statuts));
    $anc = array_slice(array_keys($anciens), 0, 5); $encore = 0;
    foreach ($anc as $t) { [$rv] = $http('guest', 0, 'GET', "/v2/academique/bulletins/verify/$t", [], $ENV_OK); if (($rv['status'] ?? 0) !== 404) { $encore++; } }
    check($encore === 0, 'les anciens QR (jetons v1 retirés) sont refusés (404)', "$encore accepté(s)");
    [$rv] = $http('guest', 0, 'GET', '/v2/academique/bulletins/verify/' . str_repeat('a', 64), [], $ENV_OK);
    check(($rv['status'] ?? 0) === 404, 'un faux jeton est refusé (404)');

    // ═════════════════════════════════════════════════════════════════════
    $viderLimiteur();

    // ═════════════════════════════════════════════════════════════════════
    section('8. Conduite incomplète : aucune publication, raison explicite');
    $cI = 131; $pI = 90;   // jeu de données du Lot 2 : l'élève 121 n'a aucune note de Conduite
    $nI = (int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ?', [$cI, $pI]);
    $resI = $mkService()->publier($cI, $pI, $admin);
    check($resI['publies'] === 0 && count($resI['echecs']) > 0, 'classe 131 : aucun bulletin publié', json_encode([$resI['publies'], count($resI['echecs'])]));
    check(count(array_filter($resI['echecs'], fn($e) => stripos($e['raison'], 'conduite') !== false)) === count($resI['echecs']), 'toutes les raisons mentionnent la Conduite', json_encode(array_slice(array_column($resI['echecs'], 'raison'), 0, 1)));
    check((int)$un("SELECT COUNT(*) FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ? AND statut <> 'brouillon'", [$cI, $pI]) === 0 && (int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ?', [$cI, $pI]) >= $nI, 'aucun bulletin de cette classe n\'a quitté le brouillon');

    // ═════════════════════════════════════════════════════════════════════
    section('9. Problème de base de données en cours de route');
    $db->exec("UPDATE bulletins_v2 SET statut = 'brouillon', token_version = 2, published_at = NULL WHERE classe_id = $C AND periode_id = $P AND eleve_id = $victime");
    $db->exec('RENAME TABLE bulletins_v2 TO bulletins_v2_hs');
    $exception = null; $resDb = null;
    try { $resDb = $mkService()->publier($C, $P, $admin); } catch (\Throwable $e) { $exception = $e; }
    $db->exec('RENAME TABLE bulletins_v2_hs TO bulletins_v2');
    check($exception === null && $resDb !== null, 'aucune exception ne s\'échappe du service (le traitement continue)', $exception ? get_class($exception) . ' : ' . $exception->getMessage() : '');
    if ($resDb !== null) {
        check(count($resDb['echecs']) > 0 && $resDb['publies'] === 0, 'tous les bulletins sont signalés en échec, aucun faux « publié »');
        $fuite = array_filter($resDb['echecs'], fn($e) => preg_match('/SQLSTATE|bulletins_v2|Table|doesn\'t exist|ecole_app/i', $e['raison']));
        check($fuite === [], 'les messages affichés ne divulguent ni requête, ni nom de table, ni base', json_encode(array_slice(array_column(array_values($fuite), 'raison'), 0, 1)));
    }
    check((int)$un('SELECT COUNT(*) FROM bulletins_v2 WHERE classe_id = ? AND periode_id = ?', [$C, $P]) === 12, 'la table est intacte après l\'incident (12 bulletins)');

    section('10. Génération PDF');
    check(!str_contains((string)file_get_contents(ROOT_PATH . '/app/Modules/Academique/Services/BulletinClassePublisher.php'), 'TCPDF')
        && !str_contains((string)file_get_contents(ROOT_PATH . '/app/Modules/Academique/Controllers/BulletinController.php'), 'TCPDF'), 'aucune génération PDF côté serveur : l\'impression est une page HTML (le navigateur produit le PDF) — pas de point de défaillance PDF');

    section('Base réelle intacte');
    check(DbCopy::allDataHashes($source) === $avantSource, 'empreinte de la base réelle identique avant / après');
} finally {
    $copy->drop();
}
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
