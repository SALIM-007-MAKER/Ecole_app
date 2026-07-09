<?php
/**
 * Tests unitaires — BulletinGenerator V2
 *
 * Standalone, aucune dépendance DB.
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinGeneratorTest.php
 */

declare(strict_types=1);

// ─── Autoloader ──────────────────────────────────────────────────────────────

$base = dirname(__DIR__, 2);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'Core\\'                   => $base . '/core/',
        'App\Modules\Academique\\' => $base . '/app/Modules/Academique/',
        'App\Services\\'           => $base . '/app/Services/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

// Stubs légers sans appel DB
if (!class_exists('App\Services\AuditService')) {
    eval('namespace App\Services; class AuditService {
        public static function log(...$a): void {}
        public static function logCreate(...$a): void {}
        public static function logUpdate(...$a): void {}
        public static function logDelete(...$a): void {}
    }');
}
if (!class_exists('Core\EventDispatcher')) {
    eval('namespace Core; class EventDispatcher {
        public static function dispatch($e): void {}
    }');
}

use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\RankingEngine;
use App\Modules\Academique\Services\BulletinGenerator;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\DTO\BulletinSummary;
use App\Modules\Academique\ValueObjects\AverageValue;

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

function assert_true(bool $val, string $label): void  { assert_eq(true, $val, $label); }
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

function assert_contains(string $needle, string $haystack, string $label): void
{
    global $passed, $failed;
    if (str_contains($haystack, $needle)) {
        echo "\033[32m  ✓ $label\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $label — '$needle' non trouvé\033[0m\n";
        $failed++;
    }
}

function section(string $title): void
{
    echo "\n\033[33m── $title\033[0m\n";
}

// ─── Stubs ───────────────────────────────────────────────────────────────────

class StubBulletinRepository extends BulletinRepository
{
    private ?object $eleve;
    private ?object $periode;
    private array   $noteRows;
    private array   $saved = [];
    private ?array  $storedBulletin = null;

    public function __construct(?object $eleve, ?object $periode, array $noteRows)
    {
        // Ne pas appeler parent::__construct() — pas de DB
        $this->eleve    = $eleve;
        $this->periode  = $periode;
        $this->noteRows = $noteRows;
    }

    public function infoEleve(int $eleveId): ?object    { return $this->eleve; }
    public function infoPeriode(int $periodeId): ?object { return $this->periode; }
    public function infoEtablissement(): array           { return ['nom' => 'Lycée Test', 'adresse' => '1 rue Test', 'logo' => null]; }
    public function elevesParClasse(int $classeId): array { return $this->eleve ? [$this->eleve->id] : []; }

    public function notesEleveParPeriode(int $eleveId, int $classeId, int $periodeId): array
    {
        return $this->noteRows;
    }

    public function saveBulletin(BulletinData $b): int
    {
        $this->saved[] = $b;
        $this->storedBulletin = $b->toArray();
        return 1;
    }

    public function findByToken(string $token): ?array
    {
        if ($this->storedBulletin && $this->storedBulletin['verification_token'] === $token) {
            return array_merge(['statut' => 'brouillon', 'data_json' => json_encode($this->storedBulletin)], $this->storedBulletin);
        }
        return null;
    }

    public function updateStatut(string $token, string $statut, int $userId, ?string $publishedAt = null, ?string $archivedAt = null): bool
    {
        return true;
    }

    public function getSaved(): array { return $this->saved; }
}

class StubRankingRepo extends RankingRepository
{
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function notesParClasseEtPeriode(int $c, int $p): array  { return $this->rows; }
    public function notesParNiveauEtPeriode(string $n, int $p): array { return $this->rows; }
    public function notesParMatiereEtPeriode(int $m, int $p, ?int $c = null): array { return $this->rows; }
    public function notesParClasseMultiPeriodes(int $c, array $p): array { return $this->rows; }
}

// ─── Fixtures ─────────────────────────────────────────────────────────────────

