<?php

namespace App\Modules\VieScolaire\Presences\Listeners;

use App\Modules\VieScolaire\Presences\Events\AttendanceStarted;
use App\Modules\VieScolaire\Presences\Events\AttendanceValidated;
use App\Modules\VieScolaire\Presences\Events\AttendanceCompleted;
use App\Modules\VieScolaire\Presences\Events\StudentAbsent;
use App\Modules\VieScolaire\Presences\Events\StudentLate;
use App\Modules\VieScolaire\Presences\Events\StudentPresent;
use Core\Event;
use Core\Listener;
use Core\Logger;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof AttendanceStarted    => $this->onStarted($event),
            $event instanceof AttendanceValidated  => $this->onValidated($event),
            $event instanceof AttendanceCompleted  => $this->onCompleted($event),
            $event instanceof StudentAbsent        => $this->onAbsent($event),
            $event instanceof StudentLate          => $this->onLate($event),
            $event instanceof StudentPresent       => null, // présence ne génère pas d'entrée d'audit dédiée
            default                                => null,
        };
    }

    private function onStarted(AttendanceStarted $e): void
    {
        Logger::info(sprintf(
            '[VieScolaire/Appel] Session ouverte — appel:%d classe:%d date:%s type:%s par_enseignant:%d',
            $e->appelId, $e->classeId, $e->dateAppel, $e->typeAppel, $e->enseignantId,
        ));
    }

    private function onValidated(AttendanceValidated $e): void
    {
        Logger::info(sprintf(
            '[VieScolaire/Appel] Session validée — appel:%d classe:%d date:%s présents:%d absents:%d validé_par:%d',
            $e->appelId, $e->classeId, $e->dateAppel, $e->totalPresents, $e->totalAbsents, $e->valideParId,
        ));
    }

    private function onCompleted(AttendanceCompleted $e): void
    {
        Logger::info(sprintf(
            '[VieScolaire/Appel] Pointage complet — appel:%d classe:%d (%d/%d élèves)',
            $e->appelId, $e->classeId, $e->pointesCount, $e->totalEleves,
        ));
    }

    private function onAbsent(StudentAbsent $e): void
    {
        Logger::info(sprintf(
            '[VieScolaire/Appel] Absence enregistrée — appel:%d élève:%d absence_id:%d date:%s',
            $e->appelId, $e->eleveId, $e->absenceId, $e->dateAppel,
        ));
    }

    private function onLate(StudentLate $e): void
    {
        Logger::info(sprintf(
            '[VieScolaire/Appel] Retard enregistré — appel:%d élève:%d %s (%s min)',
            $e->appelId, $e->eleveId,
            $e->heureArrivee ?? '?',
            $e->retardMinutes ?? '?',
        ));
    }
}
