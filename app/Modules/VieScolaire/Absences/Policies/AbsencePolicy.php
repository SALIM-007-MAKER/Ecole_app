<?php

namespace App\Modules\VieScolaire\Absences\Policies;

class AbsencePolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.update');
    }

    public function canDelete(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.delete');
    }

    public function canJustify(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.justify');
    }

    public function canValidate(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.validate');
    }

    public function canViewStats(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.view');
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
