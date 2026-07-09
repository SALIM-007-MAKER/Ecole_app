<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Listeners;

use Core\Event;
use Core\Listener;

/**
 * Notifications parents/enseignants pour les événements EDT.
 * Câblage NotificationService V2 différé (MS2-M-004).
 */
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // MS2-M-004 : NotificationService V2 non câblé en Phase 5
    }
}
