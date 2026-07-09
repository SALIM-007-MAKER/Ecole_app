<?php
/**
 * Tests unitaires — RankingEngine V2
 *
 * Standalone, aucune dépendance DB.
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/RankingEngineTest.php
 */

declare(strict_types=1);

// ─── Autoloader manuel ───────────────────────────────────────────────────────

$base = dirname(__DIR__, 2);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'Core\\'                      => $base . '/core/',
        'App\Modules\Academique\\'    => $base . '/app/Modules/Academique/',
        'App\Services\\'              => $base . '/app/Services/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

// Stub minimal AuditService (évite require de Core\Database dans les tests)
if (!class_exists('App\Services\AuditService')) {
    class_alias(
        (new class {
            public static function log(...$a): void {}
            public static function logCreate(...$a): void {}
            public static function logUpdate(...$a): void {}
            public static function logDelete(...$a): void {}
        })::class,
        'App\Services\AuditService'
    );
}

use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\RankingEngine;
use App\Modules\Academique\ValueObjects\AverageValue;
use App\Modules\Academique\DTO\RankingResultDTO;

// ─── Helpers ─────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "\033[32m  ✓ $label\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $label\033[0m\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}

function assert_true(bool $val, string $label): void  { assert_eq(true,  $val, $label); }
function assert_false(bool $val, string $label): void { assert_eq(false, $val, $label); }

function assert_near(float $expected, float $actual, string $label, float $delta = 0.01): void
{
    global $passed, $failed;
    if (abs($expected - $actual) <= $delta) {
        echo "\033[32m  ✓ $label\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $label — expected ~$expected got $actual\033[0m\n";
        $failed++;
    }
}

function section(string $title): void
{
    echo "\n\033[33m── $title\033[0m\n";
}

// ─── Stub RankingRepository (jamais de DB) ───────────────────────────────────

class StubRankingRepository extends \App\Modules\Academique\Repositories\RankingRepository
{
    private array $rows;

    public function __construct(array $rows)
    {
        // Ne pas appeler parent::__construct() — pas de DB
        $this->rows = $rows;
    }

    public function notesParClasseEtPeriode(int $classeId, int $periodeId): array
    {
        return $this->rows;
    }

    public function notesParNiveauEtPeriode(string $niveau, int $periodeId): array
    {
        return $this->rows;
    }

    public function notesParMatiereEtPeriode(int $matiereId, int $periodeId, ?int $classeId = null): array
    {
        return $this->rows;
    }

    public function notesParClasseMultiPeriodes(int $classeId, array $periodeIds): array
    {
        return $this->rows;
    }
}

// ─── Helpers de construction de fixtures ─────────────────────────────────────

/**
 * Construit une ligne de note brute (simule une ligne retournée par le repo).
 */
function makeRow(
    int $eleveId, string $nom, string $prenom,
    int $matiereId, float $coeffMatiere,
    float $valeur, float $noteMax = 20.0, float $coeffEval = 1.0,
    bool $estAbsent = false, bool $estEliminatoire = false,
    ?float $seuilEliminatoire = null,
    ?string $classeNom = null,
    ?string $matricule = null,
): array {
    return [
        'eleve_id'           => $eleveId,
        'nom'                => $nom,
        'prenom'             => $prenom,
        'matricule'          => $matricule,
        'classe_id'          => 1,
        'classe_nom'         => $classeNom,
        'matiere_id'         => $matiereId,
        'matiere_nom'        => "Matière $matiereId",
        'coeff_matiere'      => $coeffMatiere,
        'evaluation_id'      => rand(1, 1000),
        'note_max'           => $noteMax,
        'coeff_eval'         => $coeffEval,
        'valeur'             => $estAbsent ? null : $valeur,
        'est_absent'         => $estAbsent ? 1 : 0,
        'est_eliminatoire'   => $estEliminatoire ? 1 : 0,
        'seuil_eliminatoire' => $seuilEliminatoire,
    ];
}

function makeEngine(array $rows): RankingEngine
{
    $calc = new AcademicCalculationService();
    $repo = new StubRankingRepository($rows);
    return new RankingEngine($calc, $repo);
}

// ─── Début des tests ─────────────────────────────────────────────────────────

echo "\n\033[1m=== RankingEngine Test Suite ===\033[0m\n";

// ─── Section 1 : Classe vide ─────────────────────────────────────────────────

