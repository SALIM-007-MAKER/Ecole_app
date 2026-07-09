<?php

namespace App\Modules\Finance\Policies;

/**
 * Mappe sur les permissions granulaires de config/permissions.php :
 *   finance.factures.view
 *   finance.factures.create   → création ET modification brouillon
 *   finance.factures.emettre  → émission
 *   finance.factures.remise   → application de remises
 *   finance.factures.annuler  → annulation / archivage / suppression brouillon
 *   finance.factures.masse    → génération en masse
 */
class InvoicePolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'finance.factures.view')
            || $this->has($user, 'finance.factures.create')
            || $this->has($user, 'finance.factures.emettre')
            || $this->has($user, 'finance.factures.annuler');
    }

    public function canViewOwn(array $user): bool
    {
        return $this->has($user, 'finance.paiements.view.own') || $this->canView($user);
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'finance.factures.create');
    }

    public function canEdit(array $user): bool
    {
        return $this->has($user, 'finance.factures.create');
    }

    public function canEmettre(array $user): bool
    {
        return $this->has($user, 'finance.factures.emettre')
            || $this->has($user, 'finance.factures.annuler');
    }

    public function canAnnuler(array $user): bool
    {
        return $this->has($user, 'finance.factures.annuler');
    }

    public function canArchiver(array $user): bool
    {
        return $this->has($user, 'finance.factures.annuler');
    }

    public function canSupprimer(array $user): bool
    {
        return $this->has($user, 'finance.factures.annuler');
    }

    public function canGenererMasse(array $user): bool
    {
        return $this->has($user, 'finance.factures.masse');
    }

    public function canAppliquerRemise(array $user): bool
    {
        return $this->has($user, 'finance.factures.remise');
    }

    public function canPrint(array $user): bool
    {
        return $this->canView($user);
    }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
