<?php

namespace App\Modules\Academique\Policies;

class EvaluationPolicy
{
    public function canView(array $user): bool
    {
        return $this->hasPermission($user, 'academique.evaluations.view')
            || $this->hasPermission($user, 'academique.evaluations.manage');
    }

    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'academique.evaluations.manage');
    }

    public function canUpdate(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        if (!$evaluation) {
            return true;
        }
        // Archivée : aucune modification possible
        if ($evaluation->statut === 'archivee') {
            return false;
        }
        // Verrouillée : admin uniquement
        if ($evaluation->statut === 'verrouillee') {
            return $this->hasPermission($user, 'academique.evaluations.admin');
        }
        return true;
    }

    public function canPublish(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'brouillon';
    }

    public function canVerrouiller(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.manage')) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'publiee';
    }

    public function canDeverrouiller(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.admin')) {
            return false;
        }
        return $evaluation && $evaluation->statut === 'verrouillee';
    }

    public function canArchiver(array $user, ?\stdClass $evaluation = null): bool
    {
        if (!$this->hasPermission($user, 'academique.evaluations.admin')) {
            return false;
        }
        return $evaluation && $evaluation->statut !== 'archivee';
    }

    private function hasPermission(array $user, string $permission): bool
    {
        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
