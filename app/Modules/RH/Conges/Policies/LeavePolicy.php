<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Policies;

class LeavePolicy
{
    public function canView(array $user): bool
    {
        return in_array('leave.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('leave.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('leave.update', $user['permissions'] ?? [], true);
    }

    public function canApprove(array $user): bool
    {
        return in_array('leave.approve', $user['permissions'] ?? [], true);
    }

    public function canReject(array $user): bool
    {
        return in_array('leave.reject', $user['permissions'] ?? [], true);
    }

    public function canCancel(array $user): bool
    {
        return in_array('leave.cancel', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('leave.export', $user['permissions'] ?? [], true);
    }
}
