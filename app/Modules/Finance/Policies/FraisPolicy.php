<?php

namespace App\Modules\Finance\Policies;

class FraisPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'finance.frais.view');
    }

    public function canManage(array $user): bool
    {
        return $this->has($user, 'finance.frais.manage');
    }

    public function canAdmin(array $user): bool
    {
        return $this->has($user, 'finance.frais.admin');
    }

    /** Créer un frais = manage */
    public function canCreate(array $user): bool
    {
        return $this->canManage($user);
    }

    /** Modifier un frais = manage */
    public function canUpdate(array $user): bool
    {
        return $this->canManage($user);
    }

    /** Activer/désactiver = manage */
    public function canToggleStatut(array $user): bool
    {
        return $this->canManage($user);
    }

    /** Archiver = admin (irréversible) */
    public function canArchive(array $user): bool
    {
        return $this->canAdmin($user);
    }

    /** Supprimer physiquement = admin uniquement */
    public function canDelete(array $user): bool
    {
        return $this->canAdmin($user);
    }

    /** Gestion des catégories = manage */
    public function canManageCategories(array $user): bool
    {
        return $this->canManage($user);
    }

    /** Définir des tarifs = manage */
    public function canManageTarifs(array $user): bool
    {
        return $this->canManage($user);
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, (array)$perms, true)
            || in_array('*', (array)$perms, true);
    }
}
