<?php

namespace App\Modules\VieScolaire\Activites\Policies;

class ActivityPolicy
{
    public function canView(array $user): bool
    {
        return in_array('activity.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('activity.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('activity.update', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('activity.validate', $user['permissions'] ?? [], true);
    }

    public function canPublish(array $user): bool
    {
        return in_array('activity.publish', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('activity.export', $user['permissions'] ?? [], true);
    }

    /** Modifiable = non annulée et non terminée. */
    public function canModifyActivity(array $user, array $activity): bool
    {
        return $this->canUpdate($user)
            && !in_array($activity['statut'], ['annule', 'termine'], true);
    }

    /** Publiable = brouillon uniquement. */
    public function canPublishActivity(array $user, array $activity): bool
    {
        return $this->canPublish($user) && $activity['statut'] === 'brouillon';
    }

    /** Annulable = brouillon ou publie ou en_cours. */
    public function canCancelActivity(array $user, array $activity): bool
    {
        return $this->canValidate($user)
            && in_array($activity['statut'], ['brouillon', 'publie', 'en_cours'], true);
    }

    /** Inscription possible = publie ou en_cours + pas annulée/terminée. */
    public function canRegisterStudent(array $user): bool
    {
        return $this->canUpdate($user) || $this->canCreate($user);
    }
}