function makeEleve(int $id = 1, string $nom = 'Dupont', string $prenom = 'Jean'): object
{
    return (object)[
        'id'        => $id,
        'nom'       => $nom,
        'prenom'    => $prenom,
        'matricule' => 'MAT00' . $id,
        'photo'     => null,
        'classe_id' => 1,
        'classe_nom'=> '3e A',
        'niveau'    => '3e',
    ];
}

function makePeriode(int $id = 1, string $nom = 'Trimestre 1'): object
{
    return (object)[
        'id'            => $id,
        'nom'           => $nom,
        'annee_scolaire'=> '2025/2026',
        'date_debut'    => '2025-09-01',
        'date_fin'      => '2025-12-20',
    ];
}

function makeNoteRow(int $matiereId, string $matiereNom, float $coeffMat, float $valeur, float $noteMax = 20.0, float $coeffEval = 1.0, bool $absent = false): array
{
    return [
        'matiere_id'         => $matiereId,
        'matiere_nom'        => $matiereNom,
        'coeff_matiere'      => $coeffMat,
        'evaluation_id'      => rand(1, 999),
        'note_max'           => $noteMax,
        'coeff_eval'         => $coeffEval,
        'valeur'             => $absent ? null : $valeur,
        'est_absent'         => $absent ? 1 : 0,
        'est_eliminatoire'   => 0,
        'seuil_eliminatoire' => null,
    ];
}

function makeRankingRows(array $elevesNotes): array
{
    $rows = [];
    foreach ($elevesNotes as $eid => [$nom, $moy]) {
        $rows[] = [
            'eleve_id'           => $eid,
            'nom'                => $nom,
            'prenom'             => 'P',
            'matricule'          => null,
            'classe_id'          => 1,
            'classe_nom'         => null,
            'matiere_id'         => 10,
            'matiere_nom'        => 'Maths',
            'coeff_matiere'      => 1.0,
            'evaluation_id'      => rand(1, 999),
            'note_max'           => 20.0,
            'coeff_eval'         => 1.0,
            'valeur'             => $moy,
            'est_absent'         => 0,
            'est_eliminatoire'   => 0,
            'seuil_eliminatoire' => null,
        ];
    }
    return $rows;
}

function makeGenerator(array $noteRows, ?object $eleve = null, ?object $periode = null, array $rankingRows = []): BulletinGenerator
{
    $calc    = new AcademicCalculationService();
    $eleveObj = $eleve ?? makeEleve();

    // RankingEngine attend des lignes avec eleve_id — on enrichit les note rows si nécessaire
    if (empty($rankingRows)) {
        $rankingRows = array_map(fn($r) => array_merge($r, [
            'eleve_id'   => $eleveObj->id,
            'nom'        => $eleveObj->nom,
            'prenom'     => $eleveObj->prenom,
            'matricule'  => $eleveObj->matricule,
            'classe_id'  => $eleveObj->classe_id,
            'classe_nom' => null,
        ]), $noteRows);
    }

    $rankRepo = new StubRankingRepo($rankingRows);
    $ranking  = new RankingEngine($calc, $rankRepo);
    $repo     = new StubBulletinRepository($eleveObj, $periode ?? makePeriode(), $noteRows);
    return new BulletinGenerator($calc, $ranking, $repo, seuilRattrapage: 8.0, appKey: 'test_key');
}

// ─── Tests ───────────────────────────────────────────────────────────────────

echo "\n\033[1m=== BulletinGenerator Test Suite ===\033[0m\n";

// ─── Section 1 : Structure BulletinData ──────────────────────────────────────

section('1. Structure BulletinData — preview');

$noteRows = [
    makeNoteRow(10, 'Mathématiques', 3.0, 16.0),
    makeNoteRow(10, 'Mathématiques', 3.0, 14.0),
    makeNoteRow(11, 'Français',      2.0, 12.0),
];

$gen = makeGenerator($noteRows);
$b   = $gen->previewBulletin(1, 1);

