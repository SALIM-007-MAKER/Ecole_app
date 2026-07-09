<?php

namespace App\Modules\RH\Employes\Policies;

class EmployeePolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'employee.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'employee.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->hasPermission($user, 'employee.update');
    }

    public function canArchive(array $user): bool
    {
        return $this->hasPermission($user, 'employee.archive');
    }

    public function canRestore(array $user): bool
    {
        return $this->hasPermission($user, 'employee.restore');
    }

    public function canExport(array $user): bool
    {
        return $this->hasPermission($user, 'employee.export');
    }

    public function canViewOwn(array $user, int $employeUserId): bool
    {
        return (int)($user['id'] ?? 0) === $employeUserId || $this->canView($user);
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
