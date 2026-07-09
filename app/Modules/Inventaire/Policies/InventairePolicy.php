<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Policies;

class InventairePolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.create');
    }

    public function canEdit(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.edit');
    }

    public function canDelete(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.delete');
    }

    public function canManageStock(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.stock.manage');
    }

    public function canViewReports(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.reports');
    }

    private function hasPermission(array $user, string $permission): bool
    {
        $permissions = $user['permissions'] ?? [];
        return in_array($permission, $permissions, true)
            || in_array('*', $permissions, true);
    }
}
