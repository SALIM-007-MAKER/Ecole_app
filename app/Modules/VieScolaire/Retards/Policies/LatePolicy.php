<?php

namespace App\Modules\VieScolaire\Retards\Policies;

class LatePolicy
{
    public function canView(array $user): bool
    {
        return in_array('late.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('late.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('late.update', $user['permissions'] ?? [], true);
    }

    public function canJustify(array $user): bool
    {
        return in_array('late.justify', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('late.validate', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('late.export', $user['permissions'] ?? [], true);
    }

    /** Un retard modifiable = non justifié ou en attente. */
    public function canModifyRetard(array $user, array $retard): bool
    {
        if (!$this->canUpdate($user)) {
            return false;
        }
        return in_array($retard['statut'] ?? '', ['non_justifie', 'en_attente'], true);
    }
}
