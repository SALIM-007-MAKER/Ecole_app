<?php

declare(strict_types=1);

namespace App\Modules\Documents\Policies;

class FolderPolicy
{
    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }

    public function canView(array $user): bool
    {
        return $this->has($user, 'document.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'folder.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->has($user, 'folder.create');
    }

    public function canDelete(array $user): bool
    {
        return $this->has($user, 'folder.delete');
    }
}
