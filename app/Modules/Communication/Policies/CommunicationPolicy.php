<?php

declare(strict_types=1);

namespace App\Modules\Communication\Policies;

class CommunicationPolicy
{
    public function canView(array $user): bool
    {
        return in_array('communication.view', $user['permissions'] ?? [], true);
    }

    public function canSend(array $user): bool
    {
        return in_array('communication.send', $user['permissions'] ?? [], true);
    }

    public function canBroadcast(array $user): bool
    {
        return in_array('communication.broadcast', $user['permissions'] ?? [], true);
    }

    public function canManageTemplates(array $user): bool
    {
        return in_array('communication.manage_templates', $user['permissions'] ?? [], true);
    }

    public function canManageGroups(array $user): bool
    {
        return in_array('communication.manage_groups', $user['permissions'] ?? [], true);
    }

    public function canLaunchCampaign(array $user): bool
    {
        return in_array('communication.campaign', $user['permissions'] ?? [], true);
    }

    public function canAdmin(array $user): bool
    {
        return in_array('communication.admin', $user['permissions'] ?? [], true);
    }

    public function canViewAll(array $user): bool
    {
        return in_array('communication.view_all', $user['permissions'] ?? [], true);
    }
}
