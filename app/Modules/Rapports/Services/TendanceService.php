<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\Repositories\SnapshotRepository;
use App\Shared\Analytics\KPIEngine;
use App\Shared\Analytics\ChartEngine;

class TendanceService
{
    private SnapshotRepository $snapshots;
    private KPIEngine          $kpi;
    private ChartEngine        $charts;

    public function __construct()
    {
        $this->snapshots = new SnapshotRepository();
        $this->kpi       = new KPIEngine();
        $this->charts    = new ChartEngine();
    }

    public function analyserMetrique(string $domaine, string $metrique, int $etablissementId, int $nbPeriodes = 12): array
    {
        $historique = $this->snapshots->getMetrique($domaine, $metrique, $etablissementId, $nbPeriodes);
        // DESC → on retourne pour avoir ASC dans le graphe
        $historique = array_reverse($historique);
        $labels   = array_column($historique, 'periode');
        $values   = array_map(fn($r) => (float)$r['valeur'], $historique);
        $glissante = $this->kpi->moyenneGlissante($values, 3);
        $tendance  = $this->kpi->tendance($values);
        $variation = count($values) >= 2
            ? $this->kpi->variation($values[0], end($values))
            : null;

        $chart = $this->charts->line($labels, [
            ['label' => ucfirst(str_replace('_', ' ', $metrique)), 'data' => $values],
            ['label' => 'Moy. glissante (3)', 'data' => $glissante, 'borderDash' => [5, 5]],
        ], ['titre' => "Tendance — $metrique"]);

        return [
            'domaine'   => $domaine,
            'metrique'  => $metrique,
            'tendance'  => $tendance,
            'variation' => $variation,
            'labels'    => $labels,
            'values'    => $values,
            'glissante' => $glissante,
            'chart'     => $chart,
        ];
    }

    public function comparerPeriodes(
        string $domaine,
        string $metrique,
        int    $etablissementId,
        string $periodeA,
        string $periodeB,
    ): array {
        $rowA = $this->snapshots->getSnapshotPeriode($domaine, $metrique, $periodeA, $etablissementId)
              ?? ['valeur' => 0];
        $rowB = $this->snapshots->getSnapshotPeriode($domaine, $metrique, $periodeB, $etablissementId)
              ?? ['valeur' => 0];

        $valA = (float)$rowA['valeur'];
        $valB = (float)$rowB['valeur'];
        return [
            'periode_a' => $periodeA,
            'valeur_a'  => $valA,
            'periode_b' => $periodeB,
            'valeur_b'  => $valB,
            'variation' => $this->kpi->variation($valA, $valB),
        ];
    }

    public function alertesKpi(string $domaine, int $etablissementId): array
    {
        $seuilsAlertes = [
            'finance'      => [['metrique' => 'taux_recouvrement', 'seuil' => 70.0,  'direction' => 'below']],
            'vie_scolaire' => [['metrique' => 'nb_absences',       'seuil' => 50,    'direction' => 'above']],
            'academique'   => [['metrique' => 'moyenne_generale',  'seuil' => 10.0,  'direction' => 'below']],
            'inventaire'   => [['metrique' => 'nb_alertes_stock',  'seuil' => 0,     'direction' => 'above']],
        ];

        $alertes = [];
        foreach ($seuilsAlertes[$domaine] ?? [] as $cfg) {
            $snap = $this->snapshots->getDernierSnapshot($domaine, $cfg['metrique'], $etablissementId);
            if (!$snap) continue;
            if ($this->kpi->alerteSeuil((float)$snap['valeur'], (float)$cfg['seuil'], $cfg['direction'])) {
                $alertes[] = [
                    'metrique'  => $cfg['metrique'],
                    'valeur'    => $snap['valeur'],
                    'seuil'     => $cfg['seuil'],
                    'direction' => $cfg['direction'],
                    'periode'   => $snap['periode'],
                ];
            }
        }
        return $alertes;
    }
}