assert_true($b instanceof BulletinData, 'retourne BulletinData');
assert_eq(1,    $b->eleveId,        'eleveId correct');
assert_eq(1,    $b->periodeId,      'periodeId correct');
assert_eq('3e A', $b->classeNom,    'classeNom correct');
assert_eq('brouillon', $b->statut,  'statut brouillon par défaut');
assert_true(strlen($b->verificationToken) === 64, 'token = 64 hex chars (SHA-256)');
assert_true(str_contains($b->qrCodeUrl, $b->verificationToken), 'qrCodeUrl contient le token');
assert_eq(2, count($b->lignesMatieres), '2 lignes matières');
assert_true(!empty($b->appreciationPp), 'appréciation PP générée');
assert_true(!empty($b->decision),       'décision non vide');

// ─── Section 2 : Calcul — aucune moyenne calculée dans BulletinGenerator ─────

section('2. Délégation AcademicCalculationService (zéro calcul direct)');

// Maths(coeff 3) : notes 16 et 14 → moy = (16×1+14×1)/2 = 15/20
// Français(coeff 2) : note 12/20
// Moy période = (15×3 + 12×2) / (3+2) = (45+24)/5 = 69/5 = 13.8
// Maths(coeff 3): (16+14)/2 = 15/20 ; Français(coeff 2): 12/20
// Moy période = (15×3 + 12×2) / 5 = 69/5 = 13.8  → AB (12 ≤ x < 14)
assert_near(13.8, $b->moyennePeriode, 'Moyenne période = 13.8');
assert_eq('AB', $b->mentionCode,      'Mention AB (13.8)');
assert_true($b->admis,               'Admis (13.8 >= 10)');
assert_false($b->aEliminatoire,      'Pas éliminatoire');

// ─── Section 3 : Mention et décision ─────────────────────────────────────────

section('3. Décision de passage');

// Moyenne 13.8 >= 10 → admis
assert_eq('admis', $b->decision,   'Décision admis pour 13.8');
assert_true(!empty($b->decisionMotif), 'Motif décision non vide');

// Test avec moyenne < seuil de passage (10)
$noteRowsFail = [makeNoteRow(10, 'Maths', 1.0, 6.0)];
$bFail = makeGenerator($noteRowsFail)->previewBulletin(1, 1);
assert_eq('refuse', $bFail->decision, 'Décision refuse pour moy 6');
assert_false($bFail->admis,           'Non admis');

// Test rattrapage (entre seuilRattrapage=8 et 10)
$noteRowsRatt = [makeNoteRow(10, 'Maths', 1.0, 9.0)];
$bRatt = makeGenerator($noteRowsRatt)->previewBulletin(1, 1);
assert_eq('rattrapage', $bRatt->decision, 'Décision rattrapage pour moy 9 (seuil=8)');

// ─── Section 4 : Lignes matières ─────────────────────────────────────────────

section('4. Lignes matières enrichies');

$ligne10 = null;
$ligne11 = null;
foreach ($b->lignesMatieres as $l) {
    if ($l['matiere_id'] === 10) $ligne10 = $l;
    if ($l['matiere_id'] === 11) $ligne11 = $l;
}

assert_true($ligne10 !== null,                 'Ligne matière 10 présente');
assert_true($ligne11 !== null,                 'Ligne matière 11 présente');
assert_near(15.0, (float)$ligne10['moyenne'],  'Moy maths = 15');
assert_near(12.0, (float)$ligne11['moyenne'],  'Moy français = 12');
assert_eq('B',  $ligne10['mention_code'],      'Mention maths = B (15 ≥14 <16)');
assert_eq('AB', $ligne11['mention_code'],      'Mention français = AB (12)');
assert_eq(2, $ligne10['nb_notes'],             'Maths = 2 notes');
assert_eq(1, $ligne11['nb_notes'],             'Français = 1 note');
assert_true(!empty($ligne10['appreciation']),  'Appréciation maths non vide');

// ─── Section 5 : Token stable ─────────────────────────────────────────────────

