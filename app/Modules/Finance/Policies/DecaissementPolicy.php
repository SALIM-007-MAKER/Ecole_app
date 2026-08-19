<?php

namespace App\Modules\Finance\Policies;

/**
 * Mappe sur les permissions Finance V2 déjà déclarées dans config/permissions.php :
 *   finance.decaissements.view
 *   finance.decaissements.create
 *   finance.decaissements.valider
 *   finance.decaissements.approuver
 *   finance.decaissements.rejeter
 * Pas de permission ".annuler" dédiée dans le blueprint d'origine — l'annulation
 * est réservée à qui peut approuver (dernier maillon de la chaîne de contrôle).
 */
class DecaissementPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.view')
            || $this->has($user, 'finance.decaissements.create')
            || $this->has($user, 'finance.decaissements.valider')
            || $this->has($user, 'finance.decaissements.approuver');
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.create');
    }

    public function canValider(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.valider');
    }

    public function canApprouver(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.approuver');
    }

    public function canRejeter(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.rejeter');
    }

    public function canPayer(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.approuver');
    }

    public function canAnnuler(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.approuver');
    }

    public function canManageFournisseurs(array $user): bool
    {
        return $this->has($user, 'finance.decaissements.create');
    }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
