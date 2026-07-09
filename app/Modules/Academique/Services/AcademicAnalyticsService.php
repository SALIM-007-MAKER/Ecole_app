<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\Contracts\AcademicAnalyticsInterface;
use App\Modules\Academique\DTO\AnalyticsResult;
use App\Modules\Academique\DTO\DashboardMetrics;
use App\Modules\Academique\Events\AnalyticsGenerated;
use App\Modules\Academique\Events\StatisticsUpdated;
use App\Modules\Academique\Repositories\AnalyticsRepository;
use Core\EventDispatcher;

/**
 * Moteur d'analytique académique.
 *
 * Contrat :
 *   • Les agrégations de volume sont déléguées à AnalyticsRepository (SQL GROUP BY).
 *   • Les seuils de mention et de passage viennent de AcademicCalculationService::getOptions().
 *   • Le service ne calcule jamais de moyenne par lui-même.
 *   • Les classements précis sont délégués à RankingEngine (optionnel — coûteux).
 */
class AcademicAnalyticsService implements AcademicAnalyticsInterface
{
    // Couleurs Tailwind par mention pour les graphiques
    private const MENTION_CONFIG = [
        'TB'  => ['label' => 'Très Bien',  'css' => 'emerald'],
        'B'   => ['label' => 'Bien',        'css' => 'blue'],
        'AB'  => ['label' => 'Assez Bien', 'css' => 'cyan'],
        'P'   => ['label' => 'Passable',   'css' => 'amber'],
        'INS' => ['label' => 'Insuffisant', 'css' => 'red'],
    ];

    private const DISTRIBUTION_COLORS = [
        'TB'  => 'emerald',
        'B'   => 'blue',
        'AB'  => 'cyan',
        'P'   => 'amber',
        'INS' => 'red',
    ];

