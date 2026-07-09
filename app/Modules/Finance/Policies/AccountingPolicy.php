<?php

namespace App\Modules\Finance\Policies;

class AccountingPolicy
{
    public function canView(array $user): bool
    {
        return in_array('finance.comptabilite.view', $user['permissions'] ?? [], true)
            || $this->canSaisir($user)
            || $this->canGererExercice($user);
    }

    public function canSaisir(array $user): bool
    {
        return in_array('finance.comptabilite.saisir', $user['permissions'] ?? [], true);
    }

    public function canGererExercice(array $user): bool
    {
        return in_array('finance.comptabilite.exercice', $user['permissions'] ?? [], true);
    }

    public function canExtourner(array $user): bool
    {
        return $this->canGererExercice($user);
    }

    public function canCloturerPeriode(array $user): bool
    {
        return $this->canGererExercice($user);
    }

    public function canCloturerExercice(array $user): bool
    {
        return $this->canGererExercice($user);
    }

    public function canViewPlanComptable(array $user): bool
    {
        return $this->canView($user);
    }

    public function canViewBalance(array $user): bool
    {
        return $this->canView($user);
    }

    public function canViewGrandLivre(array $user): bool
    {
        return $this->canView($user);
    }
}
