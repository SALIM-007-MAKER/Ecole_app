<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\DTO\KpiDTO;
use App\Modules\Rapports\Repositories\SnapshotRepository;
use App\Modules\Rapports\Events\KpiSnapshot;
use App\Shared\Analytics\KPIEngine;
use Core\EventDispatcher;

class KpiService
{
    private KPIEngine          $engine;
    private SnapshotRepository $snapshots;
    private DataAggregatorService $aggregator;

    public function __construct()
    {
        $this->engine     = new KPIEngine();
        $this->snapshots  = new SnapshotRepository();
        $this->aggregator = new DataAggregatorService();
    }

    public function getKpisDomaine(string $domaine, int $etablissementId, array $filters = []): array
    {
        $data = $this->aggregator->getForDomaine($domaine, $etablissementId, $filters);
        return $this->buildKpisFromData($domaine, $data, $etablissementId);
    }

    private function buildKpisFromData(string $domaine, array $data, int $etab): array
    {
        $kpis    = [];
        $periode = date('Y-m');
        $snap    = fn(string $met) => $this->snapshots->getDernierSnapshot($domaine, $met, $etab);

        switch ($domaine) {
            case 'scolarite':
                $nb = (int)($data['counters']['nb_eleves'] ?? 0);
                $prev = $snap('nb_eleves');
                $var  = $prev ? $this->engine->variation((float)$prev['valeur'], $nb) : null;
                $kpis[] = KpiDTO::make($domaine, 'nb_eleves', $nb, 'élèves', $var, $this->engine->tendance($var ? [0, $var] : []))->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_classes', (int)($data['counters']['nb_classes'] ?? 0), 'classes')->toArray();
                break;

            case 'academique':
                $moy = (float)($data['counters']['moyenne_generale'] ?? 0);
                $kpis[] = KpiDTO::make($domaine, 'moyenne_generale', $moy, '/20')->toArray();
                break;

            case 'finance':
                $kpis[] = KpiDTO::make($domaine, 'total_facture',   (float)($data['counters']['total_facture'] ?? 0), 'FCFA')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'total_paye',      (float)($data['counters']['total_paye']    ?? 0), 'FCFA')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_impayes',      (int)($data['counters']['nb_impayes']      ?? 0), 'factures')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'taux_recouvrement', (float)($data['taux_recouvrement']       ?? 0), '%')->toArray();
                break;

            case 'vie_scolaire':
                $kpis[] = KpiDTO::make($domaine, 'nb_absences',  (int)($data['counters']['nb_absences']  ?? 0), 'absences')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_retards',   (int)($data['counters']['nb_retards']   ?? 0), 'retards')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_incidents', (int)($data['counters']['nb_incidents'] ?? 0), 'incidents')->toArray();
                break;

            case 'rh':
                $kpis[] = KpiDTO::make($domaine, 'nb_employes_actifs', (int)($data['counters']['nb_employes_actifs'] ?? 0), 'employés')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'taux_presence', (float)($data['taux_presence'] ?? 0), '%')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_conges_attente', (int)($data['counters']['nb_conges_attente'] ?? 0), 'congés')->toArray();
                break;

            case 'bibliotheque':
                $kpis[] = KpiDTO::make($domaine, 'nb_ouvrages',       (int)($data['counters']['nb_ouvrages']       ?? 0), 'ouvrages')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_emprunts_cours', (int)($data['counters']['nb_emprunts_cours']  ?? 0), 'en cours')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'taux_retour', (float)($data['taux_retour'] ?? 0), '%')->toArray();
                break;

            case 'inventaire':
                $kpis[] = KpiDTO::make($domaine, 'nb_articles',     (int)($data['counters']['nb_articles']     ?? 0), 'articles')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'valeur_stock',    (float)($data['valeur_stock']              ?? 0), 'FCFA')->toArray();
                $kpis[] = KpiDTO::make($domaine, 'nb_alertes_stock', (int)($data['counters']['nb_alertes_stock'] ?? 0), 'alertes')->toArray();
                break;
        }

        foreach ($kpis as $kpi) {
            EventDispatcher::dispatch(new KpiSnapshot(
                $kpi['domaine'],
                $kpi['metrique'],
                (float)$kpi['valeur'],
                $periode,
                $etab,
            ));
        }

        return $kpis;
    }
}
