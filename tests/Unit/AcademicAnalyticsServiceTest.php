<?php

/**
 * Tests unitaires — AcademicAnalyticsService (Phase 2.8)
 *
 * Stratégie d'isolation :
 *   • StubAnalyticsRepo : étend AnalyticsRepository sans construire PDO,
 *     surcharge toutes les méthodes pour retourner des fixtures en mémoire.
 *   • AcademicCalculationService est instancié normalement (pur, sans DB).
 *   • EventDispatcher : les events sont dispatchés mais ne nécessitent pas de
 *     handler enregistré (config/events.php non chargé en test unitaire).
 */

declare(strict_types=1);

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

// Stub minimal AuditService (évite require de Core\Database)
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

use App\Modules\Academique\Services\AcademicAnalyticsService;
use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Repositories\AnalyticsRepository;
use App\Modules\Academique\DTO\AnalyticsResult;
use App\Modules\Academique\DTO\DashboardMetrics;

// ─────────────────────────────────────────────────────────────────────────────
//  Stub Repository
// ─────────────────────────────────────────────────────────────────────────────

class StubAnalyticsRepo extends AnalyticsRepository
{
    public function __construct(
        private array $classeRows    = [],
        private array $matiereRows   = [],
        private array $niveauRows    = [],
        private array $distribRows   = [],
        private array $absData       = [],
        private array $etabData      = [],
        private array $topRows       = [],
        private array $alerteRows    = [],
        private array $statsEtab     = [],
        private array $matEnsRows    = [],
        private array $evolutionRows = [],
        private ?object $periodeMock = null,
    ) {
        // Pas d'appel parent (pas de DB)
    }

    public function moyenneEtablissement(int $periodeId): array       { return $this->etabData; }
    public function moyennesParClasse(int $periodeId, ?string $niveau = null): array { return $this->classeRows; }
    public function moyennesParMatiere(int $periodeId, ?int $classeId = null): array { return $this->matiereRows; }
    public function moyennesParNiveau(int $periodeId): array          { return $this->niveauRows; }
    public function distributionNotes(int $periodeId, ?int $classeId = null, ?int $matiereId = null): array { return $this->distribRows; }
    public function tauxAbsenteisme(int $periodeId, ?int $classeId = null): array { return $this->absData; }
    public function topPerformers(int $periodeId, ?int $classeId = null, int $limit = 10): array { return $this->topRows; }
    public function alertesEleves(int $periodeId, ?int $classeId = null, float $seuil = 10.0, int $limit = 20): array { return $this->alerteRows; }
    public function statsEtablissement(): array                        { return $this->statsEtab; }
    public function matieresEnseignant(int $enseignantId, int $periodeId): array { return $this->matEnsRows; }
    public function evolutionParPeriodes(int $classeId, array $periodeIds): array { return $this->evolutionRows; }
    public function infoPeriode(int $periodeId): ?object              { return $this->periodeMock; }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────────────────────

$ok = $fail = 0;
function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $ok, $fail;
    if ($expected == $actual) {
        echo "  [OK] $label\n"; $ok++;
    } else {
        $e = is_scalar($expected) ? $expected : json_encode($expected);
        $a = is_scalar($actual)   ? $actual   : json_encode($actual);
        echo "  [FAIL] $label — attendu=$e, obtenu=$a\n"; $fail++;
    }
}
function assert_true(bool $val, string $label): void
{
    global $ok, $fail;
    if ($val) { echo "  [OK] $label\n"; $ok++; }
    else      { echo "  [FAIL] $label\n"; $fail++; }
}
function assert_count(int $expected, array $arr, string $label): void
{
    assert_eq($expected, count($arr), $label);
}
function section(string $name): void { echo "\n── $name ──\n"; }

