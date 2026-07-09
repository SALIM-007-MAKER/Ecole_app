<?php
declare(strict_types=1);

namespace App\Modules\Portals\Contracts;

interface PortalPolicyInterface
{
    public function getPortalName(): string;

    public function getRequiredPermission(): string;

    public function canAccess(array $user): bool;
}
