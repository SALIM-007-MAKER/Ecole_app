<?php

namespace App\Modules\Academique\Policies;

class CalculationPolicy
{
    /**
     * Peut déclencher le calcul de moyennes (bulletin d'un élève).
     */
    public function canCalculate(array $user): bool
    {
        return $this->has($user, 'academique.moyennes.calculate')
            || $this->has($user, 'academique.moyennes.admin');
    }

    /**
     * Peut publier les moyennes de la classe (les rendre visibles aux parents).
     */
    public function canPublier(array $user): bool
    {
        return $this->has($user, 'academique.moyennes.admin');
    }

    /**
     * Peut consulter les moyennes d'un élève.
     * Enseignant : uniquement ses classes/matières.
     * Admin : tous.
     */
    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.moyennes.view')
            || $this->has($user, 'academique.moyennes.calculate')
            || $this->has($user, 'academique.moyennes.admin');
    }

    /**
     * Peut accéder aux statistiques de classe.
     */
    public function canViewStats(array $user): bool
    {
        return $this->has($user, 'academique.moyennes.view')
            || $this->has($user, 'academique.moyennes.admin');
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
