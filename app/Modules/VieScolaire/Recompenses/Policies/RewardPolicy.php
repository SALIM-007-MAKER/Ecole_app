<?php

namespace App\Modules\VieScolaire\Recompenses\Policies;

class RewardPolicy
{
    public function canView(array $user): bool
    {
        return in_array('reward.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('reward.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('reward.update', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('reward.validate', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('reward.export', $user['permissions'] ?? [], true);
    }

    /** Récompense modifiable = statut 'attribuee' uniquement. */
    public function canModify(array $user, array $reward): bool
    {
        return $this->canUpdate($user)
            && $reward['statut'] === 'attribuee';
    }

    /** Récompense révocable = statut attribuee ou validee. */
    public function canRevoke(array $user, array $reward): bool
    {
        return $this->canValidate($user)
            && in_array($reward['statut'], ['attribuee', 'validee'], true);
    }
}
