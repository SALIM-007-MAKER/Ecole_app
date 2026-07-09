<?php

namespace App\Modules\Academique\Policies;

class PeriodePolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.view')
            || $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canUpdate(array $user, ?object $periode = null): bool
    {
        if (!$this->hasPermission($user, 'academique.periodes.manage')
            && !$this->hasPermission($user, 'academique.periodes.admin')) {
            return false;
        }
        if ($periode !== null && $periode->statut === 'verrouillee') {
            return $this->hasPermission($user, 'academique.periodes.admin');
        }
        return true;
    }

    public function canActivate(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canFermer(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canVerrouiller(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canDeverrouiller(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canArchiver(array $user, ?object $periode = null): bool
    {
        if (!$this->hasPermission($user, 'academique.periodes.manage')
            && !$this->hasPermission($user, 'academique.periodes.admin')) {
            return false;
        }
        if ($periode !== null && $periode->statut === 'verrouillee') {
            return false;
        }
        return true;
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
