<?php

namespace App\Modules\VieScolaire\Presences\Listeners;

use App\Modules\VieScolaire\Presences\Events\AttendanceValidated;
use App\Modules\VieScolaire\Presences\Events\AttendanceCompleted;
use App\Modules\VieScolaire\Presences\Events\StudentLate;
use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Mise à jour des statistiques et intégrations inter-domaines.
 *
 * Remarques :
 * - L'intégration Absences (vs_absences) est gérée DIRECTEMENT dans AttendanceService,
 *   avant le dispatch de l'événement StudentAbsent, afin de garantir l'atomicité.
 * - Ce listener gère les statistiques agrégées et le futur domaine Retards (Phase 5.3).
 */
class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof AttendanceValidated  => $this->onValidated($event),
            $event instanceof AttendanceCompleted  => $this->onCompleted($event),
            $event instanceof StudentLate          => $this->onLate($event),
            default                                => null,
        };
    }

    private function onValidated(AttendanceValidated $e): void
    {
        // Invalidation du cache de statistiques (à implémenter avec un cache en V2.1)
        Logger::info(sprintf(
            '[VieScolaire/Stats] Cache stats invalidé — classe:%d date:%s',
            $e->classeId, $e->dateAppel,
        ));
    }

    private function onCompleted(AttendanceCompleted $e): void
    {
        Logger::info(sprintf(
            '[VieScolaire/Stats] Pointage complet — appel:%d classe:%d',
            $e->appelId, $e->classeId,
        ));
    }

    private function onLate(StudentLate $e): void
    {
        // La création dans vs_retards est gérée atomiquement par AttendanceService
        // via creerRetardDepuisSession() → LateRepository::insert().
        // Retards\Events\StudentLate est ensuite dispatché pour les handlers Retards.
        Logger::info(sprintf(
            '[VieScolaire/Stats] Retard transmis au domaine Retards — élève:%d appel:%d',
            $e->eleveId, $e->appelId,
        ));
    }
}
