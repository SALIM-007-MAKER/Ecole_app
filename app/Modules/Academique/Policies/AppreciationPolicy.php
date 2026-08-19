<?php

namespace App\Modules\Academique\Policies;

use App\Models\EnseignementModel;

class AppreciationPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.notes.view')
            || $this->has($user, 'academique.notes.manage');
    }

    /**
     * @param ?int $profId  Fiche professeur liée à l'utilisateur connecté
     *                      (ProfesseurModel::findByUserId), ou null si
     *                      l'utilisateur n'est pas un professeur (admin,
     *                      directeur...) — dans ce cas la permission large
     *                      suffit, comme pour NotePolicy::canSaisir().
     */
    public function canSaisir(array $user, ?int $profId, int $matiereId, int $classeId, string $annee): bool
    {
        if (!$this->has($user, 'academique.notes.manage')) {
            return false;
        }
        if ($profId === null) {
            return true;
        }
        return (new EnseignementModel())->exists($profId, $matiereId, $classeId, $annee);
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
