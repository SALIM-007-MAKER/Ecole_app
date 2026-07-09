<?php

namespace App\Modules\Finance\Policies;

class FinancialReportPolicy
{
    public function canView(array $user): bool
    {
        return in_array('finance.reports.view', $user['permissions'] ?? [], true)
            || $this->canExport($user)
            || $this->canPrint($user);
    }

    public function canExport(array $user): bool
    {
        return in_array('finance.reports.export', $user['permissions'] ?? [], true);
    }

    public function canPrint(array $user): bool
    {
        return in_array('finance.reports.print', $user['permissions'] ?? [], true)
            || $this->canExport($user);
    }

    public function canViewDashboard(array $user): bool
    {
        return $this->canView($user)
            || in_array('finance.dashboard.view', $user['permissions'] ?? [], true);
    }

    public function canViewComptable(array $user): bool
    {
        return $this->canView($user)
            || in_array('finance.comptabilite.view', $user['permissions'] ?? [], true);
    }
}
