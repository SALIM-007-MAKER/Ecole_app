<?php

namespace App\Modules\VieScolaire\Presences\Policies;

class AttendancePolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.session.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.session.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.session.update');
    }

    public function canValidate(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.session.validate');
    }

    public function canViewStats(array $user): bool
    {
        return $this->hasPermission($user, 'attendance.session.view');
    }

    /** A validated session cannot be modified. */
    public function canModifySession(array $user, array $session): bool
    {
        if ($session['statut'] === 'valide') {
            return false;
        }
        return $this->canUpdate($user);
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
