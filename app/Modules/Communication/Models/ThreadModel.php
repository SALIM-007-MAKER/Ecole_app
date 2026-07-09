<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

class ThreadModel
{
    const TYPES = ['direct', 'groupe', 'broadcast'];

    public static function nomAffichage(array $thread, int $currentUserId): string
    {
        if (!empty($thread['sujet'])) {
            return $thread['sujet'];
        }
        return 'Conversation #' . $thread['id'];
    }
}
