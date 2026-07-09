<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Policies;

use Core\Auth;

class RapportPolicy
{
    public function voirDashboard(string $contexte): bool
    {
        $permMap = [
            'direction'    => 'rapports.dashboard.direction',
            'administration' => 'rapports.dashboard.administration',
            'scolarite'    => 'rapports.dashboard.scolarite',
            'academique'   => 'rapports.dashboard.academique',
            'finance'      => 'rapports.dashboard.finance',
            'rh'           => 'rapports.dashboard.rh',
            'vie_scolaire' => 'rapports.dashboard.vie_scolaire',
            'bibliotheque' => 'rapports.dashboard.bibliotheque',
            'inventaire'   => 'rapports.dashboard.inventaire',
        ];
        $perm = $permMap[$contexte] ?? 'rapports.dashboard.direction';
        return Auth::hasPermission($perm) || Auth::hasPermission('rapports.dashboard.direction');
    }

    public function voirKpis(): bool
    {
        return Auth::hasPermission('rapports.kpis.voir');
    }

    public function exporter(): bool
    {
        return Auth::hasPermission('rapports.exporter');
    }

    public function planifier(): bool
    {
        return Auth::hasPermission('rapports.planifier');
    }

    public function gererPlanifications(): bool
    {
        return Auth::hasPermission('rapports.planifier');
    }

    public function voirSnapshots(): bool
    {
        return Auth::hasPermission('rapports.kpis.voir');
    }

    public function voirApiAnalytics(): bool
    {
        return Auth::hasPermission('rapports.api.analytics');
    }
}
