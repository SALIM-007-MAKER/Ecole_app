<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Listeners;

use App\Modules\RH\Conges\Events\LeaveApproved;
use App\Modules\RH\Conges\Events\LeaveRejected;
use App\Modules\RH\Conges\Events\LeaveRequested;
use Core\Event;
use Core\Listener;

/**
 * Stub Phase 6.10 — notifications e-mail automatiques.
 * Priorités :
 *   - LeaveRequested → notifier RH/directeur qu'une demande attend approbation
 *   - LeaveApproved  → notifier l'employé de l'approbation
 *   - LeaveRejected  → notifier l'employé avec le motif de rejet
 */
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // Stub — implémentation Phase 6.10 (Module Communications V2)
    }
}
