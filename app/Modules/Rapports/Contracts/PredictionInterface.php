<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Contracts;

/**
 * STUB V3 — IA prédictive.
 * 5 modèles prévus : décrochage, impayé, effectifs, EDT, budget.
 */
interface PredictionInterface
{
    public function predict(string $modele, array $features): float;

    public function tauxDecrochage(int $eleveId, int $etablissementId): float;

    public function risqueImpaye(int $eleveId, int $etablissementId): float;

    public function previsionEffectifs(int $etablissementId, string $anneeScolaire): array;

    public function previsionBudget(int $etablissementId, string $mois): array;
}
