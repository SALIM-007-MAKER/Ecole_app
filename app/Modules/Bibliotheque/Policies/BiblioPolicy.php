<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Policies;

class BiblioPolicy
{
    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }

    public function canView(array $user): bool
    {
        return $this->has($user, 'biblio.view');
    }

    public function canSearch(array $user): bool
    {
        return $this->has($user, 'biblio.search');
    }

    public function canBorrow(array $user): bool
    {
        return $this->has($user, 'biblio.borrow');
    }

    public function canReserve(array $user): bool
    {
        return $this->has($user, 'biblio.reserve');
    }

    public function canManageLoans(array $user): bool
    {
        return $this->has($user, 'biblio.manage_loans');
    }

    public function canManageCatalogue(array $user): bool
    {
        return $this->has($user, 'biblio.manage_catalogue');
    }

    public function canManageExemplaires(array $user): bool
    {
        return $this->has($user, 'biblio.manage_exemplaires');
    }

    public function canManagePenalties(array $user): bool
    {
        return $this->has($user, 'biblio.manage_penalties');
    }

    public function canInventory(array $user): bool
    {
        return $this->has($user, 'biblio.inventory');
    }

    public function canAnalytics(array $user): bool
    {
        return $this->has($user, 'biblio.analytics');
    }

    public function canAdmin(array $user): bool
    {
        return $this->has($user, 'biblio.admin');
    }
}
