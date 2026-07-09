<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Policies;

class AffectationPolicy
{
    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.affectation.create');
    }

    public function canReturn(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.affectation.return');
    }

    public function canMarkLost(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.affectation.lost');
    }

    private function hasPermission(array $user, string $perm): bool
    {
        $permissions = $user['permissions'] ?? [];
        return in_array($perm, $permissions, true) || in_array('*', $permissions, true);
    }
}