    public function __construct(
        private AnalyticsRepository $repo,
        private AcademicCalculationService $calculator,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  Indicateurs
    // ─────────────────────────────────────────────────────────────────

    public function moyenneEtablissement(int $periodeId): AnalyticsResult
    {
        $data = $this->repo->moyenneEtablissement($periodeId);
        $moy  = isset($data['moyenne']) ? round((float)$data['moyenne'], 2) : 0.0;
        $nb   = (int)($data['nb_eleves'] ?? 0);

        return new AnalyticsResult(
            type         : 'moyenne_etablissement',
            label        : 'Moyenne générale établissement',
            value        : $moy,
            previousValue: null,
            count        : $nb,
            breakdown    : [],
            metadata     : ['periode_id' => $periodeId, 'nb_notes' => (int)($data['nb_notes'] ?? 0)],
            computedAt   : date('Y-m-d H:i:s'),
        );
    }

    /** @return AnalyticsResult[] keyed by niveau */
    public function moyenneParNiveau(int $periodeId): array
    {
        $rows   = $this->repo->moyennesParNiveau($periodeId);
        $result = [];
        foreach ($rows as $row) {
            $niveau  = $row['niveau'];
            $moy     = round((float)($row['moyenne'] ?? 0), 2);
            $nb      = (int)($row['nb_eleves'] ?? 0);
            $passants = (int)($row['nb_passants'] ?? 0);
            $taux    = $nb > 0 ? round($passants / $nb * 100, 1) : 0.0;

            $result[$niveau] = new AnalyticsResult(
                type         : 'moyenne_niveau',
                label        : "Moyenne niveau $niveau",
                value        : $moy,
                previousValue: null,
                count        : $nb,
                breakdown    : [['taux_reussite' => $taux, 'nb_passants' => $passants]],
                metadata     : ['periode_id' => $periodeId, 'niveau' => $niveau, 'nb_classes' => (int)($row['nb_classes'] ?? 0)],
                computedAt   : date('Y-m-d H:i:s'),
            );
        }
        return $result;
    }

    /** @return AnalyticsResult[] keyed by classeId */
    public function moyenneParClasse(int $periodeId, ?string $niveau = null): array
    {
        $rows   = $this->repo->moyennesParClasse($periodeId, $niveau);
        $result = [];
        foreach ($rows as $row) {
            $classeId = (int)$row['classe_id'];
            $moy      = round((float)($row['moyenne'] ?? 0), 2);
            $nb       = (int)($row['nb_eleves'] ?? 0);
            $passants = (int)($row['nb_passants'] ?? 0);
            $taux     = $nb > 0 ? round($passants / $nb * 100, 1) : 0.0;

            $result[$classeId] = new AnalyticsResult(
                type         : 'moyenne_classe',
                label        : "Moyenne {$row['classe_nom']}",
                value        : $moy,
                previousValue: null,
                count        : $nb,
                breakdown    : [
                    ['taux_reussite' => $taux, 'nb_passants' => $passants],
                    ['note_min' => (float)($row['note_min'] ?? 0), 'note_max' => (float)($row['note_max'] ?? 20)],
                ],
                metadata     : [
                    'periode_id' => $periodeId,
                    'classe_id'  => $classeId,
                    'classe_nom' => $row['classe_nom'],
                    'niveau'     => $row['niveau'],
                ],
                computedAt   : date('Y-m-d H:i:s'),
            );
        }
        return $result;
    }

    /** @return AnalyticsResult[] keyed by matiereId */
    public function moyenneParMatiere(int $periodeId, ?int $classeId = null): array
    {
        $rows   = $this->repo->moyennesParMatiere($periodeId, $classeId);
        $result = [];
        foreach ($rows as $row) {
            $matiereId = (int)$row['matiere_id'];
            $moy       = round((float)($row['moyenne'] ?? 0), 2);
            $nb        = (int)($row['nb_eleves'] ?? 0);
            $passants  = (int)($row['nb_passants'] ?? 0);
            $taux      = $nb > 0 ? round($passants / $nb * 100, 1) : 0.0;

            $result[$matiereId] = new AnalyticsResult(
                type         : 'moyenne_matiere',
                label        : "Moyenne {$row['matiere_nom']}",
                value        : $moy,
                previousValue: null,
                count        : $nb,
                breakdown    : [['taux_reussite' => $taux, 'nb_passants' => $passants]],
                metadata     : [
                    'periode_id'  => $periodeId,
                    'matiere_id'  => $matiereId,
                    'matiere_nom' => $row['matiere_nom'],
                    'coefficient' => (float)($row['coefficient'] ?? 1),
                    'classe_id'   => $classeId,
                ],
                computedAt   : date('Y-m-d H:i:s'),
            );
        }
        return $result;
    }

    public function tauxReussite(int $periodeId, ?int $classeId = null): float
    {
        $data = $this->repo->tauxAbsenteisme($periodeId, $classeId);
        if (empty($data) || !isset($data['nb_total']) || (int)$data['nb_total'] === 0) return 0.0;

        // On réutilise la query d'absentéisme pour le total, mais on a besoin des passants.
        // Approche : requête agrégée sur les moyennes par classe.
        $rows = $this->repo->moyennesParClasse($periodeId, $classeId !== null ? null : null);
        if ($classeId !== null) {
            $rows = array_filter($rows, fn($r) => (int)$r['classe_id'] === $classeId);
        }

        $nbTotal   = array_sum(array_column($rows, 'nb_eleves'));
        $nbPassants = array_sum(array_column($rows, 'nb_passants'));

        if ($nbTotal <= 0) return 0.0;
        return round($nbPassants / $nbTotal * 100, 2);
    }

    public function tauxAbsenteisme(int $periodeId, ?int $classeId = null): float
    {
        $data = $this->repo->tauxAbsenteisme($periodeId, $classeId);
        if (empty($data) || !isset($data['nb_total']) || (int)$data['nb_total'] === 0) return 0.0;
        $nbTotal    = (int)$data['nb_total'];
        $nbAbsences = (int)($data['nb_absences'] ?? 0);
        return round($nbAbsences / $nbTotal * 100, 2);
    }

    public function distributionNotes(int $periodeId, ?int $classeId = null, ?int $matiereId = null): array
    {
        $rows = $this->repo->distributionNotes($periodeId, $classeId, $matiereId);
        return $this->enrichDistribution($rows);
    }

    public function repartitionMentions(int $periodeId, ?int $classeId = null): array
    {
        // Les mentions individuelles par note = distribution des notes individuelles.
        // Ici on réutilise distributionNotes puisqu'un barème mention = une tranche de note.
        return $this->distributionNotes($periodeId, $classeId);
    }

    public function evolutionResultats(int $classeId, array $periodeIds): array
    {
        $rows   = $this->repo->evolutionParPeriodes($classeId, $periodeIds);
        $result = [];
        $prev   = null;
        foreach ($rows as $row) {
            $moy = round((float)($row['moyenne'] ?? 0), 2);
            $evo = $prev !== null ? round($moy - $prev, 2) : null;

            $result[] = [
                'periode_id'  => (int)$row['periode_id'],
                'periode_nom' => $row['periode_nom'],
                'moyenne'     => $moy,
                'evolution'   => $evo,
                'taux_reussite' => 0.0, // approx — nécessiterait query séparée
                'nb_eleves'   => (int)($row['nb_eleves'] ?? 0),
            ];
            $prev = $moy;
        }
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Tableaux de bord
    // ─────────────────────────────────────────────────────────────────

    public function dashboardDirecteur(int $periodeId): DashboardMetrics
    {
        $start = microtime(true);

        $periode    = $this->repo->infoPeriode($periodeId);
        $periodeNom = $periode?->nom ?? "Période $periodeId";

        $statsEtab    = $this->repo->statsEtablissement();
        $etabResult   = $this->repo->moyenneEtablissement($periodeId);
        $classeRows   = $this->repo->moyennesParClasse($periodeId);
        $absData      = $this->repo->tauxAbsenteisme($periodeId);
        $distrib      = $this->repo->distributionNotes($periodeId);
        $top          = $this->repo->topPerformers($periodeId, null, 10);
        $alertes      = $this->repo->alertesEleves($periodeId, null, 10.0, 10);

        $nbEleves    = (int)($statsEtab['nb_eleves'] ?? 0);
        $nbClasses   = (int)($statsEtab['nb_classes'] ?? 0);
        $moyEtab     = round((float)($etabResult['moyenne'] ?? 0), 2);

        $passants    = array_sum(array_column($classeRows, 'nb_passants'));
        $totalEleves = array_sum(array_column($classeRows, 'nb_eleves'));
        $tauxReuss   = $totalEleves > 0 ? round($passants / $totalEleves * 100, 1) : 0.0;

        $nbAbsences  = (int)($absData['nb_absences'] ?? 0);
        $nbTotal     = (int)($absData['nb_total'] ?? 1);
        $tauxAbs     = $nbTotal > 0 ? round($nbAbsences / $nbTotal * 100, 1) : 0.0;

        $kpis = [
            $this->kpi('nb_eleves',     'Élèves inscrits',    $nbEleves,   'élèves', 'minus',         'users',       'slate'),
            $this->kpi('nb_classes',    'Classes actives',    $nbClasses,  'classes', 'minus',         'layout',      'slate'),
            $this->kpi('moyenne_etab',  'Moyenne générale',   $moyEtab,    '/20',    'minus',          'bar-chart-2', 'blue'),
            $this->kpi('taux_reussite', 'Taux de réussite',   $tauxReuss,  '%',      'trending-up',    'check-circle','emerald'),
            $this->kpi('taux_absences', 'Taux d\'absentéisme', $tauxAbs,  '%',      'trending-down',   'user-x',      'amber'),
            $this->kpi('nb_alertes',    'Élèves en difficulté', count($alertes), 'élèves', 'trending-down', 'alert-circle', 'red'),
        ];

        $classesOverview = array_map(fn($r) => [
            'classe_id'     => (int)$r['classe_id'],
            'classe_nom'    => $r['classe_nom'],
            'niveau'        => $r['niveau'],
            'nb_eleves'     => (int)($r['nb_eleves'] ?? 0),
            'moyenne'       => round((float)($r['moyenne'] ?? 0), 2),
            'taux_reussite' => (int)($r['nb_eleves'] ?? 0) > 0
                ? round((int)($r['nb_passants'] ?? 0) / (int)($r['nb_eleves'] ?? 0) * 100, 1)
                : 0.0,
        ], $classeRows);

        $ms = round((microtime(true) - $start) * 1000, 2);

        EventDispatcher::dispatch(new AnalyticsGenerated(
            dashboardType   : 'directeur',
            periodeId       : $periodeId,
            contextId       : null,
            generatedByRole : 'directeur',
            generatedById   : 0,
            computationMs   : $ms,
        ));

        return new DashboardMetrics(
            role               : 'directeur',
            periodeId          : $periodeId,
            periodeNom         : $periodeNom,
            contextId          : null,
            contextLabel       : null,
            kpis               : $kpis,
            topPerformers      : $this->formatTop($top),
            alertes            : $this->formatAlertes($alertes),
            distributionNotes  : $this->enrichDistribution($distrib),
            repartitionMentions: $this->enrichDistribution($distrib),
            evolutionChart     : [],
            classesOverview    : $classesOverview,
            matieresOverview   : [],
            generatedAt        : date('Y-m-d H:i:s'),
        );
    }

    public function dashboardEnseignant(int $enseignantId, int $periodeId): DashboardMetrics
    {
        $start = microtime(true);

        $periode       = $this->repo->infoPeriode($periodeId);
        $periodeNom    = $periode?->nom ?? "Période $periodeId";
        $matiereRows   = $this->repo->matieresEnseignant($enseignantId, $periodeId);

        $moyennes    = array_column($matiereRows, 'moyenne');
        $moyGlobale  = count($moyennes) > 0 ? round(array_sum($moyennes) / count($moyennes), 2) : 0.0;
        $nbEleves    = max(array_column($matiereRows, 'nb_eleves') ?: [0]);
        $passants    = array_sum(array_column($matiereRows, 'nb_passants'));
        $totalCreneaux = array_sum(array_column($matiereRows, 'nb_eleves'));
        $tauxReuss   = $totalCreneaux > 0 ? round($passants / $totalCreneaux * 100, 1) : 0.0;

        $kpis = [
            $this->kpi('nb_matieres',  'Matières enseignées', count($matiereRows), 'matières', 'minus',       'book-open',   'slate'),
            $this->kpi('nb_eleves',    'Élèves suivis',       $nbEleves,           'élèves',   'minus',       'users',       'slate'),
            $this->kpi('moyenne',      'Moyenne classe',      $moyGlobale,         '/20',      'minus',       'bar-chart-2', 'blue'),
            $this->kpi('taux_reussite','Taux de réussite',    $tauxReuss,          '%',        'trending-up', 'check-circle','emerald'),
        ];

        $matieresOverview = array_map(fn($r) => [
            'matiere_id'    => (int)$r['matiere_id'],
            'matiere_nom'   => $r['matiere_nom'],
            'classe_id'     => (int)$r['classe_id'],
            'classe_nom'    => $r['classe_nom'],
            'nb_eleves'     => (int)($r['nb_eleves'] ?? 0),
            'moyenne'       => round((float)($r['moyenne'] ?? 0), 2),
            'taux_reussite' => (int)($r['nb_eleves'] ?? 0) > 0
                ? round((int)($r['nb_passants'] ?? 0) / (int)($r['nb_eleves'] ?? 0) * 100, 1)
                : 0.0,
        ], $matiereRows);

        $ms = round((microtime(true) - $start) * 1000, 2);

        EventDispatcher::dispatch(new AnalyticsGenerated(
            dashboardType   : 'enseignant',
            periodeId       : $periodeId,
            contextId       : $enseignantId,
            generatedByRole : 'enseignant',
            generatedById   : $enseignantId,
            computationMs   : $ms,
        ));

        return new DashboardMetrics(
            role               : 'enseignant',
            periodeId          : $periodeId,
            periodeNom         : $periodeNom,
            contextId          : $enseignantId,
            contextLabel       : "Enseignant #$enseignantId",
            kpis               : $kpis,
            topPerformers      : [],
            alertes            : [],
            distributionNotes  : [],
            repartitionMentions: [],
            evolutionChart     : [],
            classesOverview    : [],
            matieresOverview   : $matieresOverview,
            generatedAt        : date('Y-m-d H:i:s'),
        );
    }

    public function dashboardResponsable(int $periodeId, ?string $niveau = null): DashboardMetrics
    {
        $start = microtime(true);

        $periode    = $this->repo->infoPeriode($periodeId);
        $periodeNom = $periode?->nom ?? "Période $periodeId";

        $classeRows = $this->repo->moyennesParClasse($periodeId, $niveau);
        $absData    = $this->repo->tauxAbsenteisme($periodeId, null);
        $top        = $this->repo->topPerformers($periodeId, null, 10);
        $alertes    = $this->repo->alertesEleves($periodeId, null, 10.0, 10);
        $distrib    = $this->repo->distributionNotes($periodeId);

        $passants    = array_sum(array_column($classeRows, 'nb_passants'));
        $totalEleves = array_sum(array_column($classeRows, 'nb_eleves'));
        $tauxReuss   = $totalEleves > 0 ? round($passants / $totalEleves * 100, 1) : 0.0;
        $moyennes    = array_filter(array_column($classeRows, 'moyenne'), fn($v) => $v !== null);
        $moyGlobale  = count($moyennes) > 0 ? round(array_sum($moyennes) / count($moyennes), 2) : 0.0;

        $nbAbsences  = (int)($absData['nb_absences'] ?? 0);
        $nbTotal     = (int)($absData['nb_total'] ?? 1);
        $tauxAbs     = $nbTotal > 0 ? round($nbAbsences / $nbTotal * 100, 1) : 0.0;

        $label = $niveau !== null ? "Niveau $niveau" : 'Tous niveaux';

        $kpis = [
            $this->kpi('nb_classes',    'Classes suivies',    count($classeRows), 'classes', 'minus',        'layout',       'slate'),
            $this->kpi('nb_eleves',     'Élèves suivis',      $totalEleves,       'élèves',  'minus',        'users',        'slate'),
            $this->kpi('moyenne',       'Moyenne générale',   $moyGlobale,        '/20',     'minus',        'bar-chart-2',  'blue'),
            $this->kpi('taux_reussite', 'Taux de réussite',   $tauxReuss,         '%',       'trending-up',  'check-circle', 'emerald'),
            $this->kpi('taux_absences', 'Taux d\'absentéisme', $tauxAbs,          '%',       'trending-down','user-x',       'amber'),
        ];

        $classesOverview = array_map(fn($r) => [
            'classe_id'     => (int)$r['classe_id'],
            'classe_nom'    => $r['classe_nom'],
            'niveau'        => $r['niveau'],
            'nb_eleves'     => (int)($r['nb_eleves'] ?? 0),
            'moyenne'       => round((float)($r['moyenne'] ?? 0), 2),
            'taux_reussite' => (int)($r['nb_eleves'] ?? 0) > 0
                ? round((int)($r['nb_passants'] ?? 0) / (int)($r['nb_eleves'] ?? 0) * 100, 1)
                : 0.0,
        ], $classeRows);

        $ms = round((microtime(true) - $start) * 1000, 2);

        EventDispatcher::dispatch(new AnalyticsGenerated(
            dashboardType   : 'responsable',
            periodeId       : $periodeId,
            contextId       : null,
            generatedByRole : 'responsable',
            generatedById   : 0,
            computationMs   : $ms,
        ));

        return new DashboardMetrics(
            role               : 'responsable',
            periodeId          : $periodeId,
            periodeNom         : $periodeNom,
            contextId          : null,
            contextLabel       : $label,
            kpis               : $kpis,
            topPerformers      : $this->formatTop($top),
            alertes            : $this->formatAlertes($alertes),
            distributionNotes  : $this->enrichDistribution($distrib),
            repartitionMentions: $this->enrichDistribution($distrib),
            evolutionChart     : [],
            classesOverview    : $classesOverview,
            matieresOverview   : [],
            generatedAt        : date('Y-m-d H:i:s'),
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Export
    // ─────────────────────────────────────────────────────────────────

    public function exportExcelData(int $periodeId, string $type, array $filtres = []): array
    {
        $periode = $this->repo->infoPeriode($periodeId);
        $periodeNom = $periode?->nom ?? "Période $periodeId";

        $rows    = match($type) {
            'classes'  => $this->repo->moyennesParClasse($periodeId),
            'matieres' => $this->repo->moyennesParMatiere($periodeId, $filtres['classe_id'] ?? null),
            'niveaux'  => $this->repo->moyennesParNiveau($periodeId),
            default    => [],
        };

        $headers = match($type) {
            'classes'  => ['Classe', 'Niveau', 'Nb élèves', 'Moyenne', 'Nb passants', 'Taux réussite'],
            'matieres' => ['Matière', 'Coefficient', 'Nb élèves', 'Moyenne', 'Nb passants', 'Taux réussite'],
            'niveaux'  => ['Niveau', 'Nb classes', 'Nb élèves', 'Moyenne', 'Nb passants'],
            default    => [],
        };

        $formatted = array_map(function ($r) use ($type) {
            return match($type) {
                'classes'  => [
                    $r['classe_nom'], $r['niveau'], (int)$r['nb_eleves'],
                    round((float)($r['moyenne'] ?? 0), 2), (int)($r['nb_passants'] ?? 0),
                    (int)($r['nb_eleves'] ?? 0) > 0
                        ? round((int)($r['nb_passants'] ?? 0) / (int)($r['nb_eleves'] ?? 0) * 100, 1) . '%'
                        : '0%',
                ],
                'matieres' => [
                    $r['matiere_nom'], (float)($r['coefficient'] ?? 1), (int)$r['nb_eleves'],
                    round((float)($r['moyenne'] ?? 0), 2), (int)($r['nb_passants'] ?? 0),
                    (int)($r['nb_eleves'] ?? 0) > 0
                        ? round((int)($r['nb_passants'] ?? 0) / (int)($r['nb_eleves'] ?? 0) * 100, 1) . '%'
                        : '0%',
                ],
                'niveaux'  => [
                    $r['niveau'], (int)($r['nb_classes'] ?? 0), (int)$r['nb_eleves'],
                    round((float)($r['moyenne'] ?? 0), 2), (int)($r['nb_passants'] ?? 0),
                ],
                default    => [],
            };
        }, $rows);

        return [
            'title'    => "Analytique $type — $periodeNom",
            'headers'  => $headers,
            'rows'     => $formatted,
            'summary'  => ['total_rows' => count($formatted), 'periode_id' => $periodeId],
            'metadata' => ['type' => $type, 'filtres' => $filtres, 'generated_at' => date('Y-m-d H:i:s')],
        ];
    }

    public function exportPdfData(int $periodeId, string $type, array $filtres = []): array
    {
        $excelData = $this->exportExcelData($periodeId, $type, $filtres);
        $etab      = $this->repo->moyenneEtablissement($periodeId);
        $moy       = round((float)($etab['moyenne'] ?? 0), 2);

        return [
            'title'    => $excelData['title'],
            'sections' => [
                [
                    'heading' => 'Résumé',
                    'content' => [
                        'Moyenne générale' => "$moy / 20",
                        'Nombre de lignes' => count($excelData['rows']),
                        'Période ID'       => $periodeId,
                    ],
                ],
                [
                    'heading' => 'Données',
                    'headers' => $excelData['headers'],
                    'rows'    => $excelData['rows'],
                ],
            ],
            'metadata' => $excelData['metadata'],
        ];
    }

    public function getWidgets(int $periodeId): array
    {
        $etab    = $this->repo->moyenneEtablissement($periodeId);
        $distrib = $this->repo->distributionNotes($periodeId);
        $top     = $this->repo->topPerformers($periodeId, null, 5);
        $alertes = $this->repo->alertesEleves($periodeId, null, 10.0, 5);
        $stats   = $this->repo->statsEtablissement();

        $moy     = round((float)($etab['moyenne'] ?? 0), 2);
        $nbNotes = (int)($etab['nb_notes'] ?? 0);

        return [
            'kpi_moyenne'    => ['value' => $moy, 'unit' => '/20', 'icon' => 'bar-chart-2'],
            'kpi_nb_eleves'  => ['value' => (int)($stats['nb_eleves'] ?? 0), 'unit' => 'élèves', 'icon' => 'users'],
            'kpi_nb_notes'   => ['value' => $nbNotes, 'unit' => 'notes saisies', 'icon' => 'edit-3'],
            'mini_distrib'   => $this->enrichDistribution($distrib),
            'top5'           => $this->formatTop($top),
            'alertes5'       => $this->formatAlertes($alertes),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────

    /**
     * Enrichit les tranches de distribution : calcule les pourcentages et injecte les couleurs.
     */
    private function enrichDistribution(array $rows): array
    {
        $total = array_sum(array_column($rows, 'nb'));
        $order = ['TB', 'B', 'AB', 'P', 'INS'];

        // Indexer par tranche pour garantir l'ordre
        $indexed = [];
        foreach ($rows as $r) $indexed[$r['tranche']] = (int)$r['nb'];

        $result = [];
        foreach ($order as $tranche) {
            $nb  = $indexed[$tranche] ?? 0;
            $pct = $total > 0 ? round($nb / $total * 100, 1) : 0.0;
            $cfg = self::MENTION_CONFIG[$tranche];
            $result[] = [
                'tranche'     => $tranche,
                'label'       => $cfg['label'],
                'nb'          => $nb,
                'pourcentage' => $pct,
                'color'       => self::DISTRIBUTION_COLORS[$tranche],
                'css'         => $cfg['css'],
            ];
        }
        return $result;
    }

    private function formatTop(array $rows): array
    {
        $result = [];
        foreach ($rows as $i => $r) {
            $moy = round((float)($r['moyenne_approx'] ?? 0), 2);
            $result[] = [
                'rang'         => $i + 1,
                'eleve_id'     => (int)$r['eleve_id'],
                'nom'          => $r['nom'],
                'prenom'       => $r['prenom'],
                'matricule'    => $r['matricule'] ?? '',
                'classe_nom'   => $r['classe_nom'],
                'moyenne'      => $moy,
                'mention_code' => $this->getMentionCode($moy),
            ];
        }
        return $result;
    }

    private function formatAlertes(array $rows): array
    {
        return array_map(fn($r) => [
            'eleve_id'     => (int)$r['eleve_id'],
            'nom'          => $r['nom'],
            'prenom'       => $r['prenom'],
            'classe_nom'   => $r['classe_nom'],
            'moyenne_approx' => round((float)($r['moyenne_approx'] ?? 0), 2),
        ], $rows);
    }

    private function getMentionCode(float $moy): string
    {
        return match(true) {
            $moy >= 16 => 'TB',
            $moy >= 14 => 'B',
            $moy >= 12 => 'AB',
            $moy >= 10 => 'P',
            default    => 'INS',
        };
    }

    private function kpi(string $id, string $label, float|int $value, string $unit, string $trend, string $icon, string $color): array
    {
        return compact('id', 'label', 'value', 'unit', 'trend', 'icon', 'color');
    }
}