section('1. Classe vide');

$engine = makeEngine([]);
$result = $engine->classementClasse(1, 1);

assert_true($result instanceof RankingResultDTO, 'retourne RankingResultDTO');
assert_true($result->isEmpty(),                  'isEmpty() true si aucune ligne');
assert_eq(0, $result->nbTotal,                   'nbTotal = 0');
assert_eq(0, $result->nbAdmis,                   'nbAdmis = 0');
assert_eq('classe', $result->type,               'type = classe');

// ─── Section 2 : Classement basique 4 élèves ─────────────────────────────────

section('2. Classement basique — 4 élèves, 1 matière');

$rows = [
    makeRow(1, 'Alpha', 'A', 10, 1.0, 18.0),
    makeRow(2, 'Beta',  'B', 10, 1.0, 12.0),
    makeRow(3, 'Gamma', 'G', 10, 1.0,  8.0),
    makeRow(4, 'Delta', 'D', 10, 1.0, 15.0),
];

$engine = makeEngine($rows);
$result = $engine->classementClasse(1, 1);

assert_eq(4, $result->nbTotal,        'nbTotal = 4');
assert_false($result->isEmpty(),      'isEmpty() false');

$rankings = $result->rankings;
assert_eq(1, $rankings[0]['rang'],    '1er rang = rang 1');
assert_eq(1, $rankings[0]['eleve_id'],'1er = eleve 1 (18)');
assert_eq(2, $rankings[1]['rang'],    '2e rang = rang 2');
assert_eq(4, $rankings[1]['eleve_id'],'2e = eleve 4 (15)');
assert_eq(3, $rankings[2]['rang'],    '3e rang = rang 3');
assert_eq(2, $rankings[2]['eleve_id'],'3e = eleve 2 (12)');
assert_eq(4, $rankings[3]['rang'],    '4e rang = rang 4');
assert_eq(3, $rankings[3]['eleve_id'],'4e = eleve 3 (8)');

// ─── Section 3 : Ex-aequo ────────────────────────────────────────────────────

section('3. Ex-aequo');

$rows = [
    makeRow(1, 'Alpha', 'A', 10, 1.0, 16.0),
    makeRow(2, 'Beta',  'B', 10, 1.0, 14.0),
    makeRow(3, 'Gamma', 'G', 10, 1.0, 14.0),
    makeRow(4, 'Delta', 'D', 10, 1.0, 10.0),
];

$result   = makeEngine($rows)->classementClasse(1, 1);
$rankings = $result->rankings;

assert_eq(1, $rankings[0]['rang'],   'Alpha : rang 1');
assert_eq(2, $rankings[1]['rang'],   'Beta : rang 2 (ex-aequo)');
assert_eq(2, $rankings[2]['rang'],   'Gamma : rang 2 (ex-aequo)');
assert_eq(4, $rankings[3]['rang'],   'Delta : rang 4 (saute le 3)');
assert_eq(4, $result->nbTotal,       'nbTotal = 4');

// ─── Section 4 : Triple ex-aequo en tête ─────────────────────────────────────

section('4. Triple ex-aequo en tête');

$rows = [
    makeRow(1, 'A1', 'X', 10, 1.0, 18.0),
    makeRow(2, 'A2', 'X', 10, 1.0, 18.0),
    makeRow(3, 'A3', 'X', 10, 1.0, 18.0),
    makeRow(4, 'B1', 'X', 10, 1.0, 12.0),
];

$rankings = makeEngine($rows)->classementClasse(1, 1)->rankings;

assert_eq(1, $rankings[0]['rang'],  'A1 rang 1');
assert_eq(1, $rankings[1]['rang'],  'A2 rang 1');
assert_eq(1, $rankings[2]['rang'],  'A3 rang 1');
assert_eq(4, $rankings[3]['rang'],  'B1 rang 4 (saute 2 et 3)');

// ─── Section 5 : Mentions ────────────────────────────────────────────────────

section('5. Mentions correctes');

$rows = [
    makeRow(1, 'Excellent', 'E', 10, 1.0, 17.0),   // TB
    makeRow(2, 'Bien',      'B', 10, 1.0, 14.0),   // B
    makeRow(3, 'Assez',     'A', 10, 1.0, 12.0),   // AB
    makeRow(4, 'Passable',  'P', 10, 1.0, 10.0),   // Passable
    makeRow(5, 'Insuffisant','I',10, 1.0,  7.0),   // Insuffisant
];

