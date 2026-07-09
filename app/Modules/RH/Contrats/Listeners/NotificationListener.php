<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\RH\Contrats\Events\ContractExpired;
use App\Modules\RH\Contrats\Events\ContractTerminated;

class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // Phase 6.8 — Communication module :
        // ContractExpired    → notifier le RH + employé concerné
        // ContractTerminated → notifier RH, direction
        // Alertes échéance   → email/push J-90, J-60, J-30 avant date_fin
    }
}
