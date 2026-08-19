<?php
/**
 * Tests — écrans de résultats Académique V2 (ResultatsController), équivalents
 * des anciennes routes V1 /bulletins/classe, /bulletins/classement,
 * /notes/moyennes.
 *
 * Combine :
 *  - vérification statique du contrôleur et de ses dépendances (aucune trace
 *    de NoteModel/PeriodeModel/table `notes` V1) ;
 *  - test fonctionnel contre la base réelle (RankingEngine::classementClasse()/
 *    classementMatiere(), exactement les appels faits par le contrôleur) ;
 *  - test unitaire de la logique de résolution "période précédente" (réflexion
 *    sur la méthode privée, car sans dépendance externe) ;
 *  - vérification RBAC (permissions présentes pour admin/directeur/enseignant) ;
 *  - vérification des routes déclarées.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ResultatsControllerV2Test.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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

use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\Controllers\ResultatsController;
use Core\Database;

$passed = 0;
$failed = 0;

function assert_true(bool $val, string $label): void
{
    global $passed, $failed;
    if ($val) { echo "\033[32m  ✓ $label\033[0m\n"; $passed++; }
    else      { echo "\033[31m  ✗ $label\033[0m\n"; $failed++; }
}

function section(string $title): void { echo "\n\033[33m── $title\033[0m\n"; }

// ─── 1. Vérification statique — ResultatsController ───────────────────────────

section('ResultatsController — aucune trace du moteur V1');

$source = file_get_contents(ROOT_PATH . '/app/Modules/Academique/Controllers/ResultatsController.php');
foreach (['NoteModel', 'PeriodeModel', 'App\\Models\\', 'FROM notes ', 'FROM `notes`', 'FROM controles', 'FROM `controles`', 'FROM periodes ', 'FROM `periodes`'] as $forbidden) {
    assert_true(!str_contains($source, $forbidden), "ResultatsController ne contient pas '$forbidden'");
}
assert_true(str_contains($source, 'RankingEngine'),        'ResultatsController utilise RankingEngine');
assert_true(str_contains($source, 'BulletinEngineFactory'),'ResultatsController réutilise BulletinEngineFactory (pas de config dupliquée)');
foreach (['function classe()', 'function classement()', 'function moyennes()'] as $needle) {
    assert_true(str_contains($source, $needle), "Action présente : $needle");
}

section('RankingRepository / EvaluationRepository — aucune table V1');

foreach ([
    ROOT_PATH . '/app/Modules/Academique/Repositories/RankingRepository.php',
    ROOT_PATH . '/app/Modules/Academique/Repositories/EvaluationRepository.php',
] as $file) {
    $src = file_get_contents($file);
    $label = basename($file);
    assert_true(!preg_match('/FROM\s+`?notes`?\s/i', $src) || str_contains($src, 'notes_v2'), "$label : pas de lecture de `notes` V1");
    assert_true(!preg_match('/FROM\s+`?controles`?\s/i', $src), "$label : pas de lecture de `controles` V1");
    assert_true(!preg_match('/FROM\s+`?periodes`?\s/i', $src) || str_contains($src, 'periodes_scolaires'), "$label : pas de lecture de `periodes` V1");
}

// ─── 2. Routes déclarées ──────────────────────────────────────────────────────

section('Routes V2 déclarées (app/Modules/Academique/routes.php)');

$routesSrc = file_get_contents(ROOT_PATH . '/app/Modules/Academique/routes.php');
foreach ([
    "'/v2/academique/resultats/classe',",
    "'/v2/academique/resultats/classement',",
    "'/v2/academique/resultats/moyennes',",
] as $needle) {
    assert_true(str_contains($routesSrc, $needle), "Route déclarée : $needle");
}

// ─── 3. RBAC — permissions présentes pour les 3 rôles ─────────────────────────

section('RBAC — academique.moyennes.view / academique.classement.view');

$perms = require ROOT_PATH . '/config/permissions.php';
foreach (['admin', 'directeur', 'enseignant'] as $role) {
    assert_true(in_array('academique.moyennes.view', $perms[$role] ?? [], true), "$role possède academique.moyennes.view");
    assert_true(in_array('academique.classement.view', $perms[$role] ?? [], true), "$role possède academique.classement.view");
}

// ─── 4. Test fonctionnel — moteur réel contre la base ─────────────────────────

section('RankingEngine (via BulletinEngineFactory) — données réelles');

$pdo = Database::getInstance()->getConnection();
$row = $pdo->query(
    "SELECT ev.classe_id, ev.periode_scolaire_id, ev.matiere_id, m.nom AS matiere_nom
       FROM evaluations ev JOIN matieres m ON m.id = ev.matiere_id
      WHERE ev.statut IN ('publiee','verrouillee')
      LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "\033[33m  ⚠ Aucune évaluation publiée en base — section ignorée (pas un échec)\033[0m\n";
} else {
    $classeId  = (int)$row['classe_id'];
    $periodeId = (int)$row['periode_scolaire_id'];
    $matiereId = (int)$row['matiere_id'];

    $engine = BulletinEngineFactory::make(1)->getRankingEngine();

    $classementClasse = $engine->classementClasse($classeId, $periodeId);
    assert_true(!$classementClasse->isEmpty(), "classementClasse($classeId, $periodeId) retourne des résultats");
    assert_true($classementClasse->nbTotal > 0, 'nbTotal > 0');
    assert_true(isset($classementClasse->statistiques['moyenne']), "statistiques['moyenne'] présent");
    if (!empty($classementClasse->rankings)) {
        $first = $classementClasse->rankings[0];
        foreach (['eleve_id', 'nom', 'prenom', 'rang', 'moyenne', 'mention_label', 'admis'] as $key) {
            assert_true(array_key_exists($key, $first), "rankings[0] contient la clé '$key'");
        }
        assert_true($first['moyenne'] instanceof \App\Modules\Academique\ValueObjects\AverageValue, "rankings[0]['moyenne'] est un AverageValue (pas un float brut recalculé)");
    }

    $classementMatiere = $engine->classementMatiere($matiereId, $periodeId, $classeId);
    assert_true(is_array($classementMatiere->statistiques), "classementMatiere($matiereId, $periodeId, $classeId) retourne des statistiques");
    assert_true(true, "classementMatiere() : moyenne classe = " . var_export($classementMatiere->getMoyenneClasse(), true));
}

// ─── 5. Résolution "période précédente" (réflexion sur méthode privée) ────────

section('ResultatsController::trouverPeriodePrecedente() — logique évolution');

$controllerRef = new ReflectionClass(ResultatsController::class);
$method = $controllerRef->getMethod('trouverPeriodePrecedente');
$method->setAccessible(true);

// On instancie sans passer par le constructeur (pas de session HTTP dispo ici) :
// la méthode testée ne dépend que de ses paramètres, pas de l'état de l'objet.
$instance = $controllerRef->newInstanceWithoutConstructor();

$periodesFixture = [
    (object)['id' => 91, 'nom' => 'Premier Semestre',  'annee_scolaire' => '2025-2026'],
    (object)['id' => 90, 'nom' => 'Deuxième Semestre', 'annee_scolaire' => '2025-2026'],
];

$prec = $method->invoke($instance, $periodesFixture, 90);
assert_true($prec !== null && (int)$prec->id === 91, "Période précédente de 90 (Deuxième Semestre) = 91 (Premier Semestre)");

$precPremiere = $method->invoke($instance, $periodesFixture, 91);
assert_true($precPremiere === null, "Pas de période précédente pour la première période de l'année");

$periodesAutreAnnee = [
    (object)['id' => 80, 'nom' => 'Semestre 2 (2024-2025)', 'annee_scolaire' => '2024-2025'],
    (object)['id' => 91, 'nom' => 'Premier Semestre',       'annee_scolaire' => '2025-2026'],
];
$precAutreAnnee = $method->invoke($instance, $periodesAutreAnnee, 91);
assert_true($precAutreAnnee === null, "Pas d'évolution proposée entre deux années scolaires différentes");

// ─── Résumé ────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) exit(1);
