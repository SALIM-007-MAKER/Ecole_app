<?php

/**
 * Tests unitaires — AcademicCalculationService
 *
 * Exécution : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AcademicCalculationServiceTest.php
 *
 * Tous les tests sont autonomes (pas de DB, pas de framework).
 */

declare(strict_types=1);

// ── Bootstrap minimal ──────────────────────────────────────────────────────────

$root = dirname(__DIR__, 2);

// Autoloader manuel pour ce test standalone
spl_autoload_register(function (string $class) use ($root): void {
    $map = [
        'App\\Modules\\Academique\\ValueObjects\\AverageValue'         => 'app/Modules/Academique/ValueObjects/AverageValue.php',
        'App\\Modules\\Academique\\ValueObjects\\BaremeValue'           => 'app/Modules/Academique/ValueObjects/BaremeValue.php',
        'App\\Modules\\Academique\\ValueObjects\\CoefficientValue'      => 'app/Modules/Academique/ValueObjects/CoefficientValue.php',
        'App\\Modules\\Academique\\ValueObjects\\GradeValue'            => 'app/Modules/Academique/ValueObjects/GradeValue.php',
        'App\\Modules\\Academique\\ValueObjects\\MentionValue'          => 'app/Modules/Academique/ValueObjects/MentionValue.php',
        'App\\Modules\\Academique\\ValueObjects\\NoteValue'             => 'app/Modules/Academique/ValueObjects/NoteValue.php',
        'App\\Modules\\Academique\\Services\\AcademicCalculationService'=> 'app/Modules/Academique/Services/AcademicCalculationService.php',
    ];
    if (isset($map[$class])) {
        require_once $root . '/' . $map[$class];
    }
});

use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\ValueObjects\AverageValue;
use App\Modules\Academique\ValueObjects\GradeValue;
use App\Modules\Academique\ValueObjects\MentionValue;

// ── Mini runner ────────────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;
$errors = [];

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $pass, $fail, $errors;
    if ($expected == $actual) {
        $pass++;
        echo "\033[32m  ✓\033[0m {$label}\n";
    } else {
        $fail++;
        $errors[] = $label;
        echo "\033[31m  ✗\033[0m {$label}\n";
        echo "    expected=" . json_encode($expected) . " got=" . json_encode($actual) . "\n";
    }
}

function assert_true(bool $condition, string $label): void
{
    assert_eq(true, $condition, $label);
}

