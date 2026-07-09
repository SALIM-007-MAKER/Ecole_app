<?php

namespace App\Modules\VieScolaire\Activites\Listeners;

use Core\Database;
use Core\Event;
use Core\Listener;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        $class = get_class($event);

        if (str_ends_with($class, 'ActivityPublished')) {
            $this->onPublished($event->activityId);
        }

        if (str_ends_with($class, 'ActivityCancelled')) {
            $this->onCancelled($event->activityId);
        }

        if (str_ends_with($class, 'StudentRegisteredToActivity')) {
            $this->onRegistration($event->activityId, $event->statut);
        }
    }

    private function onPublished(int $activityId): void
    {
        // Hook : mise à jour compteur activités publiées (extensible)
    }

    private function onCancelled(int $activityId): void
    {
        // Hook : mise à jour compteur annulations (extensible)
    }

    private function onRegistration(int $activityId, string $statut): void
    {
        // Hook : mise à jour compteur inscrits / liste d'attente (extensible)
    }
}
