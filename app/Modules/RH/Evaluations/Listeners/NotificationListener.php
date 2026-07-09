<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Listeners;

use Core\Event;
use Core\Listener;

// Stub — implémentation complète Phase 6.10 (système notifications)
class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        // EvaluationCreated → notifier l'employé
        // EvaluationValidated → notifier l'employé + responsable
        // EvaluationPublished → notifier l'employé (résultat disponible)
        // DevelopmentPlanCreated → notifier l'employé
    }
}
