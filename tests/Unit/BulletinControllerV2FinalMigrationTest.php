<?php
/**
 * Tests — migration finale de App\Controllers\BulletinController vers le
 * moteur Académique V2 (BulletinEngineFactory / BulletinGenerator /
 * RankingEngine). Les 4 actions (index/classe/classement/eleve) sont
 * couvertes, ainsi que le scénario complet demandé :
 *   1. Sélection classe -> 2. Sélection période -> 3. Génération bulletin ->
 *   4. Affichage -> 5. Impression PDF -> 6. Vérification QR code.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinControllerV2FinalMigrationTest.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT_PATH', dirname(__DIR__, 2));
define('BASE_URL', '/ecole_app');

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'             => ROOT_PATH . '/core/',
        'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
        'App\\Models\\'      => ROOT_PATH . '/app/Models/',
        'App\\Services\\'    => ROOT_PATH . '/app/Services/',
        'App\\Modules\\'     => ROOT_PATH . '/app/Modules/',
        'App\\Shared\\'      => ROOT_PATH . '/app/Shared/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

use App\Controllers\BulletinController;
use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\Repositories\BulletinRepository;
use Core\Database;
use Core\View;

$passed = 0;
$failed = 0;

function assert_true(bool $val, string $label): void
{
    global $passed, $failed;
    if ($val) { echo "\033[32m  ✓ $label\033[0m\n"; $passed++; }
    else      { echo "\033[31m  ✗ $label\033[0m\n"; $failed++; }
}

function section(string $title): void { echo "\n\033[33m── $title\033[0m\n"; }

$CLASSE_ID  = 131;
$PERIODE_ID = 90;

// ─── 1. Statique — plus aucune trace du moteur V1 ────────────────────────────

section('BulletinController — dépendances V1 supprimées');

$source = file_get_contents(ROOT_PATH . '/app/Controllers/BulletinController.php');
// 'NoteModel::' volontairement exclu : un commentaire du contrôleur cite
// historiquement NoteModel::getClassementClasse() pour expliquer la forme
// de donnée reproduite par buildClassementLegacyShape() — ce n'est pas un
// usage réel, 'use App\Models\NoteModel;' ci-dessous suffit à couvrir toute
// dépendance effective à la classe.
foreach (['$this->noteModel', '$this->periodeModel', 'new NoteModel(', 'new PeriodeModel(', 'use App\\Models\\NoteModel;', 'use App\\Models\\PeriodeModel;'] as $forbidden) {
    assert_true(!str_contains($source, $forbidden), "Aucun usage réel de : $forbidden (mentions en commentaire tolérées)");
}
foreach (['FROM `notes`', 'FROM notes ', 'FROM `controles`', 'FROM controles ', 'FROM `periodes`', 'FROM periodes '] as $forbidden) {
    assert_true(!preg_match('/' . preg_quote($forbidden, '/') . '/i', $source), "Aucune requête directe : $forbidden");
}
foreach (['RankingEngine', 'BulletinEngineFactory', 'RankingRepository', 'EvaluationRepository'] as $needle) {
    assert_true(str_contains($source, $needle), "Utilise $needle");
}
foreach (['function index()', 'function classe()', 'function classement()', 'function eleve(string $id)'] as $needle) {
    assert_true(str_contains($source, $needle), "Action présente : $needle");
}

$indexViewSrc = file_get_contents(ROOT_PATH . '/app/Views/bulletins/index.php');
assert_true(!str_contains($indexViewSrc, 'NoteModel::MENTIONS'), "bulletins/index.php n'utilise plus NoteModel::MENTIONS");
assert_true(str_contains($indexViewSrc, 'MentionValue::thresholds'), "bulletins/index.php utilise MentionValue::thresholds() (V2)");

// ─── 2. Instanciation réelle + accès aux aides privées (réflexion) ───────────

section('BulletinController — instanciation réelle');

$controller = new BulletinController();
assert_true($controller instanceof BulletinController, 'Contrôleur instancié sans erreur (constructeur V2 fonctionnel)');

$ref = new ReflectionClass($controller);

function callPrivate(object $obj, ReflectionClass $ref, string $method, array $args = []): mixed
{
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($obj, $args);
}

// ─── 3. Scénario — 1. Sélection classe / 2. Sélection période ────────────────

section('Scénario 1-2 : sélection classe + période');

$classeModelRef = $ref->getProperty('classeModel');
$classeModelRef->setAccessible(true);
$classe = $classeModelRef->getValue($controller)->findById($CLASSE_ID);
assert_true($classe !== false, "Classe $CLASSE_ID trouvée (sélection classe)");

$periode = callPrivate($controller, $ref, 'trouverPeriode', [$PERIODE_ID]);
assert_true($periode !== null && (int)$periode->id === $PERIODE_ID, "Période $PERIODE_ID résolue via periodes_scolaires (V2)");

// ─── 4. Données classe() / classement() (RankingEngine réel) ─────────────────

section('classe() / classement() — données réelles via RankingEngine');

$eleveInfosMethod = $ref->getMethod('buildEleveInfos');
$eleveInfosMethod->setAccessible(true);
$rankingEngineProp = $ref->getProperty('rankingEngine');
$rankingEngineProp->setAccessible(true);
$rankingEngine = $rankingEngineProp->getValue($controller);

$classementResult = $rankingEngine->classementClasse($CLASSE_ID, $PERIODE_ID);
assert_true(!$classementResult->isEmpty(), 'classementClasse() retourne des résultats');

$eleveInfos = callPrivate($controller, $ref, 'buildEleveInfos', [$classementResult]);
assert_true(count($eleveInfos) === $classementResult->nbTotal, 'buildEleveInfos() transpose tous les élèves du classement');
$first = reset($eleveInfos);
foreach (['nom', 'prenom', 'moyenne_generale', 'rang', 'mention'] as $key) {
    assert_true(array_key_exists($key, $first), "eleveInfos[...] contient '$key' (forme attendue par bulletins/classe.php)");
}

$matieres = callPrivate($controller, $ref, 'matieresDeLaClasse', [$CLASSE_ID, $PERIODE_ID]);
assert_true(count($matieres) > 0, 'matieresDeLaClasse() retourne des matières (dérivées de notes_v2/evaluations)');

$classementLegacy = callPrivate($controller, $ref, 'buildClassementLegacyShape', [$classementResult, $CLASSE_ID]);
assert_true(count($classementLegacy) === $classementResult->nbTotal, 'buildClassementLegacyShape() transpose tous les élèves');
assert_true(is_object($classementLegacy[0]) && isset($classementLegacy[0]->rang), 'classement[0] a la forme objet attendue par bulletins/classement.php');
// Trié par rang croissant, comme ORDER BY mg.rang ASC en V1
$rangs = array_map(fn($e) => $e->rang, $classementLegacy);
$sorted = $rangs;
sort($sorted);
assert_true($rangs === $sorted, 'Le classement est trié par rang croissant (même ordre que l\'ancien ORDER BY V1)');

// ─── 5. Affichage — rendu réel des 3 vues (index/classe/classement) ──────────

section('4. Affichage — rendu réel des vues');

$view = new View();
function renderCapture(View $view, string $name, array $data): string {
    ob_start();
    try { $view->render($name, $data, 'none'); }
    catch (\Throwable $e) { ob_end_clean(); return 'EXCEPTION: ' . $e->getMessage(); }
    return ob_get_clean();
}

/**
 * Détecte une vraie erreur PHP dans du HTML rendu, sans faux positif sur les
 * classes CSS Tailwind du projet (ex: "btn-warning", "text-warning").
 */
