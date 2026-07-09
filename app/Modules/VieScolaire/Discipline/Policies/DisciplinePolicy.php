<?php

namespace App\Modules\VieScolaire\Discipline\Policies;

class DisciplinePolicy
{
    public function canView(array $user): bool
    {
        return in_array('discipline.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('discipline.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('discipline.update', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('discipline.validate', $user['permissions'] ?? [], true);
    }

    public function canSanction(array $user): bool
    {
        return in_array('discipline.sanction', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('discipline.export', $user['permissions'] ?? [], true);
    }

    /** Un dossier modifiable = non clos. */
    public function canModifyDossier(array $user, array $dossier): bool
    {
        return $this->canUpdate($user)
            && !in_array($dossier['statut'] ?? '', ['clos'], true);
    }

    /** Sanction modifiable = statut 'prononcee'. */
    public function canModifySanction(array $user, array $sanction): bool
    {
        return $this->canSanction($user)
            && $sanction['statut'] === 'prononcee';
    }

    /** Appel déposable = sanction statut prononcee ou effective. */
    public function canAppeal(array $user, array $sanction): bool
    {
        return in_array($sanction['statut'] ?? '', ['prononcee', 'effective'], true);
    }
}
