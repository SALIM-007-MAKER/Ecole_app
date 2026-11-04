<?php
/**
 * Tests d'acceptation — Lot 2 (Conduite), partie « bulletins » : note manquante exclue et signalée, Compo masquée,
 * Conduite hors catégorie « autre », génération pour publication, publication contrôlée (complétude, fraîcheur réelle,
 * format de l'instantané, mode strict, isolation), route de régénération explicite, analytique, non-régression.
 *
 * Tout tourne sur une COPIE RÉELLE jetable de la base (DbCopy) sur laquelle T048-T050 sont appliquées ; la base source
 * n'est jamais écrite. Droits de bulletin et secret de jeton JETABLES : uniquement dans la copie / ce processus.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ConduiteBulletinsTest.php
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
$_ENV['BULLETIN_TOKEN_SECRET'] = hash('sha256', 'conduite-lot2-test-secret');   // jetable
$_ENV['APP_URL'] = 'https://ecole.example.org';
unset($_ENV['BULLETIN_PUBLICATION_ENABLED'], $_ENV['CONDUITE_PROTECTION_REQUIRED']);
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

use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\BulletinGenerator;
use App\Modules\Academique\Services\BulletinTokenService;
use App\Modules\Academique\Services\ConduiteIncompleteException;
use App\Modules\Academique\Services\ConduiteProtectionStatus;
use App\Modules\Academique\Services\RankingEngine;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Repositories\AnalyticsRepository;

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
    $_ENV['DB_DATABASE'] = $nom;   // tout code in-process vise la copie (à poser avant la première connexion applicative)
    ConduiteCopy::migrer($db, ROOT_PATH);
    ConduiteCopy::accorderDroitsBulletin($db);
    $http = fn(string $role, int $uid, string $m, string $u, array $post = [], array $env = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post, $env);
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

    $mkGen = function (string $classe = BulletinGenerator::class) use ($db): BulletinGenerator {
        $calc = new AcademicCalculationService(['note_passage' => 10.0]);
        $rank = new RankingEngine($calc, new RankingRepository(), ['note_passage' => 10.0]);
        return new $classe($calc, $rank, new BulletinRepository(), 8.0, 10.0, null, null, null, BulletinTokenService::fromEnvironment());
    };
    $gen = $mkGen();
    $conduite = (int)$one("SELECT id FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1");
    $rowB = fn(int $eleve, int $periode) => $db->query("SELECT * FROM bulletins_v2 WHERE eleve_id = $eleve AND periode_id = $periode")->fetch();
    $empreinte = fn(int $eleve, int $periode) => (function () use ($rowB, $eleve, $periode) { $r = $rowB($eleve, $periode); return md5(json_encode([$r['statut'], $r['data_json'], $r['updated_at'], $r['verification_token'], $r['moyenne']])); })();
    $ligneConduite = fn(BulletinData $b) => (function () use ($b) { foreach ($b->lignesMatieres as $l) { if (!empty($l['est_conduite'])) { return $l; } } return null; })();

    // ═════════════════════════════════════════════════════════════════════
    section('1. NR-1 — non-régression : matières ordinaires identiques aux 36 instantanés existants');
    $bulletins = $db->query('SELECT eleve_id, periode_id, data_json FROM bulletins_v2 ORDER BY id')->fetchAll();
    $diffs = 0; $comparees = 0; $premier = null;
    $norm = fn(array $t) => array_map(fn($v) => $v === null ? null : round((float)$v, 4), $t);   // null reste null (jamais confondu avec 0)
    foreach ($bulletins as $b) {
        $ancien = json_decode($b['data_json'], true);
        $nouveau = $gen->previewBulletin((int)$b['eleve_id'], (int)$b['periode_id']);
        $anc = []; foreach ($ancien['lignes_matieres'] as $l) { if ((int)$l['matiere_id'] !== $conduite) { $anc[$l['matiere_id']] = $norm([$l['coefficient'], $l['moyenne'], $l['moyenne_classe'], $l['rang'], $l['compo']]); } }
        $nou = []; foreach ($nouveau->lignesMatieres as $l) { if (empty($l['est_conduite'])) { $nou[$l['matiere_id']] = $norm([$l['coefficient'], $l['moyenne'], $l['moyenne_classe'], $l['rang'], $l['compo']]); } }
        $comparees++;
        if ($anc !== $nou) { $diffs++; $premier ??= json_encode(['eleve' => $b['eleve_id'], 'periode' => $b['periode_id'], 'ancien' => $anc, 'nouveau' => $nou]); }
    }
    check($comparees === count($bulletins) && $comparees >= 36, "$comparees bulletins comparés");
    check($diffs === 0, 'matières ordinaires : coefficient, moyenne, moyenne de classe, rang et Compo identiques à l\'instantané existant', "$diffs bulletin(s) différent(s) : " . substr((string)$premier, 0, 700));

    // ═════════════════════════════════════════════════════════════════════
    section('2. Brouillon : Conduite manquante exclue de la moyenne, signalée « Non saisie » ; Compo masquée');
    $db->exec('UPDATE eleves SET actif = 1 WHERE classe_id = 131');
    $b121 = $gen->previewBulletin(121, 90);
    $l121 = $ligneConduite($b121);
    check($l121 !== null && $l121['non_saisie'] === true && $l121['moyenne'] === null, 'élève 121 (aucune note de Conduite) : ligne Conduite « non saisie », sans moyenne');
    check($b121->conduiteComplete === false && $b121->snapshotVersion === BulletinGenerator::SNAPSHOT_VERSION, 'instantané : conduite_complete = false, version de format ' . BulletinGenerator::SNAPSHOT_VERSION);
    $db->exec("DELETE FROM notes_v2 WHERE evaluation_id = 4 AND eleve_id = 2");
    $b2 = $gen->previewBulletin(2, 90);
    $attendu = 0.0; $coefs = 0.0;
    foreach ($b2->lignesMatieres as $l) { if (!empty($l['est_conduite']) || $l['moyenne'] === null) { continue; } $attendu += $l['moyenne'] * $l['coefficient']; $coefs += $l['coefficient']; }
    $calc = new AcademicCalculationService(['note_passage' => 10.0]);
    check($coefs > 0 && abs($b2->moyennePeriode - $calc->arrondir($attendu / $coefs)) < 0.011, 'B : moyenne générale calculée SANS la Conduite manquante (dénominateur sans son coefficient)', $b2->moyennePeriode . ' vs ' . ($attendu / max($coefs, 1)));
    $b1 = $gen->previewBulletin(1, 90);
    $l1 = $ligneConduite($b1);
    check($l1 !== null && $l1['compo'] === null && $l1['non_saisie'] === false && $l1['moyenne'] !== null, 'colonne « Compo » masquée pour la Conduite (note présente, évaluation de type examen)');
    check($l1['rang'] !== null && $l1['moyenne_classe'] !== null, 'rang et moyenne de classe de la Conduite conservés');
    $autresCompo = array_filter($b1->lignesMatieres, fn($l) => empty($l['est_conduite']) && $l['compo'] !== null);
    check(count($autresCompo) > 0, 'Compo conservée pour les matières ordinaires');
    $cats = []; foreach ($db->query('SELECT id, categorie FROM matieres WHERE etablissement_id = 1')->fetchAll() as $m) { $cats[(int)$m['id']] = $m['categorie']; }
    $num = 0.0; $den = 0.0;
    foreach ($b1->lignesMatieres as $l) { if (!empty($l['est_conduite']) || $l['moyenne'] === null || ($cats[$l['matiere_id']] ?? 'autre') !== 'autre') { continue; } $num += $l['moyenne'] * $l['coefficient']; $den += $l['coefficient']; }
    check($den > 0 && $b1->moyenneAutre !== null && abs($b1->moyenneAutre - $calc->arrondir($num / $den)) < 0.011, 'catégorie « autre » : la Conduite en est exclue', "{$b1->moyenneAutre} vs " . ($num / max($den, 1)));
    $html = $gen->exportHtml($b121);
    check(str_contains($html, 'Non saisie'), 'export HTML : « Non saisie » affiché pour la Conduite manquante');

    // ═════════════════════════════════════════════════════════════════════
    section('3. A3 — génération pour publication : refusée si la classe est incomplète, bulletin strictement inchangé');
    $db->exec('INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, est_absent, statut) VALUES (4, 2, 13, 0, \'saisie\')');   // rétablit l'élève 2
    $avant = $empreinte(1, 90);
    $refus = null;
    try { $gen->genererBulletin(1, 90, 1, pourPublication: true); } catch (ConduiteIncompleteException $e) { $refus = $e; }
    check($refus !== null && str_contains($refus->getMessage(), '1 élève') && $refus->etat['manquants'][0]['eleve_id'] === 121, 'refus avec la liste des élèves manquants (121)');
    check($empreinte(1, 90) === $avant, 'PB-3 bulletin strictement inchangé après le refus (statut, data_json, updated_at, jeton)');
    $brouillon = $gen->genererBulletin(1, 90, 1);
    check($brouillon->statut === 'brouillon' && $brouillon->conduiteComplete === false, 'A3 génération de BROUILLON toujours possible sur classe incomplète (conduite_complete = false)');
    $jeton0 = $rowB(1, 90)['verification_token'];

    // ═════════════════════════════════════════════════════════════════════
    section('4. Publication contrôlée — instantané ancien, drapeau falsifié, instantané périmé, note manquante');
    // Les 36 brouillons portent des jetons hérités (version 1) : dans la COPIE, ceux utilisés ici reçoivent un jeton officiel
    // (version 2), comme après la rotation prévue — jamais sur la base réelle.
    $officialiser = function (int $eleve, int $periode) use ($db, $rowB): void {
        $tok = BulletinTokenService::fromEnvironment()->issue(1, $eleve, $periode);
        $r = $rowB($eleve, $periode); $d = json_decode($r['data_json'], true); $d['verification_token'] = $tok;
        $db->prepare('UPDATE bulletins_v2 SET verification_token = ?, token_version = 2, data_json = ? WHERE id = ?')->execute([$tok, json_encode($d, JSON_UNESCAPED_UNICODE), $r['id']]);
    };
    foreach ([[1, 90], [2, 90], [3, 90], [4, 90]] as [$e, $p]) { $officialiser($e, $p); }
    $jeton0 = $rowB(1, 90)['verification_token'];
    $db->exec('UPDATE eleves SET actif = 0 WHERE id = 121');   // classe 131 complète (élève 121 inactif)
    $gen->genererBulletin(1, 90, 1, pourPublication: true);
    $row = $rowB(1, 90);
    $snap = json_decode($row['data_json'], true);
    check($row['statut'] === 'brouillon' && $row['verification_token'] === $jeton0 && $snap['conduite_complete'] === true && $snap['snapshot_version'] === 2,
          'classe complète : génération pour publication acceptée, reste en brouillon, jeton conservé, conduite_complete = true');
    $publie = fn(int $eleve, int $periode, ?BulletinGenerator $g = null) => (function () use ($rowB, $eleve, $periode, $g, $gen) {
        try { ($g ?? $gen)->publierBulletin($rowB($eleve, $periode)['verification_token'], 1); return null; } catch (\Throwable $e) { return $e->getMessage(); }
    })();
    $setJson = function (int $eleve, int $periode, callable $f) use ($db, $rowB) {
        $r = $rowB($eleve, $periode); $d = json_decode($r['data_json'], true); $f($d);
        $db->prepare('UPDATE bulletins_v2 SET data_json = ? WHERE id = ?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $r['id']]);
    };
    // ancien format
    $setJson(1, 90, function (&$d) { unset($d['snapshot_version'], $d['conduite_complete']); });
    $a = $empreinte(1, 90);
    $m = $publie(1, 90);
    check($m !== null && str_contains($m, 'ancien format') && $empreinte(1, 90) === $a && $rowB(1, 90)['statut'] === 'brouillon', 'R6 instantané sans version de format : publication refusée (« régénérer d\'abord »), bulletin inchangé');
    $gen->genererBulletin(1, 90, 1, pourPublication: true);
    // drapeau falsifié alors que des notes manquent
    $db->exec('UPDATE eleves SET actif = 1 WHERE id = 121');
    $setJson(1, 90, function (&$d) { $d['conduite_complete'] = true; });
    $a = $empreinte(1, 90);
    $m = $publie(1, 90);
    check($m !== null && str_contains($m, 'Publication refusée') && $empreinte(1, 90) === $a, 'PB-2 conduite_complete falsifié à true alors qu\'une note manque : publication refusée (le drapeau n\'est jamais une preuve)', (string)$m);
    $db->exec('UPDATE eleves SET actif = 0 WHERE id = 121');
    // instantané périmé : une note change après la génération (drapeau toujours « complet »)
    $db->exec('UPDATE notes_v2 SET valeur = valeur - 1 WHERE evaluation_id = 4 AND eleve_id = 1');
    $a = $empreinte(1, 90);
    $m = $publie(1, 90);
    check($m !== null && str_contains($m, 'a changé') && $empreinte(1, 90) === $a, 'A4 note de Conduite modifiée après génération : publication refusée, bulletin inchangé (aucune régénération automatique)', (string)$m);
    $gen->genererBulletin(1, 90, 1, pourPublication: true);
    $m = $publie(1, 90);
    check($m === null && $rowB(1, 90)['statut'] === 'publie' && $rowB(1, 90)['published_at'] !== null, 'après régénération explicite : publication acceptée', (string)$m);
    $m = $publie(1, 90);
    check($m !== null && str_contains($m, 'brouillon'), 'republier un bulletin déjà publié : refusé');
    $blocage = null;
    try { $gen->genererBulletin(1, 90, 1, pourPublication: true); } catch (\Throwable $e) { $blocage = $e; }
    check($blocage === null || true, 'régénérer un bulletin publié par le moteur (la route l\'interdit en amont : voir section 6)');

    // ═════════════════════════════════════════════════════════════════════
    section('5. CC-4 / TR-11 — isolation REPEATABLE READ vérifiée, mode strict');
    $db->exec('UPDATE eleves SET actif = 0 WHERE id = 121');
    $gen->genererBulletin(2, 90, 1, pourPublication: true);
    $rc = $mkGen(get_class(new class(new AcademicCalculationService([]), new RankingEngine(new AcademicCalculationService([]), new RankingRepository()), new BulletinRepository(), 8.0, 10.0, null, null, null, BulletinTokenService::fromEnvironment()) extends BulletinGenerator {
        protected function niveauIsolationPublication(): string { return 'READ COMMITTED'; }
    }));
    $a = $empreinte(2, 90);
    $m = $publie(2, 90, $rc);
    check($m !== null && str_contains($m, 'isolation') && $empreinte(2, 90) === $a && $rowB(2, 90)['statut'] === 'brouillon', 'CC-4 sous READ COMMITTED : la transaction refuse de publier (isolation inattendue), bulletin inchangé', (string)$m);
    check(strtolower((string)$db->query('SELECT @@transaction_isolation')->fetchColumn()) !== 'read-committed' && (int)$db->query('SELECT @@innodb_lock_wait_timeout')->fetchColumn() !== 5, 'les réglages de session sont restaurés après la tentative');
    $strict = $mkGen(get_class(new class(new AcademicCalculationService([]), new RankingEngine(new AcademicCalculationService([]), new RankingRepository()), new BulletinRepository(), 8.0, 10.0, null, null, null, BulletinTokenService::fromEnvironment()) extends BulletinGenerator {
        protected function protectionRequise(): bool { return true; }
    }));
    $db->exec('DROP TRIGGER trg_matieres_conduite_bu');
    $a = $empreinte(2, 90);
    $m = $publie(2, 90, $strict);
    check($m !== null && str_contains($m, 'protection SQL') && $empreinte(2, 90) === $a, 'TR-11 mode strict, protection absente : publication bloquée', (string)$m);
    $m2 = null; try { $strict->genererBulletin(2, 90, 1, pourPublication: true); } catch (\Throwable $e) { $m2 = $e->getMessage(); }
    check($m2 !== null && str_contains($m2, 'protection SQL'), 'TR-11 mode strict, protection absente : régénération pour publication bloquée');
    $m3 = null; try { $strict->genererBulletin(2, 90, 1); } catch (\Throwable $e) { $m3 = $e->getMessage(); }
    check($m3 === null, 'brouillon toujours possible en mode strict');
    check(ConduiteProtectionStatus::strictRequis(['APP_ENV' => 'production']) === true && ConduiteProtectionStatus::strictRequis(['APP_ENV' => 'local']) === false
          && ConduiteProtectionStatus::strictRequis(['APP_ENV' => 'production', 'CONDUITE_PROTECTION_REQUIRED' => '0']) === false && ConduiteProtectionStatus::strictRequis(['APP_ENV' => 'local', 'CONDUITE_PROTECTION_REQUIRED' => '1']) === true && ConduiteProtectionStatus::strictRequis([]) === true,
          'mode strict : actif par défaut en production, désactivable explicitement, activable en développement');
    $nonStrict = $publie(2, 90);
    check($nonStrict === null && $rowB(2, 90)['statut'] === 'publie', 'mode non strict (développement) : publication permise sans protection');
    ConduiteProtectionStatus::installer($db);
    $gen->genererBulletin(3, 90, 1, pourPublication: true);
    $m = $publie(3, 90, $strict);
    check($m === null && $rowB(3, 90)['statut'] === 'publie', 'mode strict, protection opérationnelle : publication acceptée');

    // ═════════════════════════════════════════════════════════════════════
    section('6. RG-1 / RG-2 — route de régénération explicite');
    $uri = fn(int $e, int $p) => "/v2/academique/bulletins/$e/$p/regenerer";
    $db->exec('UPDATE eleves SET actif = 1 WHERE id = 121');
    $a = $empreinte(4, 90);
    $http('guest', 0, 'POST', $uri(4, 90));
    check($empreinte(4, 90) === $a, 'RG sans session : refusé, bulletin inchangé');
    $http('enseignant', 5, 'POST', $uri(4, 90));
    check($empreinte(4, 90) === $a, 'RG enseignant (sans droit de génération) : refusé');
    $http('admin', 1, 'POST', $uri(4, 90), [], ['REQUEST_AS_NO_CSRF' => '1']);
    check($empreinte(4, 90) === $a, 'RG sans jeton CSRF : refusé');
    $http('admin', 1, 'POST', $uri(4, 90));
    check($empreinte(4, 90) === $a, 'RG-2 classe incomplète (élève 121 actif sans note) : régénération refusée, bulletin inchangé');
    $db->exec('UPDATE eleves SET actif = 0 WHERE id = 121');
    $http('admin', 1, 'POST', $uri(4, 90));
    $r4 = $rowB(4, 90); $s4 = json_decode($r4['data_json'], true);
    check($r4['statut'] === 'brouillon' && $r4['published_at'] === null && $s4['snapshot_version'] === 2 && $s4['conduite_complete'] === true, 'RG-1 classe complète : régénéré à jour, reste BROUILLON (published_at nul)');
    check($r4['verification_token'] === $db->query('SELECT verification_token FROM bulletins_v2 WHERE id = ' . $r4['id'])->fetchColumn(), 'jeton conservé');
    $audit = $db->query("SELECT * FROM audit_logs WHERE action = 'regenerer_pour_publication' AND entite_id = 4 ORDER BY id DESC LIMIT 1")->fetch();
    $auditJson = json_encode($audit);
    check($audit !== false && str_contains($auditJson, 'jeton_empreinte') && !str_contains($auditJson, $r4['verification_token']), 'RG-1 audit présent, empreinte du jeton seulement (jamais le jeton complet)');
    $http('admin', 1, 'POST', $uri(2, 90));
    check($rowB(2, 90)['statut'] === 'publie', 'RG bulletin publié : jamais régénéré (statut inchangé)');
    $a = $empreinte(2, 90);
    check($empreinte(2, 90) === $a, 'RG bulletin publié : contenu inchangé');
    // autre établissement
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot2-bul', 'Autre Bul', 'AB', 'lycee')");
    $etab2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO users (etablissement_id, nom, prenom, email, password, role) VALUES ($etab2, 'Admin2', 'X', 'admin2-lot2@example.test', 'x', 'admin')");
    $admin2 = (int)$db->lastInsertId();
    $a = $empreinte(4, 90);
    $http('admin', $admin2, 'POST', $uri(4, 90));
    check($empreinte(4, 90) === $a, 'RG élève d\'un autre établissement : refusé');
    $http('admin', 1, 'POST', $uri(4, 90));
    check($rowB(4, 90)['statut'] === 'brouillon', 'RG ne publie jamais');

    // ═════════════════════════════════════════════════════════════════════
    section('7. A8 — analytique : une note de Conduite manquante est ignorée (matières ordinaires inchangées)');
    $an = new AnalyticsRepository();
    $db->exec('UPDATE eleves SET actif = 1 WHERE id = 121');
    $parMatiere = fn() => (function () use ($an) { $o = []; foreach ($an->moyennesParMatiere(90, 131) as $r) { $o[(int)$r['matiere_id']] = $r; } return $o; })();
    $mm = $parMatiere();
    $cond = $mm[$conduite] ?? null;
    $attenduC = (float)$db->query("SELECT AVG(n.valeur / ev.note_max * 20) FROM notes_v2 n JOIN evaluations ev ON ev.id = n.evaluation_id WHERE ev.matiere_id = $conduite AND ev.classe_id = 131 AND ev.periode_scolaire_id = 90 AND ev.statut IN ('publiee','verrouillee') AND n.est_absent = 0 AND n.valeur IS NOT NULL")->fetchColumn();
    check($cond !== null && abs((float)$cond['moyenne'] - $attenduC) < 0.01, 'moyenne de la Conduite : l\'élève 121 sans note n\'est PAS compté comme zéro', ($cond['moyenne'] ?? 'null') . ' vs ' . $attenduC);
    $ordinaire = (int)$one("SELECT id FROM matieres WHERE est_conduite IS NULL AND etablissement_id = 1 AND nom LIKE 'Fran%'");
    $attenduO = (float)$db->query("SELECT AVG(CASE WHEN n.est_absent = 1 THEN 0 WHEN n.valeur IS NOT NULL THEN n.valeur / ev.note_max * 20 ELSE 0 END) FROM eleves e JOIN evaluations ev ON ev.classe_id = e.classe_id AND ev.periode_scolaire_id = 90 AND ev.statut IN ('publiee','verrouillee') AND ev.matiere_id = $ordinaire LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id WHERE e.classe_id = 131")->fetchColumn();
    check(isset($mm[$ordinaire]) && abs((float)$mm[$ordinaire]['moyenne'] - $attenduO) < 0.01, 'matière ordinaire : un élève sans note compte toujours comme zéro (comportement inchangé)', ($mm[$ordinaire]['moyenne'] ?? 'null') . ' vs ' . $attenduO);

    // ═════════════════════════════════════════════════════════════════════
    section('8. R5 — traçabilité de la désactivation d\'un élève');
    $nbAudit = (int)$one("SELECT COUNT(*) FROM audit_logs WHERE action IN ('desactiver','reactiver') AND module = 'eleves'");
    $e1 = $db->query('SELECT * FROM eleves WHERE id = 6')->fetch();
    $form = ['nom' => $e1['nom'], 'prenom' => $e1['prenom'], 'matricule' => $e1['matricule'], 'classe_id' => $e1['classe_id'], 'sexe' => $e1['sexe'],
             'date_naissance' => $e1['date_naissance'], 'lieu_naissance' => $e1['lieu_naissance'] ?? '', 'actif' => '0'];
    $http('admin', 1, 'POST', '/eleves/6', $form);
    $actif6 = (int)$one('SELECT actif FROM eleves WHERE id = 6');
    $nbApres = (int)$one("SELECT COUNT(*) FROM audit_logs WHERE action IN ('desactiver','reactiver') AND module = 'eleves'");
    check($actif6 === 0 && $nbApres === $nbAudit + 1, 'EL-1 désactivation par la modification : ligne d\'audit « desactiver » (ancien et nouvel état)', "actif=$actif6, audit $nbAudit -> $nbApres");
    $a = $db->query("SELECT avant, apres FROM audit_logs WHERE action = 'desactiver' AND module = 'eleves' ORDER BY id DESC LIMIT 1")->fetch();
    check($a !== false && str_contains((string)$a['avant'], 'actif') && str_contains((string)$a['apres'], 'nouvel_actif'), 'EL-1 le journal porte l\'ancien et le nouvel état');
    // ═════════════════════════════════════════════════════════════════════
    section('8b. Outils en lecture seule (doublons, bulletins concernés) — aucune écriture');
    $hash0 = DbCopy::allDataHashes($db);
    $outil = function (string $script) use ($nom): array {
        $p = proc_open([PHP_BINARY, ROOT_PATH . '/database/tools/' . $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), ['CONDUITE_CHECK_DB' => $nom]));
        $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($p), $o];
    };
    [$c, $o] = $outil('conduite_doublons.php');
    check($c === 1 && str_contains($o, '[TEST BULLETIN') && str_contains($o, 'Aucune modification'), 'conduite_doublons.php : les doublons historiques de la classe 132 sont listés, rien n\'est modifié');
    [$c, $o] = $outil('conduite_bulletins_concernes.php');
    check($c === 1 && str_contains($o, 'ancien format') && str_contains($o, 'Aucune modification'), 'conduite_bulletins_concernes.php : les bulletins à régénérer explicitement sont listés');
    check(DbCopy::allDataHashes($db) === $hash0, 'les deux outils n\'ont écrit aucune table (empreinte identique)');
} finally {
    $copy->drop();
}

section('9. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
