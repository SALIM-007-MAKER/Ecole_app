<?php

namespace App\Modules\Scolarite\Policies;

class MatierePolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'matieres.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'matieres.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->has($user, 'matieres.update');
    }

    public function canDelete(array $user): bool
    {
        return $this->has($user, 'matieres.delete');
    }

    /** Archivage = même niveau que update. */
    public function canArchive(array $user): bool
    {
        return $this->has($user, 'matieres.update');
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, (array)$perms, true)
            || in_array('*', (array)$perms, true);
    }
}
