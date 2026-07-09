<?php

namespace App\Modules\Finance\Policies;

/**
 * Mappe sur les permissions Finance V2 existantes :
 *   finance.paiements.view
 *   finance.paiements.view.own  → parent/élève (ses propres paiements)
 *   finance.paiements.create    → enregistrer / initier / valider / compléter
 *   finance.paiements.annuler   → annuler
 *   finance.paiements.trop_percu→ traiter les trop-perçus
 */
class PaymentPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'finance.paiements.view')
            || $this->has($user, 'finance.paiements.create')
            || $this->has($user, 'finance.paiements.annuler');
    }

    public function canViewOwn(array $user): bool
    {
        return $this->has($user, 'finance.paiements.view.own') || $this->canView($user);
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'finance.paiements.create');
    }

    public function canValider(array $user): bool
    {
        return $this->has($user, 'finance.paiements.create');
    }

    public function canCompleter(array $user): bool
    {
        return $this->has($user, 'finance.paiements.create');
    }

    public function canAnnuler(array $user): bool
    {
        return $this->has($user, 'finance.paiements.annuler');
    }

    public function canRembourser(array $user): bool
    {
        return $this->has($user, 'finance.paiements.annuler');
    }

    public function canTraiterTropPercu(array $user): bool
    {
        return $this->has($user, 'finance.paiements.trop_percu');
    }

    public function canPrintRecu(array $user): bool
    {
        return $this->canView($user) || $this->canViewOwn($user);
    }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
