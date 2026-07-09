<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Policies;

class AttendancePolicy
{
    public function canView(array $user): bool
    {
        return in_array('rh.presence.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('rh.presence.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('rh.presence.update', $user['permissions'] ?? [], true);
    }

    public function canValidate(array $user): bool
    {
        return in_array('rh.presence.validate', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('rh.presence.export', $user['permissions'] ?? [], true);
    }
}
