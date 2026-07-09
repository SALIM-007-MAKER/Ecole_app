<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Rapports\Events\RapportExecute;

class BiNotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof RapportExecute)) {
            return;
        }
        // V3 : envoyer email/notification push quand statut='termine' ou 'erreur'
        // Pour l'instant, log simple — pas de dépendance EmailService imposée ici
    }
}
