<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Policies;

class TrainingPolicy
{
    public function canView(array $user): bool
    {
        return in_array('training.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('training.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('training.update', $user['permissions'] ?? [], true);
    }

    public function canEnroll(array $user): bool
    {
        return in_array('training.enroll', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('training.validate', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('training.export', $user['permissions'] ?? [], true);
    }

    public function canManageCatalog(array $user): bool
    {
        return in_array('training.manage_catalog', $user['permissions'] ?? [], true);
    }
}