section('5. Token de vérification stable (idempotent)');

$token1 = $gen->generateVerificationToken(1, 1);
$token2 = $gen->generateVerificationToken(1, 1);
$token3 = $gen->generateVerificationToken(2, 1);

assert_eq($token1, $token2,    'Même token pour même eleve+periode');
assert_true($token1 !== $token3, 'Token différent pour autre élève');
assert_eq(64, strlen($token1), 'Token = 64 chars');

// ─── Section 6 : genererBulletin persiste ─────────────────────────────────────

section('6. genererBulletin — persistance et retour');

$calc      = new AcademicCalculationService();
$eleve6    = makeEleve();
$rankRows6 = array_map(fn($r) => array_merge($r, [
    'eleve_id'   => $eleve6->id,
    'nom'        => $eleve6->nom,
    'prenom'     => $eleve6->prenom,
    'matricule'  => $eleve6->matricule,
    'classe_id'  => $eleve6->classe_id,
    'classe_nom' => null,
]), $noteRows);
$rankRepo = new StubRankingRepo($rankRows6);
$ranking  = new RankingEngine($calc, $rankRepo);
$stubRepo = new StubBulletinRepository($eleve6, makePeriode(), $noteRows);
$gen2     = new BulletinGenerator($calc, $ranking, $stubRepo, 8.0, 'test_key');

$generated = $gen2->genererBulletin(1, 1, 99);

assert_true($generated instanceof BulletinData, 'genererBulletin retourne BulletinData');
assert_eq(1, count($stubRepo->getSaved()),      'saveBulletin appelé une fois');
assert_eq(99, $generated->generatedById,         'generatedById = userId');

// ─── Section 7 : Transition statut ────────────────────────────────────────────

section('7. Transition de statut via withStatut()');

$bPublie  = $generated->withStatut('publie', publishedAt: '2026-01-15 10:00:00');
$bArchive = $generated->withStatut('archive', archivedAt: '2026-06-01 12:00:00');

assert_eq('publie',  $bPublie->statut,     'withStatut publie');
assert_eq('2026-01-15 10:00:00', $bPublie->publishedAt, 'publishedAt correct');
assert_eq('brouillon', $generated->statut, 'Original inchangé (immuable)');
assert_eq('archive', $bArchive->statut,    'withStatut archive');
assert_eq('2026-06-01 12:00:00', $bArchive->archivedAt, 'archivedAt correct');

// ─── Section 8 : toArray / fromArray ──────────────────────────────────────────

section('8. Sérialisation BulletinData');

$arr      = $generated->toArray();
$restored = BulletinData::fromArray($arr);

assert_eq($generated->eleveId,       $restored->eleveId,        'eleveId préservé');
assert_eq($generated->moyennePeriode,$restored->moyennePeriode, 'moyenne préservée');
assert_eq($generated->mentionCode,   $restored->mentionCode,    'mention préservée');
assert_eq($generated->decision,      $restored->decision,       'décision préservée');
assert_eq($generated->verificationToken, $restored->verificationToken, 'token préservé');
assert_eq(count($generated->lignesMatieres), count($restored->lignesMatieres), 'lignes préservées');

// ─── Section 9 : toSummary ────────────────────────────────────────────────────

section('9. BulletinSummary');

$summary = $generated->toSummary();

assert_true($summary instanceof BulletinSummary, 'toSummary() retourne BulletinSummary');
assert_eq($generated->eleveId,    $summary->eleveId,    'eleveId dans summary');
assert_eq($generated->rang,       $summary->rang,        'rang dans summary');
assert_eq($generated->decision,   $summary->decision,    'décision dans summary');
assert_eq($generated->statut,     $summary->statut,      'statut dans summary');
assert_eq('Dupont Jean',          $summary->eleveNomComplet(), 'eleveNomComplet()');
assert_eq($generated->decision === 'admis', $summary->estAdmis(), 'estAdmis() cohérent');

// ─── Section 10 : Export HTML ─────────────────────────────────────────────────

