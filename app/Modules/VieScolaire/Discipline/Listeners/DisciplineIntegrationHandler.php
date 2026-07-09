<?php

namespace App\Modules\VieScolaire\Discipline\Listeners;

use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Réagit aux événements cross-domaine sans dépendance directe sur les modules sources.
 * Écoute : LateThresholdReached, Absences\StudentAbsent, Retards\StudentLate
 */
class DisciplineIntegrationHandler implements Listener
{
    public function handle(Event $event): void
    {
        $class = get_class($event);

        // Retards\Events\LateThresholdReached
        if (str_ends_with($class, 'LateThresholdReached')) {
            $this->onLateThresholdReached($event);
            return;
        }

        // Absences\Events\StudentAbsent
        if (str_ends_with($class, 'StudentAbsent')) {
            $this->onStudentAbsent($event);
            return;
        }

        // Retards\Events\StudentLate (namespace Retards)
        if (str_ends_with($class, 'StudentLate') && str_contains($class, 'Retards')) {
            $this->onStudentLateRetard($event);
            return;
        }
    }

    private function onLateThresholdReached(Event $event): void
    {
        Logger::warning('discipline.integration', [
            'trigger'       => 'LateThresholdReached',
            'eleve_id'      => $event->eleveId ?? null,
            'total_retards' => $event->totalRetards ?? null,
            'seuil_atteint' => $event->seuilAtteint ?? null,
            'note'          => 'Signalement automatique possible — traitement manuel requis',
        ]);
    }

    private function onStudentAbsent(Event $event): void
    {
        Logger::info('discipline.integration', [
            'trigger'  => 'StudentAbsent',
            'eleve_id' => $event->eleveId ?? null,
            'type'     => $event->typeAbsence ?? null,
        ]);
    }

    private function onStudentLateRetard(Event $event): void
    {
        Logger::info('discipline.integration', [
            'trigger'  => 'StudentLate (Retards)',
            'eleve_id' => $event->eleveId ?? null,
            'minutes'  => $event->retardMinutes ?? null,
        ]);
    }
}