function hasPhpError(string $html): bool {
    return (bool)preg_match('/\bWarning:\s|\bFatal error:\s|\bNotice:\s|\bDeprecated:\s|^EXCEPTION:/m', $html);
}

$evalRepoProp = $ref->getProperty('evalRepo');
$evalRepoProp->setAccessible(true);
$evalRepo = $evalRepoProp->getValue($controller);

$htmlIndex = renderCapture($view, 'bulletins/index', [
    'title' => 'Bulletins', 'classes' => $classeModelRef->getValue($controller)->findAll('niveau'),
    'periodes' => $evalRepo->listPeriodesForSelect(),
]);
assert_true(!hasPhpError($htmlIndex), 'bulletins/index.php rendu sans erreur PHP');
assert_true(str_contains($htmlIndex, 'Très Bien'), 'bulletins/index.php affiche la légende des mentions (V2)');

$htmlClasse = renderCapture($view, 'bulletins/classe', [
    'classe' => $classe, 'periode' => $periode, 'matieres' => array_map(fn($id, $d) => (object)['id'=>$id,'nom'=>$d['nom'],'coefficient'=>$d['coefficient']], array_keys($matieres), $matieres),
    'grille' => (function() use ($matieres, $rankingEngine, $CLASSE_ID, $PERIODE_ID) {
        $g = [];
        foreach (array_keys($matieres) as $mid) {
            foreach ($rankingEngine->classementMatiere($mid, $PERIODE_ID, $CLASSE_ID)->rankings as $e) {
                $g[(int)$e['eleve_id']][$mid] = $e['moyenne']->isEmpty() ? null : $e['moyenne']->getValue();
            }
        }
        return $g;
    })(),
    'eleveInfos' => $eleveInfos, 'classes' => $classeModelRef->getValue($controller)->findAll('niveau'),
    'periodes' => $evalRepo->listPeriodesForSelect(), 'filters' => ['classeId' => $CLASSE_ID, 'periodeId' => $PERIODE_ID],
]);
assert_true(!hasPhpError($htmlClasse), 'bulletins/classe.php rendu sans erreur PHP');
assert_true(str_contains($htmlClasse, 'Boukerrou') || str_contains($htmlClasse, 'Benali'), 'bulletins/classe.php affiche des élèves réels de la classe');

