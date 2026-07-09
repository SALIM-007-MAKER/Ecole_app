<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Policies;

class CommandePolicy
{
    public function canCreate(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.commande.create');
    }

    public function canValidate(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.commande.validate');
    }

    public function canReceive(array $user): bool
    {
        return $this->hasPermission($user, 'inventaire.commande.receive');
    }

    private function hasPermission(array $user, string $perm): bool
    {
        $permissions = $user['permissions'] ?? [];
        return in_array($perm, $permissions, true) || in_array('*', $permissions, true);
    }
}
