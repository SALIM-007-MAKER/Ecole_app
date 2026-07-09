<?php

declare(strict_types=1);

namespace App\Modules\Documents\Policies;

class SharePolicy
{
    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }

    public function canShare(array $user): bool
    {
        return $this->has($user, 'document.share');
    }

    public function canRevoke(array $user, array $partage): bool
    {
        return $this->has($user, 'document.share')
            || (int)($user['id'] ?? 0) === (int)$partage['created_by'];
    }
}