section('10. Export HTML');

$html = $gen2->exportHtml($generated);

assert_true(str_starts_with(trim($html), '<!DOCTYPE html>'), 'HTML commence par DOCTYPE');
assert_contains('Dupont', $html,          'Nom élève dans HTML');
assert_contains('Lycée Test', $html,      'Établissement dans HTML');
assert_contains('Trimestre 1', $html,     'Période dans HTML');
assert_contains('<table', $html,          'Table matières présente');
assert_contains('BULLETIN DE NOTES', $html, 'Titre bulletin présent');
assert_contains($generated->verificationToken, $html, 'Token de vérification dans HTML');
assert_contains('DÉCISION', $html,        'Section décision présente');
assert_contains('signatures', $html,      'Section signatures présente');

// ─── Section 11 : toApiPayload ───────────────────────────────────────────────

section('11. API Payload');

$payload = $gen2->toApiPayload($generated);

assert_true(isset($payload['_links']),           'payload a _links');
assert_true(isset($payload['_links']['self']),   '_links.self présent');
assert_true(isset($payload['_links']['verify']), '_links.verify présent');
assert_true(isset($payload['_links']['html']),   '_links.html présent');
assert_contains('/v2/academique/bulletins/', $payload['_links']['self'], 'URL correcte');

// ─── Section 12 : BulletinSummary toArray ────────────────────────────────────

section('12. BulletinSummary::toArray()');

$sarr = $summary->toArray();
assert_true(isset($sarr['eleve_id']),    'toArray a eleve_id');
assert_true(isset($sarr['moyenne']),     'toArray a moyenne');
assert_true(isset($sarr['rang']),        'toArray a rang');
assert_true(isset($sarr['decision']),    'toArray a decision');
assert_true(isset($sarr['statut']),      'toArray a statut');
assert_true(isset($sarr['verification_token']), 'toArray a verification_token');

// ─── Section 13 : Appreciation déterministe ──────────────────────────────────

section('13. Appréciation déterministe');

// Même élève+mention → même appréciation
$gen3 = makeGenerator([makeNoteRow(10, 'Maths', 1.0, 18.0)]);
$b1   = $gen3->previewBulletin(1, 1);
$b2   = $gen3->previewBulletin(1, 1);

assert_eq($b1->appreciationPp, $b2->appreciationPp, 'Appréciation identique entre re-générations');
assert_true(strlen($b1->appreciationPp) > 10, 'Appréciation non vide (>10 chars)');

// Élève différent → peut avoir appréciation différente (selon pool)
$gen4 = makeGenerator([makeNoteRow(10, 'Maths', 1.0, 18.0)], makeEleve(3, 'Martin', 'Alice'));
$b3   = $gen4->previewBulletin(3, 1);
assert_true(!empty($b3->appreciationPp), 'Appréciation élève 3 non vide');

// ─── Section 14 : groupNotesByMatiere (méthode publique) ─────────────────────

section('14. groupNotesByMatiere() — accès direct');

$gen5  = makeGenerator([]);
$rows  = [
    makeNoteRow(10, 'Maths',   3.0, 16.0),
    makeNoteRow(10, 'Maths',   3.0, 14.0),
    makeNoteRow(11, 'Anglais', 2.0, 18.0),
];
$grouped = $gen5->groupNotesByMatiere($rows);

assert_eq(2,    count($grouped),                '2 matières groupées');
assert_true(isset($grouped[10]),                'Matière 10 présente');
assert_true(isset($grouped[11]),                'Matière 11 présente');
assert_eq(2,    count($grouped[10]['notes']),   'Matière 10 = 2 notes');
assert_eq(1,    count($grouped[11]['notes']),   'Matière 11 = 1 note');
assert_eq(3.0,  $grouped[10]['coefficient'],    'Coeff matière 10 = 3.0');
assert_eq(16.0, $grouped[10]['notes'][0]['valeur'], 'Première note maths = 16');

// ─── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) exit(1);