function assert_false(bool $condition, string $label): void
{
    assert_eq(false, $condition, $label);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — noteRameneeSur20
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 1 : noteRameneeSur20 ──\n";

$svc = new AcademicCalculationService();

assert_eq(15.0,  $svc->noteRameneeSur20(15.0, 20.0),  '15/20 → 15.00');
assert_eq(10.0,  $svc->noteRameneeSur20(10.0, 20.0),  '10/20 → 10.00');
assert_eq(10.0,  $svc->noteRameneeSur20(5.0,  10.0),  '5/10 → 10.00');
assert_eq(16.0,  $svc->noteRameneeSur20(8.0,  10.0),  '8/10 → 16.00');
assert_eq(7.5,   $svc->noteRameneeSur20(15.0, 40.0),  '15/40 → 7.50');
assert_eq(10.0,  $svc->noteRameneeSur20(50.0, 100.0), '50/100 → 10.00');
assert_eq(0.0,   $svc->noteRameneeSur20(0.0,  20.0),  '0/20 → 0.00');
assert_eq(20.0,  $svc->noteRameneeSur20(20.0, 20.0),  '20/20 → 20.00');
assert_eq(0.0,   $svc->noteRameneeSur20(1.0,  0.0),   'noteMax=0 → 0.00');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — notePonderee
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 2 : notePonderee ──\n";

assert_eq(30.0,  $svc->notePonderee(15.0, 20.0, 2.0), '15/20 × coeff2 → 30.00');
assert_eq(10.0,  $svc->notePonderee(10.0, 20.0, 1.0), '10/20 × coeff1 → 10.00');
assert_eq(8.0,   $svc->notePonderee(8.0,  10.0, 0.5), '8/10 × coeff0.5 → 8.00');
assert_eq(12.5,  $svc->notePonderee(12.5, 20.0, 1.0), '12.5/20 × coeff1 → 12.50');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — moyenneEvaluation
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 3 : moyenneEvaluation (classe) ──\n";

$notes = [
    ['valeur' => 15.0, 'est_absent' => 0],
    ['valeur' => 10.0, 'est_absent' => 0],
    ['valeur' => 5.0,  'est_absent' => 0],
];
$moy = $svc->moyenneEvaluation($notes, 20.0);
assert_eq(10.0, $moy->getValue(), 'Moyenne (15+10+5)/3 = 10.00');

// Avec absent = 0 (valeur 0)
$notes2 = [
    ['valeur' => 18.0, 'est_absent' => 0],
    ['valeur' => null, 'est_absent' => 1], // absent → 0
    ['valeur' => 12.0, 'est_absent' => 0],
];
$moy2 = $svc->moyenneEvaluation($notes2, 20.0);
assert_eq(10.0, $moy2->getValue(), 'Avec absent (18+0+12)/3 = 10.00');

// Vide
$moy3 = $svc->moyenneEvaluation([], 20.0);
assert_true($moy3->isEmpty(), 'Vide → isEmpty()');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — moyenneMatiere
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 4 : moyenneMatiere (élève) ──\n";

// Cas simple : 2 notes égales, même coeff
$items = [
    ['valeur' => 14.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
    ['valeur' => 16.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
];
$r = $svc->moyenneMatiere($items);
assert_eq(15.0, $r['moyenne']->getValue(), 'Matière : (14+16)/2 = 15.00');
assert_false($r['aEliminatoire'], 'Pas éliminatoire');
assert_eq(2, $r['nbNotes'], 'nbNotes = 2');

// Coefficients différents
$items2 = [
    ['valeur' => 10.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
    ['valeur' => 16.0, 'note_max' => 20.0, 'coefficient' => 2.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
];
$r2 = $svc->moyenneMatiere($items2);
// (10×1 + 16×2) / (1+2) = (10+32)/3 = 42/3 = 14.00
assert_eq(14.0, $r2['moyenne']->getValue(), 'Matière coeff différents : (10×1+16×2)/3 = 14.00');

// Barèmes mixtes
$items3 = [
    ['valeur' => 8.0, 'note_max' => 10.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],  // 16/20
    ['valeur' => 12.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null], // 12/20
];
$r3 = $svc->moyenneMatiere($items3);
assert_eq(14.0, $r3['moyenne']->getValue(), 'Barèmes mixtes : (16+12)/2 = 14.00');

// Absent → 0
$items4 = [
    ['valeur' => null, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 1, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
    ['valeur' => 16.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
];
$r4 = $svc->moyenneMatiere($items4);
assert_eq(8.0, $r4['moyenne']->getValue(), 'Absent vaut 0 : (0+16)/2 = 8.00');
assert_eq(1, $r4['nbAbsents'], 'nbAbsents = 1');

// Note éliminatoire
$items5 = [
    ['valeur' => 3.0, 'note_max' => 20.0, 'coefficient' => 2.0, 'est_absent' => 0, 'est_eliminatoire' => 1, 'seuil_eliminatoire' => 8.0],
    ['valeur' => 18.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
];
$r5 = $svc->moyenneMatiere($items5);
assert_true($r5['aEliminatoire'], 'Note éliminatoire détectée (3/20 < seuil 8)');

// Option absent_vaut_zero=false → exclure les absents
$svcNoAbsent = new AcademicCalculationService(['absent_vaut_zero' => false]);
$items6 = [
    ['valeur' => null, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 1, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
    ['valeur' => 16.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
];
$r6 = $svcNoAbsent->moyenneMatiere($items6);
assert_eq(16.0, $r6['moyenne']->getValue(), 'absent_vaut_zero=false → absent exclu : 16/1 = 16.00');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — moyennePeriode
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 5 : moyennePeriode ──\n";

$matiereInputs = [
    ['moyenne' => new AverageValue(14.0), 'coefficient' => 3.0],  // Maths coeff 3
    ['moyenne' => new AverageValue(12.0), 'coefficient' => 2.0],  // Français coeff 2
    ['moyenne' => new AverageValue(18.0), 'coefficient' => 1.0],  // EPS coeff 1
];
$rP = $svc->moyennePeriode($matiereInputs);
// (14×3 + 12×2 + 18×1) / (3+2+1) = (42+24+18)/6 = 84/6 = 14.00
assert_eq(14.0, $rP['moyenne']->getValue(), 'Période pondérée : 84/6 = 14.00');
assert_eq(3, $rP['nbMatieres'], 'nbMatieres = 3');

// Matière sans note (isEmpty)
$matiereInputs2 = [
    ['moyenne' => new AverageValue(14.0), 'coefficient' => 3.0],
    ['moyenne' => AverageValue::empty(),  'coefficient' => 2.0],  // pas de notes
];
$rP2 = $svc->moyennePeriode($matiereInputs2);
assert_eq(14.0, $rP2['moyenne']->getValue(), 'Période ignore matière sans note');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 6 — moyenneGenerale
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 6 : moyenneGenerale ──\n";

$periodes = [
    ['moyenne' => new AverageValue(12.0), 'poids' => 1.0],
    ['moyenne' => new AverageValue(14.0), 'poids' => 1.0],
    ['moyenne' => new AverageValue(16.0), 'poids' => 1.0],
];
$mG = $svc->moyenneGenerale($periodes);
assert_eq(14.0, $mG->getValue(), 'Générale 3 trimestres (12+14+16)/3 = 14.00');

// Poids différents (ex: T1 coeff1, T2 coeff2, T3 coeff2)
$periodes2 = [
    ['moyenne' => new AverageValue(10.0), 'poids' => 1.0],
    ['moyenne' => new AverageValue(16.0), 'poids' => 2.0],
];
$mG2 = $svc->moyenneGenerale($periodes2);
// (10×1 + 16×2) / (1+2) = 42/3 = 14.00
assert_eq(14.0, $mG2->getValue(), 'Générale poids (10×1+16×2)/3 = 14.00');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 7 — mentions
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 7 : mentions ──\n";

assert_eq(MentionValue::TB,          $svc->mentionFromFloat(17.5)->getLabel(), 'TB : ≥ 16');
assert_eq(MentionValue::BIEN,        $svc->mentionFromFloat(14.8)->getLabel(), 'B  : ≥ 14');
assert_eq(MentionValue::ASSEZ_BIEN,  $svc->mentionFromFloat(12.0)->getLabel(), 'AB : ≥ 12 exactement');
assert_eq(MentionValue::PASSABLE,    $svc->mentionFromFloat(10.0)->getLabel(), 'P  : ≥ 10 exactement');
assert_eq(MentionValue::INSUFFISANT, $svc->mentionFromFloat(9.99)->getLabel(), 'INS: < 10');
assert_eq(MentionValue::INSUFFISANT, $svc->mentionFromFloat(0.0)->getLabel(),  'INS: 0');
assert_true($svc->mentionFromFloat(14.0)->isAdmis(),  'TB/B/AB/P → admis');
assert_false($svc->mentionFromFloat(5.0)->isAdmis(),  'INS → non admis');

// Via AverageValue
$avgTB = new AverageValue(16.5);
assert_eq('TB', $svc->mention($avgTB)->getCode(), 'mention(AverageValue(16.5)) → TB');

// Empty AverageValue → mention INS
$mEmpty = $svc->mention(AverageValue::empty());
assert_eq('INS', $mEmpty->getCode(), 'mention(empty) → INS');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 8 — estEliminatoire
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 8 : estEliminatoire ──\n";

assert_true($svc->estEliminatoire(3.0, 20.0, 8.0),   '3/20 < seuil 8 → éliminatoire');
assert_false($svc->estEliminatoire(9.0, 20.0, 8.0),  '9/20 ≥ seuil 8 → ok');
assert_false($svc->estEliminatoire(8.0, 20.0, 8.0),  '8/20 = seuil 8 → ok (≥)');
assert_false($svc->estEliminatoire(null, 20.0, 8.0), 'null → jamais éliminatoire');
assert_false($svc->estEliminatoire(3.0, 20.0, null), 'seuil=null → jamais éliminatoire');
assert_true($svc->estEliminatoire(3.0, 10.0, 8.0),  '3/10 = 6/20 < seuil 8 → éliminatoire');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 9 — statistiquesClasse
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 9 : statistiquesClasse ──\n";

$moyennes = [
    new AverageValue(8.0),
    new AverageValue(10.0),
    new AverageValue(12.0),
    new AverageValue(15.0),
    new AverageValue(18.0),
];
$stats = $svc->statistiquesClasse($moyennes);
assert_eq(5,    $stats['count'],    'count = 5');
assert_eq(4,    $stats['admis'],    'admis = 4 (≥10)');
assert_eq(1,    $stats['echoues'],  'echues = 1 (<10)');
assert_eq(8.0,  $stats['min'],      'min = 8.00');
assert_eq(18.0, $stats['max'],      'max = 18.00');
assert_eq(12.6, $stats['moyenne'],  'moyenne = 12.60');
assert_eq(80.0, $stats['taux_reussite'], 'taux = 80%');

// Avec empty averages (ignorées)
$moyennesAvecEmpty = [
    new AverageValue(10.0),
    AverageValue::empty(),
    new AverageValue(14.0),
];
$stats2 = $svc->statistiquesClasse($moyennesAvecEmpty);
assert_eq(2, $stats2['count'], 'empty ignorée : count = 2');

// Vide
$stats3 = $svc->statistiquesClasse([]);
assert_eq(0, $stats3['count'], 'Vide → count = 0');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 10 — classement
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 10 : classement ──\n";

$eleves = [
    ['eleve_id' => 1, 'moyenne' => new AverageValue(12.0)],
    ['eleve_id' => 2, 'moyenne' => new AverageValue(18.0)],
    ['eleve_id' => 3, 'moyenne' => new AverageValue(15.0)],
    ['eleve_id' => 4, 'moyenne' => new AverageValue(12.0)], // ex-aequo avec élève 1
];
$ranked = $svc->classement($eleves);
assert_eq(1, $ranked[0]['rang'],    'rang 1 → 18.0');
assert_eq(2, $ranked[1]['rang'],    'rang 2 → 15.0');
assert_eq(3, $ranked[2]['rang'],    'rang 3 → 12.0 (premier ex-aequo)');
assert_eq(3, $ranked[3]['rang'],    'rang 3 → 12.0 (second ex-aequo)');
assert_eq(18.0, $ranked[0]['moyenne']->getValue(), 'Premier = 18.0');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 11 — prepareDecision
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 11 : prepareDecision ──\n";

$admis = $svc->prepareDecision(new AverageValue(12.0), false, 8.0);
assert_eq('admis', $admis['decision'], 'Admis : 12 ≥ 10');

$refuse = $svc->prepareDecision(new AverageValue(7.0), false, 8.0);
assert_eq('refuse', $refuse['decision'], 'Refusé : 7 < 8 (rattrapage seuil 8)');

$rattrap = $svc->prepareDecision(new AverageValue(9.0), false, 8.0);
assert_eq('rattrapage', $rattrap['decision'], 'Rattrapage : 8 ≤ 9 < 10');

$elim = $svc->prepareDecision(new AverageValue(12.0), true, 8.0);
assert_eq('refuse', $elim['decision'], 'Éliminatoire force refus même si moy ≥ 10');

$ind = $svc->prepareDecision(AverageValue::empty());
assert_eq('indeterminate', $ind['decision'], 'Empty → indeterminate');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 12 — Value Objects
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 12 : Value Objects ──\n";

// AverageValue
$av = new AverageValue(14.666, 2);
assert_eq(14.67, $av->getValue(), 'AverageValue arrondit à 2 décimales : 14.67');
assert_true($av->isPassant(), 'isPassant (14.67 ≥ 10)');
assert_false((new AverageValue(9.0))->isPassant(), 'Non passant (9.0 < 10)');
assert_eq('14.67', $av->format(2), 'format(2) → "14.67"');

// GradeValue
$gv = new GradeValue(16.0, 20.0, 2.0);
assert_eq(16.0, $gv->getValueSur20(), '16/20 → sur20 = 16.0');
assert_eq(32.0, $gv->getWeighted(),   '16/20 × coeff2 = 32.0');
assert_eq(80.0, $gv->getPercentage(), '16/20 = 80%');

$gv2 = new GradeValue(8.0, 10.0, 1.0);
assert_eq(16.0, $gv2->getValueSur20(), '8/10 → sur20 = 16.0');

// GradeValue invalide
try {
    $gv3 = new GradeValue(25.0, 20.0, 1.0);
    assert_true(false, 'GradeValue > noteMax doit lever exception');
} catch (\InvalidArgumentException) {
    assert_true(true, 'GradeValue > noteMax → InvalidArgumentException');
}

// MentionValue
$m1 = MentionValue::fromAverage(16.5);
assert_eq('TB',          $m1->getCode(),  'TB code');
assert_eq('Très Bien',   $m1->getLabel(), 'TB label');
assert_eq('emerald',     $m1->getCssColor(), 'TB css');
assert_true($m1->isAdmis(), 'TB isAdmis');

$m2 = MentionValue::fromAverage(5.0);
assert_false($m2->isAdmis(), 'INS non admis');
assert_eq('INS', $m2->getCode(), 'INS code');

// ═══════════════════════════════════════════════════════════════════════════════
// SECTION 13 — calculerBulletin (façade)
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n── Section 13 : calculerBulletin ──\n";

$matieresData = [
    1 => [ // Maths coeff 3
        'coefficient' => 3.0,
        'notes' => [
            ['valeur' => 14.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
            ['valeur' => 16.0, 'note_max' => 20.0, 'coefficient' => 2.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
        ],
    ],
    2 => [ // Français coeff 2
        'coefficient' => 2.0,
        'notes' => [
            ['valeur' => 10.0, 'note_max' => 20.0, 'coefficient' => 1.0, 'est_absent' => 0, 'est_eliminatoire' => 0, 'seuil_eliminatoire' => null],
        ],
    ],
];

$bulletin = $svc->calculerBulletin($matieresData, true, 8.0);

// Maths : (14×1 + 16×2) / (1+2) = 46/3 ≈ 15.33
assert_eq(15.33, $bulletin['matieres'][1]['moyenne']->getValue(), 'Bulletin Maths ≈ 15.33');
// Français : 10
assert_eq(10.0, $bulletin['matieres'][2]['moyenne']->getValue(), 'Bulletin Français = 10.00');
// Période : (15.33×3 + 10×2) / (3+2) = (46 + 20)/5 = 66/5 = 13.20
$expectedPeriode = round((15.33 * 3 + 10.0 * 2) / 5, 2);
assert_eq($expectedPeriode, $bulletin['periode']['moyenne']->getValue(), "Période = {$expectedPeriode}");
assert_eq('admis', $bulletin['decision']['decision'], 'Décision : admis');
assert_true($bulletin['mention']->isAdmis(), 'Mention : admis');

// ═══════════════════════════════════════════════════════════════════════════════
// RÉSUMÉ
// ═══════════════════════════════════════════════════════════════════════════════

$total = $pass + $fail;
echo "\n" . str_repeat('─', 60) . "\n";
echo "  Résultats : \033[32m{$pass} OK\033[0m / \033[31m{$fail} FAIL\033[0m / {$total} total\n";

if (!empty($errors)) {
    echo "\n  Échecs :\n";
    foreach ($errors as $e) {
        echo "    · {$e}\n";
    }
}

echo str_repeat('─', 60) . "\n";
exit($fail > 0 ? 1 : 0);
