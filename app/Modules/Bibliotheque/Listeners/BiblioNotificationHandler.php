<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Bibliotheque\Events\EmpruntEnRetard;
use App\Modules\Bibliotheque\Events\ReservationDisponible;
use App\Modules\Bibliotheque\Events\PenaliteCreee;

class BiblioNotificationHandler implements Listener
{
    public function handle(Event $event): void
    {
        // Stub: notification routing is handled by CrossModuleListener in Communication module.
        // This handler exists as extension point for direct biblio notifications (in-app, SMS).
        match(true) {
            $event instanceof EmpruntEnRetard      => $this->onRetard($event),
            $event instanceof ReservationDisponible => $this->onDisponible($event),
            $event instanceof PenaliteCreee        => $this->onPenalite($event),
            default => null,
        };
    }

    private function onRetard(EmpruntEnRetard $event): void {}

    private function onDisponible(ReservationDisponible $event): void {}

    private function onPenalite(PenaliteCreee $event): void {}
}