$rankings = makeEngine($rows)->classementClasse(1, 1)->rankings;

assert_eq('TB',   $rankings[0]['mention_code'], 'Mention TB (17)');
assert_eq('B',    $rankings[1]['mention_code'], 'Mention B (14)');
assert_eq('AB',   $rankings[2]['mention_code'], 'Mention AB (12)');
assert_eq('P',    $rankings[3]['mention_code'], 'Mention Passable (10)');
assert_eq('INS',  $rankings[4]['mention_code'], 'Mention Insuffisant (7)');

assert_true($rankings[0]['admis'],  'Admis si TB');
assert_false($rankings[4]['admis'], 'Non admis si INS');

// ─── Section 6 : Statistiques de classe ──────────────────────────────────────

section('6. Statistiques');

$rows = [
    makeRow(1, 'A', 'a', 10, 1.0, 16.0),
    makeRow(2, 'B', 'b', 10, 1.0, 12.0),
    makeRow(3, 'C', 'c', 10, 1.0,  8.0),
    makeRow(4, 'D', 'd', 10, 1.0, 14.0),
];

$result = makeEngine($rows)->classementClasse(1, 1);
$stats  = $result->statistiques;

assert_eq(4,     $stats['count'],  'stats count = 4');
assert_near(12.5, $stats['moyenne'], 'stats moyenne = 12.5');
assert_eq(8.0,   $stats['min'],    'stats min = 8');
assert_eq(16.0,  $stats['max'],    'stats max = 16');
assert_eq(3,     $stats['admis'],  'admis = 3 (≥10)');
assert_eq(1,     $stats['echoues'],'échoués = 1 (<10)');
assert_near(75.0, $stats['taux_reussite'], 'taux réussite = 75%');

assert_eq(3, $result->nbAdmis,  'DTO nbAdmis = 3');
assert_near(75.0, $result->getTauxReussite(), 'DTO getTauxReussite = 75%');

// ─── Section 7 : Multi-matières, coefficients ────────────────────────────────

section('7. Multi-matières avec coefficients différents');

// Elève 1 : maths(coeff 3)=18, français(coeff 2)=12 => moy pondérée
// Maths ramené sur 20 = 18 × 3 = 54, français = 12 × 2 = 24, total coeff = 5 → moy = 78/5 = 15.6
// Elève 2 : maths=10, français=16 → 10×3+16×2 = 62/5 = 12.4

$rows = [
    makeRow(1, 'Fort',   'F', 10, 3.0, 18.0),
    makeRow(1, 'Fort',   'F', 11, 2.0, 12.0),
    makeRow(2, 'Moyen',  'M', 10, 3.0, 10.0),
    makeRow(2, 'Moyen',  'M', 11, 2.0, 16.0),
];

$result   = makeEngine($rows)->classementClasse(1, 1);
$rankings = $result->rankings;

assert_eq(2, count($rankings),       '2 élèves classés');
assert_eq(1, $rankings[0]['eleve_id'],'Elève 1 premier (15.6)');
assert_eq(2, $rankings[1]['eleve_id'],'Elève 2 second (12.4)');
assert_near(15.6, $rankings[0]['moyenne']->getValue(), 'Moyenne élève 1 = 15.6');
assert_near(12.4, $rankings[1]['moyenne']->getValue(), 'Moyenne élève 2 = 12.4');

// ─── Section 8 : Absence (vaut zéro par défaut) ──────────────────────────────

section('8. Gestion des absences');

$rows = [
    makeRow(1, 'Present', 'P', 10, 1.0, 15.0, 20.0, 1.0, false),
    makeRow(2, 'Absent',  'A', 10, 1.0,  0.0, 20.0, 1.0, true),  // absent → 0
];

$result   = makeEngine($rows)->classementClasse(1, 1);
$rankings = $result->rankings;

assert_eq(1, $rankings[0]['eleve_id'],   'Présent en premier');
assert_eq(2, $rankings[1]['eleve_id'],   'Absent en dernier');
assert_near(15.0, $rankings[0]['moyenne']->getValue(), 'Présent = 15');
assert_near(0.0,  $rankings[1]['moyenne']->getValue(), 'Absent = 0 (vaut zéro)');

