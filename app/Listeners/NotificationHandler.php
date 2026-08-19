<?php

namespace App\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\NotificationService;
use App\Events\AbsenceCreee;

class NotificationHandler implements Listener
{
    private NotificationService $notif;

    public function __construct()
    {
        $this->notif = new NotificationService();
    }

    public function handle(Event $event): void
    {
        match (true) {

            $event instanceof AbsenceCreee => $event->isAbsenceOuRetard()
                ? $this->notif->onAbsence($event->eleveId, $event->date, $event->statut)
                : null,

            default => null,
        };
    }
}
