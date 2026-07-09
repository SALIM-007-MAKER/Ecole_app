<?php

namespace App\Modules\Academique\Policies;

class RankingPolicy
{
    /** Peut consulter le classement d'une classe. */
    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.classement.view')
            || $this->has($user, 'academique.classement.generate')
            || $this->has($user, 'academique.classement.admin');
    }

    /** Peut déclencher le calcul du classement. */
    public function canGenerate(array $user): bool
    {
        return $this->has($user, 'academique.classement.generate')
            || $this->has($user, 'academique.classement.admin');
    }

    /** Peut exporter le classement (PDF/CSV). */
    public function canExport(array $user): bool
    {
        return $this->has($user, 'academique.classement.generate')
            || $this->has($user, 'academique.classement.admin');
    }

    /** Peut voir le classement de niveau (tous les élèves d'un niveau). */
    public function canViewNiveau(array $user): bool
    {
        return $this->has($user, 'academique.classement.admin');
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