// ─── Section 9 : Option exclure_sans_notes ───────────────────────────────────

section('9. Exclusion élèves sans notes');

// Elève 3 n'a aucune note (valeur=null, non absent mais non saisi = note absente)
$rows = [
    makeRow(1, 'Alpha', 'A', 10, 1.0, 16.0),
    makeRow(2, 'Beta',  'B', 10, 1.0, 12.0),
    // Elève 3 : ligne avec valeur null et non absent = non remis → vaut 0
    // Pour exclure, il faut une absence totale de ligne ou isEmpty
];

// Avec exclure_sans_notes sans données vides → pas d'exclusion
$result = makeEngine($rows)->classementClasse(1, 1, ['exclure_sans_notes' => true]);
assert_eq(2, $result->nbTotal, 'Sans élèves vides : 2 restants');

// Classe vide avec exclusion
$emptyResult = makeEngine([])->classementClasse(1, 1, ['exclure_sans_notes' => true]);
assert_eq(0, $emptyResult->nbTotal, 'Classe vide reste vide');

// ─── Section 10 : Type niveau ────────────────────────────────────────────────

section('10. Classement par niveau');

$rows = [
    makeRow(1, 'A3e', 'x', 10, 1.0, 14.0, 20.0, 1.0, false, false, null, '3e A'),
    makeRow(2, 'B3e', 'x', 10, 1.0, 18.0, 20.0, 1.0, false, false, null, '3e B'),
];

$result = makeEngine($rows)->classementNiveau('3e', 1);
assert_eq('niveau', $result->type, 'type = niveau');
assert_eq(2, $result->nbTotal,     'nbTotal = 2 élèves de niveau');
assert_eq(2, $result->rankings[0]['eleve_id'], 'Elève 2 premier (18)');
assert_eq('3e B', $result->rankings[0]['classe_nom'], 'classe_nom renseigné');

// ─── Section 11 : Type matière ───────────────────────────────────────────────

section('11. Classement par matière');

$rows = [
    makeRow(1, 'AlphaM', 'A', 10, 3.0, 12.0),
    makeRow(2, 'BetaM',  'B', 10, 3.0, 19.0),
    makeRow(3, 'GammaM', 'G', 10, 3.0,  6.0),
];

$result = makeEngine($rows)->classementMatiere(10, 1);
assert_eq('matiere', $result->type, 'type = matiere');
assert_eq(3, $result->nbTotal,      'nbTotal = 3');
assert_eq(2, $result->rankings[0]['eleve_id'], 'Beta premier (19)');
assert_eq(1, $result->rankings[1]['eleve_id'], 'Alpha second (12)');
assert_eq(3, $result->rankings[2]['eleve_id'], 'Gamma troisième (6)');

// ─── Section 12 : toArray() ──────────────────────────────────────────────────

section('12. DTO toArray()');

$rows = [
    makeRow(1, 'T', 'T', 10, 1.0, 14.0),
    makeRow(2, 'U', 'U', 10, 1.0,  8.0),
];

$result = makeEngine($rows)->classementClasse(1, 1);
$arr    = $result->toArray();

assert_true(isset($arr['type']),         'toArray a type');
assert_true(isset($arr['rankings']),     'toArray a rankings');
assert_true(isset($arr['statistiques']), 'toArray a statistiques');
assert_true(isset($arr['taux_reussite']),'toArray a taux_reussite');
assert_true(is_float($arr['rankings'][0]['moyenne']), 'moyenne sérialisée en float');

// ─── Section 13 : computeFromNoteRows directement ────────────────────────────

section('13. computeFromNoteRows() — accès direct pour tests');

$calc   = new AcademicCalculationService();
$repo   = new StubRankingRepository([]);
$engine = new RankingEngine($calc, $repo);

$rows = [
    makeRow(5, 'Direct', 'D', 20, 1.0, 11.0),
    makeRow(6, 'Test',   'T', 20, 1.0, 17.0),
];

$result = $engine->computeFromNoteRows($rows, 'classe', 99, 7, null, null);
assert_eq(2, $result->nbTotal,          'computeFromNoteRows : 2 élèves');
assert_eq(6, $result->rankings[0]['eleve_id'], '1er = élève 6 (17)');
assert_eq(5, $result->rankings[1]['eleve_id'], '2e = élève 5 (11)');

// ─── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) {
    exit(1);
}