$htmlClassement = renderCapture($view, 'bulletins/classement', [
    'classe' => $classe, 'periode' => $periode, 'classement' => $classementLegacy,
    'classes' => $classeModelRef->getValue($controller)->findAll('niveau'), 'periodes' => $evalRepo->listPeriodesForSelect(),
    'filters' => ['classeId' => $CLASSE_ID, 'periodeId' => $PERIODE_ID],
]);
assert_true(!hasPhpError($htmlClassement), 'bulletins/classement.php rendu sans erreur PHP');
assert_true(str_contains($htmlClassement, '🥇'), 'bulletins/classement.php affiche le podium');

// ─── 6. Scénario complet 3-6 : génération, affichage bulletin, PDF, QR ───────

section('3-6. Génération -> affichage -> impression PDF -> vérification QR');

$pdo = Database::getInstance()->getConnection();
$testEleveId = null;

try {
    $pdo->exec(
        "INSERT INTO eleves (matricule, nom, prenom, date_naissance, sexe, classe_id, actif, etablissement_id)
         VALUES ('TEST-BULL-FINAL', 'FinalMigration', 'Eleve', '2010-01-01', 'M', $CLASSE_ID, 1, 1)"
    );
    $testEleveId = (int)$pdo->lastInsertId();
    assert_true($testEleveId > 0, "Élève de test créé (id=$testEleveId)");

    // Une note publiée pour que le bulletin ne soit pas vide.
    $evalRow = $pdo->query("SELECT id FROM evaluations WHERE classe_id=$CLASSE_ID AND periode_scolaire_id=$PERIODE_ID AND statut IN ('publiee','verrouillee') LIMIT 1")->fetch(PDO::FETCH_OBJ);
    $pdo->prepare("INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, est_absent, statut, created_by) VALUES (?, ?, 13, 0, 'publiee', 1)")
        ->execute([$evalRow->id, $testEleveId]);

    // ── 3. Génération bulletin (persistée, bulletins_v2) ──
    $bulletin = BulletinEngineFactory::make(1)->genererBulletin($testEleveId, $PERIODE_ID, 1);
    assert_true($bulletin->eleveId === $testEleveId, 'genererBulletin() retourne le bulletin du bon élève');
    assert_true($bulletin->statut === 'brouillon', "Bulletin généré en statut 'brouillon'");

    $persisted = $pdo->query("SELECT * FROM bulletins_v2 WHERE eleve_id=$testEleveId AND periode_id=$PERIODE_ID")->fetch(PDO::FETCH_OBJ);
    assert_true($persisted !== false, 'Le bulletin est bien persisté dans bulletins_v2 (contrairement à V1 qui ne persistait jamais)');

    // ── 4. Affichage (bulletins/eleve.php, comme BulletinController::eleve()) ──
    $htmlEleve = renderCapture($view, 'bulletins/eleve', [
        'eleve' => (object)['id'=>$testEleveId,'nom'=>'FinalMigration','prenom'=>'Eleve','matricule'=>'TEST-BULL-FINAL','sexe'=>'M','photo'=>null],
        'classe' => $classe, 'periode' => $periode, 'bulletin' => $bulletin, 'semestresDisponibles' => $evalRepo->listPeriodesForSelect(),
    ]);
    assert_true(!hasPhpError($htmlEleve), 'bulletins/eleve.php rendu sans erreur PHP pour le bulletin généré');
    assert_true(str_contains($htmlEleve, 'FinalMigration'), "L'affichage montre bien l'élève du bulletin généré");

    // ── 5. Impression PDF (BulletinGenerator::exportHtml) ──
    $pdfHtml = BulletinEngineFactory::make(1)->exportHtml($bulletin);
    assert_true(str_contains($pdfHtml, '<!DOCTYPE html>'), 'exportHtml() produit un document HTML complet (base impression navigateur)');
    assert_true(str_contains($pdfHtml, 'BULLETIN DE NOTES'), "Le PDF contient l'en-tête du bulletin");
    assert_true(str_contains($pdfHtml, number_format($bulletin->moyennePeriode, 2)), 'Le PDF affiche la moyenne correcte');
    assert_true(str_contains($pdfHtml, $bulletin->verificationToken), 'Le PDF contient le token de vérification QR');

    // ── 6. Vérification QR code ──
    assert_true($bulletin->verificationToken !== '', 'Un token de vérification a été généré');
    assert_true(str_contains($bulletin->qrCodeUrl, $bulletin->verificationToken), "L'URL du QR code contient le token");

    $repo = new BulletinRepository();
    $foundByToken = $repo->findByToken($bulletin->verificationToken);
    assert_true($foundByToken !== null, 'Le token du QR permet de retrouver le bulletin en base (scan QR -> vérification)');
    assert_true($foundByToken !== null && (int)$foundByToken['eleve_id'] === $testEleveId, 'Le bulletin retrouvé via QR correspond au bon élève');

    // Re-génération -> même token (déterministe, cf. generateVerificationToken())
    $bulletin2 = BulletinEngineFactory::make(1)->genererBulletin($testEleveId, $PERIODE_ID, 1);
    assert_true($bulletin2->verificationToken === $bulletin->verificationToken, 'Le token QR est stable entre deux générations du même bulletin (eleve+periode)');

} finally {
    section('Nettoyage');
    if ($testEleveId !== null) {
        $pdo->prepare("DELETE FROM bulletins_v2 WHERE eleve_id = ?")->execute([$testEleveId]);
        $pdo->prepare("DELETE FROM notes_v2 WHERE eleve_id = ?")->execute([$testEleveId]);
        $pdo->prepare("DELETE FROM eleves WHERE id = ?")->execute([$testEleveId]);
        $left = $pdo->query("SELECT COUNT(*) FROM eleves WHERE id=$testEleveId")->fetchColumn();
        assert_true((int)$left === 0, 'Données de test nettoyées — base restaurée');
    }
}

// ─── Résumé ────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) exit(1);
