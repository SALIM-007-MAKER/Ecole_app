<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Policies;

class OrganizationPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'organization.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'organization.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->has($user, 'organization.update');
    }

    public function canArchive(array $user): bool
    {
        return $this->has($user, 'organization.archive');
    }

    public function canExport(array $user): bool
    {
        return $this->has($user, 'organization.export');
    }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
