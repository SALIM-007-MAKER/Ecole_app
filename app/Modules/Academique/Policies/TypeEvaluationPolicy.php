<?php

namespace App\Modules\Academique\Policies;

class TypeEvaluationPolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'academique.types_evaluations.view')
            || $this->hasPermission($user, 'academique.types_evaluations.manage');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'academique.types_evaluations.manage');
    }

    public function canUpdate(array $user, ?\stdClass $type = null): bool
    {
        if (!$this->hasPermission($user, 'academique.types_evaluations.manage')) {
            return false;
        }
        if ($type && (int)$type->est_archive) {
            return false;
        }
        // Types système : modification réservée aux admins
        if ($type && (int)$type->est_systeme) {
            return $this->hasPermission($user, 'academique.types_evaluations.admin');
        }
        return true;
    }

    public function canActivate(array $user, ?\stdClass $type = null): bool
    {
        if (!$this->hasPermission($user, 'academique.types_evaluations.manage')) {
            return false;
        }
        if (!$type) {
            return false;
        }
        return !(int)$type->actif && !(int)$type->est_archive;
    }

    public function canDeactivate(array $user, ?\stdClass $type = null): bool
    {
        if (!$this->hasPermission($user, 'academique.types_evaluations.manage')) {
            return false;
        }
        if (!$type || !(int)$type->actif || (int)$type->est_archive) {
            return false;
        }
        // Désactivation d'un type système : admin uniquement
        if ((int)$type->est_systeme) {
            return $this->hasPermission($user, 'academique.types_evaluations.admin');
        }
        return true;
    }

    public function canArchiver(array $user, ?\stdClass $type = null): bool
    {
        if (!$this->hasPermission($user, 'academique.types_evaluations.admin')) {
            return false;
        }
        if (!$type || (int)$type->est_archive) {
            return false;
        }
        return true;
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
