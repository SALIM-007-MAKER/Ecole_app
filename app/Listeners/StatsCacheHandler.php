<?php

namespace App\Listeners;

use Core\Event;
use Core\Listener;
use App\Events\EleveCreated;
use App\Events\ImportCsvCompleted;

class StatsCacheHandler implements Listener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof EleveCreated || $event instanceof ImportCsvCompleted)) {
            return;
        }

        // Purge le cache de statistiques stocké en session
        foreach (array_keys($_SESSION ?? []) as $key) {
            if (str_starts_with($key, '_stats_cache_')) {
                unset($_SESSION[$key]);
            }
        }
    }
}
