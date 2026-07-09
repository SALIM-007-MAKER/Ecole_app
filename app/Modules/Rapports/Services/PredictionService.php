<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\Contracts\PredictionInterface;
use App\Modules\Rapports\Repositories\SnapshotRepository;
use App\Shared\Analytics\KPIEngine;

/**
 * STUB V3 — Implémentation IA prédictive par régression linéaire simple.
 * Les modèles IA avancés (ML) sont prévus en Phase V3.
 */
class PredictionService implements PredictionInterface
{
    private SnapshotRepository $snapshots;
    private KPIEngine          $kpi;

    public function __construct()
    {
        $this->snapshots = new SnapshotRepository();
        $this->kpi       = new KPIEngine();
    }

    public function predict(string $modele, array $features): float
    {
        // STUB — régression linéaire basique sur historique snapshots
        return 0.0;
    }

    public function tauxDecrochage(int $eleveId, int $etablissementId): float
    {
        // V3 : features = nb_absences, notes, retards → modèle classification
        return 0.0;
    }

    public function risqueImpaye(int $eleveId, int $etablissementId): float
    {
        // V3 : features = historique paiements, type_frais → régression logistique
        return 0.0;
    }

    public function previsionEffectifs(int $etablissementId, string $anneeScolaire): array
    {
        $historique = $this->snapshots->getMetrique('scolarite', 'nb_eleves', $etablissementId, 24);
        $values = array_map(fn($r) => (float)$r['valeur'], array_reverse($historique));
        if (count($values) < 2) return ['prevision' => 0, 'confiance' => 'faible'];

        $tendance  = $this->kpi->tendance($values);
        $glissante = $this->kpi->moyenneGlissante($values, 3);
        $prevision = end($glissante);

        return [
            'annee_scolaire' => $anneeScolaire,
            'prevision'      => round($prevision),
            'tendance'       => $tendance,
            'confiance'      => 'indicatif',
            'note'           => 'Modèle V3 — régression avancée prévue',
        ];
    }

    public function previsionBudget(int $etablissementId, string $mois): array
    {
        $historique = $this->snapshots->getMetrique('finance', 'total_paye', $etablissementId, 12);
        $values = array_map(fn($r) => (float)$r['valeur'], array_reverse($historique));
        if (count($values) < 2) return ['prevision' => 0.0, 'confiance' => 'faible'];

        $glissante = $this->kpi->moyenneGlissante($values, 3);
        $prevision = end($glissante);

        return [
            'mois'      => $mois,
            'prevision' => round($prevision, 2),
            'confiance' => 'indicatif',
            'note'      => 'Modèle V3 — ARIMA / Prophet prévus',
        ];
    }
}
