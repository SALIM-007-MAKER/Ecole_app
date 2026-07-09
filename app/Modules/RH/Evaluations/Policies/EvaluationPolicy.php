<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Policies;

class EvaluationPolicy
{
    public function canView(array $user): bool
    {
        return in_array('evaluation.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('evaluation.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('evaluation.update', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('evaluation.validate', $user['permissions'] ?? [], true);
    }

    public function canPublish(array $user): bool
    {
        return in_array('evaluation.publish', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('evaluation.export', $user['permissions'] ?? [], true);
    }

    public function canSelfEvaluate(array $user, array $evaluation): bool
    {
        return (int)($user['employe_id'] ?? 0) === (int)$evaluation['employe_id']
            && $evaluation['statut'] === 'en_auto_evaluation';
    }

    public function canEvaluateAsResponsable(array $user, array $evaluation): bool
    {
        return $this->canUpdate($user)
            && $evaluation['statut'] === 'en_evaluation';
    }
}
