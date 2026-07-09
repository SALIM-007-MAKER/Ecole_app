<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Listeners;

use Core\Event;
use Core\Listener;

// Stub — agrégation analytique Phase 6.10
class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        // EvaluationValidated → mise à jour score moyen campagne
        // EvaluationPublished → compteur publications
    }
}
