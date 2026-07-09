<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\RH\Affectations\Events\AssignmentTransferred;

/**
 * Stub — notifications activées en Phase 6.10 (Dashboard RH).
 *
 * Hooks prévus :
 *  - AssignmentTransferred → notifier le manager destination + RH
 *  - AssignmentCreated     → notifier l'employé (fiche d'affectation)
 *  - AssignmentArchived    → notifier RH
 */
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // Phase 6.10 : notifications email/push
    }
}
