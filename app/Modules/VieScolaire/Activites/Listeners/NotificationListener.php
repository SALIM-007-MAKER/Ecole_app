<?php

namespace App\Modules\VieScolaire\Activites\Listeners;

use Core\Event;
use Core\Listener;

/**
 * Notifications automatiques pour les activités.
 *
 * Cas préparés (câblage NotificationService V2 différé — MS2-M-004) :
 * - ActivityPublished  → notification ouverture inscriptions → classes liées
 * - ActivityUpdated    → notification modification → inscrits
 * - ActivityCancelled  → notification annulation  → inscrits
 * - StudentRegisteredToActivity → confirmation inscription → élève/parent
 */
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // MS2-M-004 : NotificationService V2 non câblé en Phase 5
    }
}
