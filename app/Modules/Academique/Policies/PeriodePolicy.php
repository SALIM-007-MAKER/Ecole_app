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
        if ($periode !== null && ($periode->verrouille_par ?? null) !== null) {
            return $this->hasPermission($user, 'academique.periodes.admin');
        }
        return true;
    }

    /** Édition directe et libre du champ `statut` (hors boutons de cycle de vie guidé) — réservée aux administrateurs. */
    public function canEditStatutDirectement(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canActivate(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canOuvrir(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canCloturer(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.manage')
            || $this->hasPermission($user, 'academique.periodes.admin');
    }

    public function canReouvrir(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.admin');
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
        if ($periode !== null && ($periode->verrouille_par ?? null) !== null) {
            return false;
        }
        return true;
    }

    public function canGererConfig(array $user): bool
    {
        return $this->hasPermission($user, 'academique.periodes.admin');
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