function makeService(array $opts = []): AcademicAnalyticsService
{
    $periodeMock = (object)['id' => 1, 'nom' => 'Trimestre 1', 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-31'];

    $repo = new StubAnalyticsRepo(
        classeRows   : $opts['classeRows']    ?? [],
        matiereRows  : $opts['matiereRows']   ?? [],
        niveauRows   : $opts['niveauRows']    ?? [],
        distribRows  : $opts['distribRows']   ?? [],
        absData      : $opts['absData']       ?? ['nb_total' => 0, 'nb_absences' => 0, 'nb_non_saisis' => 0],
        etabData     : $opts['etabData']      ?? ['moyenne' => 0, 'nb_eleves' => 0, 'nb_notes' => 0],
        topRows      : $opts['topRows']       ?? [],
        alerteRows   : $opts['alerteRows']    ?? [],
        statsEtab    : $opts['statsEtab']     ?? ['nb_classes' => 0, 'nb_eleves' => 0, 'nb_niveaux' => 0],
        matEnsRows   : $opts['matEnsRows']    ?? [],
        evolutionRows: $opts['evolutionRows'] ?? [],
        periodeMock  : $opts['periodeMock']   ?? $periodeMock,
    );

    return new AcademicAnalyticsService($repo, new AcademicCalculationService());
}

// Fixtures partagées
$classeRows = [
    ['classe_id'=>1,'classe_nom'=>'6e A','niveau'=>'6e','nb_eleves'=>30,'nb_notes'=>90,'moyenne'=>13.5,'nb_passants'=>25,'nb_absences'=>3,'note_min'=>8.0,'note_max'=>19.5],
    ['classe_id'=>2,'classe_nom'=>'5e B','niveau'=>'5e','nb_eleves'=>28,'nb_notes'=>84,'moyenne'=>11.2,'nb_passants'=>18,'nb_absences'=>5,'note_min'=>5.0,'note_max'=>18.0],
];
$distribRows = [
    ['tranche'=>'TB',  'nb'=>8],
    ['tranche'=>'B',   'nb'=>15],
    ['tranche'=>'AB',  'nb'=>20],
    ['tranche'=>'P',   'nb'=>25],
    ['tranche'=>'INS', 'nb'=>12],
];
$topRows = [
    ['eleve_id'=>1,'nom'=>'DIALLO','prenom'=>'Alpha','matricule'=>'E001','classe_nom'=>'6e A','moyenne_approx'=>18.5],
    ['eleve_id'=>2,'nom'=>'KONÉ',  'prenom'=>'Fatoumata','matricule'=>'E002','classe_nom'=>'6e A','moyenne_approx'=>17.2],
];
$alerteRows = [
    ['eleve_id'=>5,'nom'=>'TRAORÉ','prenom'=>'Boubacar','classe_nom'=>'5e B','moyenne_approx'=>6.3],
];
$etabData = ['moyenne'=>12.35,'nb_eleves'=>58,'nb_notes'=>174];

// ─────────────────────────────────────────────────────────────────────────────
//  Section 1 — AnalyticsResult DTO
// ─────────────────────────────────────────────────────────────────────────────

section('1. AnalyticsResult DTO');

$res = new AnalyticsResult('moyenne_etab','Moyenne',12.5, 11.0, 58, [], ['periode_id'=>1], date('Y-m-d H:i:s'));
assert_eq(1.5,            $res->getEvolution(),  'Evolution = +1.5');
assert_true($res->isImproving() === true,         'isImproving = true');
assert_eq('trending-up',  $res->getTrendIcon(),   'getTrendIcon = trending-up');
assert_eq('emerald',      $res->getTrendColor(),  'getTrendColor = emerald');

$bad = new AnalyticsResult('t','l',9.0, 10.0, 10, [], [], date('Y-m-d H:i:s'));
assert_eq(-1.0,           $bad->getEvolution(),   'Régression = -1.0');
assert_eq('trending-down',$bad->getTrendIcon(),   'Icon = trending-down');
assert_eq('red',          $bad->getTrendColor(),  'Color = red');

$noComp = AnalyticsResult::empty('t','l');
assert_eq(null,           $noComp->getEvolution(),'Empty: evolution = null');
assert_eq('minus',        $noComp->getTrendIcon(),'Empty: icon = minus');
assert_eq('slate',        $noComp->getTrendColor(),'Empty: color = slate');

$arr = $res->toArray();
assert_true(isset($arr['type']) && isset($arr['evolution']) && isset($arr['trend_icon']) && isset($arr['trend_color']) && isset($arr['breakdown']), 'toArray fields');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 2 — DashboardMetrics DTO
// ─────────────────────────────────────────────────────────────────────────────

section('2. DashboardMetrics DTO');

$dm = DashboardMetrics::empty('directeur', 1);
assert_true($dm->isEmpty(),        'empty() → isEmpty = true');
assert_eq('directeur', $dm->role,  'role = directeur');
assert_eq(1, $dm->periodeId,       'periodeId = 1');

$kpi = ['id'=>'moy','label'=>'Moyenne','value'=>12.5,'unit'=>'/20','trend'=>'minus','icon'=>'bar','color'=>'blue'];
$dm2 = new DashboardMetrics('enseignant',1,'T1',5,'Maths',[$kpi],[],[],[],[],[],[],[], date('Y-m-d H:i:s'));
assert_eq($kpi, $dm2->getKpi('moy'),  'getKpi(moy) = kpi array');
assert_eq(null, $dm2->getKpi('xxx'),  'getKpi(inexistant) = null');
assert_true(!$dm2->isEmpty(),          'dm2 not empty');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 3 — moyenneEtablissement
// ─────────────────────────────────────────────────────────────────────────────

section('3. moyenneEtablissement');

$svc = makeService(['etabData' => $etabData]);
$r = $svc->moyenneEtablissement(1);
assert_eq('moyenne_etablissement', $r->type,  'type correct');
assert_eq(12.35, $r->value,                   'valeur = 12.35');
assert_eq(58, $r->count,                      'count = 58 élèves');
assert_eq(1, $r->metadata['periode_id'],      'metadata periode_id = 1');

// Cas vide
$svcEmpty = makeService();
$rEmpty = $svcEmpty->moyenneEtablissement(99);
assert_eq(0.0, $rEmpty->value,  'Vide → value = 0.0');
assert_eq(0, $rEmpty->count,    'Vide → count = 0');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 4 — moyenneParClasse
// ─────────────────────────────────────────────────────────────────────────────

section('4. moyenneParClasse');

$svc = makeService(['classeRows' => $classeRows]);
$r = $svc->moyenneParClasse(1);
assert_count(2, $r, '2 classes retournées');
assert_true(isset($r[1], $r[2]), 'Keyed par classe_id 1 et 2');
assert_eq(13.5, $r[1]->value,   'Classe 1 → 13.5');
assert_eq(11.2, $r[2]->value,   'Classe 2 → 11.2');
assert_eq('Moyenne 6e A', $r[1]->label, 'Label classe 1');
assert_eq(1, $r[1]->metadata['classe_id'], 'Metadata classe_id = 1');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 5 — moyenneParNiveau
// ─────────────────────────────────────────────────────────────────────────────

section('5. moyenneParNiveau');

$niveauRows = [
    ['niveau'=>'6e','nb_classes'=>2,'nb_eleves'=>60,'nb_notes'=>180,'moyenne'=>13.1,'nb_passants'=>50],
    ['niveau'=>'5e','nb_classes'=>2,'nb_eleves'=>56,'nb_notes'=>168,'moyenne'=>11.8,'nb_passants'=>40],
];
$svc = makeService(['niveauRows' => $niveauRows]);
$r = $svc->moyenneParNiveau(1);
assert_count(2, $r,              '2 niveaux');
assert_true(isset($r['6e'], $r['5e']), 'Keyed par niveau');
assert_eq(13.1, $r['6e']->value, '6e → 13.1');
assert_eq(2, $r['6e']->metadata['nb_classes'], 'nb_classes = 2');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 6 — moyenneParMatiere
// ─────────────────────────────────────────────────────────────────────────────

section('6. moyenneParMatiere');

$matiereRows = [
    ['matiere_id'=>1,'matiere_nom'=>'Mathématiques','coefficient'=>4,'nb_eleves'=>58,'nb_notes'=>116,'moyenne'=>12.8,'nb_passants'=>42,'note_min'=>5.0,'note_max'=>20.0],
    ['matiere_id'=>2,'matiere_nom'=>'Français',     'coefficient'=>4,'nb_eleves'=>58,'nb_notes'=>116,'moyenne'=>11.5,'nb_passants'=>35,'note_min'=>4.0,'note_max'=>19.0],
];
$svc = makeService(['matiereRows' => $matiereRows]);
$r = $svc->moyenneParMatiere(1);
assert_count(2, $r, '2 matières');
assert_true(isset($r[1], $r[2]), 'Keyed par matiere_id 1 et 2');
assert_eq(12.8, $r[1]->value,   'Maths → 12.8');
assert_eq(4.0, $r[1]->metadata['coefficient'], 'Coefficient = 4');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 7 — tauxAbsenteisme
// ─────────────────────────────────────────────────────────────────────────────

section('7. tauxAbsenteisme');

$svc = makeService(['absData' => ['nb_total'=>200,'nb_absences'=>30,'nb_non_saisis'=>5]]);
$t = $svc->tauxAbsenteisme(1);
assert_eq(15.0, $t, 'Taux absence = 30/200 = 15%');

$svc0 = makeService();
assert_eq(0.0, $svc0->tauxAbsenteisme(1), 'Taux absence vide = 0%');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 8 — distributionNotes
// ─────────────────────────────────────────────────────────────────────────────

section('8. distributionNotes');

$svc = makeService(['distribRows' => $distribRows]);
$d = $svc->distributionNotes(1);
assert_count(5, $d, '5 tranches de distribution');

$byTranche = array_column($d, null, 'tranche');
assert_eq(8,    $byTranche['TB']['nb'],          'TB = 8 notes');
assert_eq(15.0, $byTranche['INS']['pourcentage'],'INS = 12/80×100 = 15%');
// Total = 8+15+20+25+12 = 80
assert_eq(15.0, round((12/80)*100, 1),           'INS pourcentage = 15%');
assert_eq('emerald', $byTranche['TB']['color'],  'TB → couleur emerald');
assert_eq('red',     $byTranche['INS']['color'], 'INS → couleur red');
assert_eq('Très Bien', $byTranche['TB']['label'],'TB → label Très Bien');

// Ordre : TB, B, AB, P, INS
$tranches = array_column($d, 'tranche');
assert_eq(['TB','B','AB','P','INS'], $tranches,  'Ordre des tranches correct');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 9 — evolutionResultats
// ─────────────────────────────────────────────────────────────────────────────

section('9. evolutionResultats');

$evoRows = [
    ['periode_id'=>1,'periode_nom'=>'T1','moyenne'=>11.0,'nb_eleves'=>30,'date_debut'=>'2026-09-01'],
    ['periode_id'=>2,'periode_nom'=>'T2','moyenne'=>12.5,'nb_eleves'=>30,'date_debut'=>'2026-12-01'],
    ['periode_id'=>3,'periode_nom'=>'T3','moyenne'=>11.8,'nb_eleves'=>30,'date_debut'=>'2027-03-01'],
];
$svc = makeService(['evolutionRows' => $evoRows]);
$evo = $svc->evolutionResultats(1, [1, 2, 3]);
assert_count(3, $evo, '3 périodes');
assert_eq(null, $evo[0]['evolution'],  'T1 évolution = null (pas de préc.)');
assert_eq(1.5,  $evo[1]['evolution'],  'T2 évolution = +1.5');
assert_eq(-0.7, $evo[2]['evolution'],  'T3 évolution = -0.7');
assert_eq('T2', $evo[1]['periode_nom'],'Nom T2 correct');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 10 — dashboardDirecteur
// ─────────────────────────────────────────────────────────────────────────────

section('10. dashboardDirecteur');

$svc = makeService([
    'classeRows'  => $classeRows,
    'etabData'    => $etabData,
    'absData'     => ['nb_total'=>200,'nb_absences'=>10,'nb_non_saisis'=>2],
    'distribRows' => $distribRows,
    'topRows'     => $topRows,
    'alerteRows'  => $alerteRows,
    'statsEtab'   => ['nb_classes'=>2,'nb_eleves'=>58,'nb_niveaux'=>2],
]);
$d = $svc->dashboardDirecteur(1);
assert_eq('directeur', $d->role,   'Rôle = directeur');
assert_eq(1, $d->periodeId,        'Période = 1');
assert_eq('Trimestre 1', $d->periodeNom, 'Nom période = Trimestre 1');
assert_count(6, $d->kpis,          '6 KPIs directeur');
assert_count(2, $d->topPerformers, '2 top performers');
assert_count(1, $d->alertes,       '1 alerte');
assert_count(2, $d->classesOverview, '2 classes dans overview');
assert_count(5, $d->distributionNotes, '5 tranches distribution');

$kpiIds = array_column($d->kpis, 'id');
assert_true(in_array('nb_eleves',    $kpiIds), 'KPI nb_eleves présent');
assert_true(in_array('taux_reussite',$kpiIds), 'KPI taux_reussite présent');
assert_true(in_array('taux_absences',$kpiIds), 'KPI taux_absences présent');

// Taux réussite = (25+18)/(30+28) = 43/58 ≈ 74.1%
$kpiMap = array_column($d->kpis, null, 'id');
assert_eq(74.1, $kpiMap['taux_reussite']['value'], 'Taux réussite = 74.1%');

// Top performer rang 1 = DIALLO
$top1 = $d->topPerformers[0];
assert_eq(1,        $top1['rang'],         'Top 1 rang = 1');
assert_eq('DIALLO', $top1['nom'],          'Top 1 = DIALLO');
assert_eq('TB',     $top1['mention_code'], 'Mention DIALLO = TB (18.5)');

// Classes overview
$cl = $d->classesOverview[0];
assert_true(isset($cl['classe_id']) && isset($cl['classe_nom']) && isset($cl['niveau']) && isset($cl['nb_eleves']) && isset($cl['moyenne']) && isset($cl['taux_reussite']), 'Overview fields présents');
assert_eq(round(25/30*100,1), $cl['taux_reussite'], 'Taux réussite 6e A = 83.3%');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 11 — dashboardEnseignant
// ─────────────────────────────────────────────────────────────────────────────

section('11. dashboardEnseignant');

$matEnsRows = [
    ['matiere_id'=>1,'matiere_nom'=>'Mathématiques','classe_id'=>1,'classe_nom'=>'6e A','nb_eleves'=>30,'moyenne'=>14.2,'nb_passants'=>28],
    ['matiere_id'=>1,'matiere_nom'=>'Mathématiques','classe_id'=>2,'classe_nom'=>'5e B','nb_eleves'=>28,'moyenne'=>11.8,'nb_passants'=>20],
];
$svc = makeService(['matEnsRows' => $matEnsRows]);
$d = $svc->dashboardEnseignant(7, 1);
assert_eq('enseignant', $d->role,        'Rôle = enseignant');
assert_eq(7, $d->contextId,              'contextId = enseignantId 7');
assert_count(4, $d->kpis,               '4 KPIs enseignant');
assert_count(2, $d->matieresOverview,   '2 lignes matières overview');
assert_true(empty($d->classesOverview), 'classesOverview = vide');

$kpiMap = array_column($d->kpis, null, 'id');
assert_eq(2, $kpiMap['nb_matieres']['value'], 'nb_matieres = 2 lignes');
// Moyenne globale = (14.2+11.8)/2 = 13.0
assert_eq(13.0, $kpiMap['moyenne']['value'], 'Moyenne globale = 13.0');

$ov = $d->matieresOverview[0];
assert_eq(14.2, $ov['moyenne'],              'Overview ligne 1 moyenne = 14.2');
assert_eq(round(28/30*100,1), $ov['taux_reussite'], 'Taux réussite matière 1 = 93.3%');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 12 — dashboardResponsable
// ─────────────────────────────────────────────────────────────────────────────

section('12. dashboardResponsable');

$svc = makeService([
    'classeRows'  => $classeRows,
    'absData'     => ['nb_total'=>100,'nb_absences'=>8,'nb_non_saisis'=>1],
    'distribRows' => $distribRows,
    'topRows'     => $topRows,
    'alerteRows'  => $alerteRows,
]);
$d = $svc->dashboardResponsable(1, '6e');
assert_eq('responsable', $d->role,       'Rôle = responsable');
assert_eq('Niveau 6e', $d->contextLabel, 'ContextLabel = Niveau 6e');
assert_count(5, $d->kpis,               '5 KPIs responsable');
assert_count(2, $d->classesOverview,    '2 classes overview');
assert_count(2, $d->topPerformers,      '2 top performers');
assert_count(1, $d->alertes,            '1 alerte élève');

$d2 = $svc->dashboardResponsable(1);
assert_eq('Tous niveaux', $d2->contextLabel, 'Sans niveau → Tous niveaux');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 13 — exportExcelData
// ─────────────────────────────────────────────────────────────────────────────

section('13. exportExcelData');

$svc = makeService(['classeRows' => $classeRows, 'matiereRows' => $matiereRows]);
$ex = $svc->exportExcelData(1, 'classes');
assert_true(isset($ex['title']) && isset($ex['headers']) && isset($ex['rows']) && isset($ex['summary']) && isset($ex['metadata']), 'Structure export Excel');
assert_count(6, $ex['headers'],  '6 colonnes classes');
assert_count(2, $ex['rows'],     '2 lignes classes');
assert_eq(2, $ex['summary']['total_rows'], 'Summary total_rows = 2');

$exM = $svc->exportExcelData(1, 'matieres');
assert_count(6, $exM['headers'], '6 colonnes matières');
assert_count(2, $exM['rows'],    '2 lignes matières');

$exN = makeService(['niveauRows' => $niveauRows])->exportExcelData(1, 'niveaux');
assert_count(5, $exN['headers'], '5 colonnes niveaux');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 14 — exportPdfData
// ─────────────────────────────────────────────────────────────────────────────

section('14. exportPdfData');

$svc = makeService(['classeRows' => $classeRows, 'etabData' => $etabData]);
$pdf = $svc->exportPdfData(1, 'classes');
assert_true(isset($pdf['title']) && isset($pdf['sections']) && isset($pdf['metadata']), 'Structure export PDF');
assert_count(2, $pdf['sections'], '2 sections PDF (résumé + données)');
assert_eq('Résumé',   $pdf['sections'][0]['heading'], 'Section 0 = Résumé');
assert_eq('Données',  $pdf['sections'][1]['heading'], 'Section 1 = Données');
assert_true(isset($pdf['sections'][0]['content']['Moyenne générale']), 'Moyenne dans résumé');

// ─────────────────────────────────────────────────────────────────────────────
//  Section 15 — getWidgets
// ─────────────────────────────────────────────────────────────────────────────

section('15. getWidgets');

$svc = makeService([
    'etabData'    => $etabData,
    'distribRows' => $distribRows,
    'topRows'     => $topRows,
    'alerteRows'  => $alerteRows,
    'statsEtab'   => ['nb_classes'=>2,'nb_eleves'=>58,'nb_niveaux'=>2],
]);
$w = $svc->getWidgets(1);
assert_true(isset($w['kpi_moyenne']) && isset($w['kpi_nb_eleves']) && isset($w['kpi_nb_notes']) && isset($w['mini_distrib']) && isset($w['top5']) && isset($w['alertes5']), 'Widgets tous présents');
assert_eq(12.35, $w['kpi_moyenne']['value'],    'Widget moyenne = 12.35');
assert_eq(58,    $w['kpi_nb_eleves']['value'],  'Widget nb_eleves = 58');
assert_count(5,  $w['mini_distrib'],             '5 tranches dans mini_distrib');
assert_count(2,  $w['top5'],                     '2 top5');
assert_count(1,  $w['alertes5'],                 '1 alerte');

// ─────────────────────────────────────────────────────────────────────────────
//  Résultat final
// ─────────────────────────────────────────────────────────────────────────────

echo "\n══════════════════════════════════════════\n";
echo "Résultat : $ok OK  /  " . ($ok + $fail) . " tests\n";
if ($fail > 0) echo "ÉCHECS   : $fail\n";
echo "══════════════════════════════════════════\n";
exit($fail > 0 ? 1 : 0);
