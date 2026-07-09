<?php

declare(strict_types=1);

namespace App\Modules\Communication\Policies;

class ThreadPolicy
{
    public function canView(array $user, array $thread): bool
    {
        // Un participant peut voir son thread
        return in_array('communication.view', $user['permissions'] ?? [], true);
    }

    public function canReply(array $user, array $thread): bool
    {
        return in_array('communication.send', $user['permissions'] ?? [], true)
            && !$thread['archive'];
    }

    public function canArchive(array $user, array $thread): bool
    {
        // Le créateur ou un admin peut archiver
        return (int) ($thread['created_by'] ?? 0) === (int) ($user['id'] ?? -1)
            || in_array('communication.admin', $user['permissions'] ?? [], true);
    }

    public function canDelete(array $user, array $thread): bool
    {
        return in_array('communication.admin', $user['permissions'] ?? [], true);
    }
}
