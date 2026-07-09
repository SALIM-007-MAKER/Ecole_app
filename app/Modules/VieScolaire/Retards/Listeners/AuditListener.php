<?php

namespace App\Modules\VieScolaire\Retards\Listeners;

use App\Modules\VieScolaire\Retards\Events\LateJustified;
use App\Modules\VieScolaire\Retards\Events\LateRejected;
use App\Modules\VieScolaire\Retards\Events\LateThresholdReached;
use App\Modules\VieScolaire\Retards\Events\StudentLate;
use Core\Event;
use Core\Listener;
use Core\Logger;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof StudentLate          => $this->onStudentLate($event),
            $event instanceof LateJustified        => $this->onJustified($event),
            $event instanceof LateRejected         => $this->onRejected($event),
            $event instanceof LateThresholdReached => $this->onThreshold($event),
            default                                => null,
        };
    }

    private function onStudentLate(StudentLate $e): void
    {
        Logger::info(sprintf(
            '[Retards/Audit] Retard enregistré — retard:%d élève:%d classe:%d date:%s durée:%dmin',
            $e->retardId, $e->eleveId, $e->classeId, $e->dateRetard, $e->retardMinutes,
        ));
    }

    private function onJustified(LateJustified $e): void
    {
        Logger::info(sprintf(
            '[Retards/Audit] Justification validée — retard:%d élève:%d par:%d',
            $e->retardId, $e->eleveId, $e->valideParId,
        ));
    }

    private function onRejected(LateRejected $e): void
    {
        Logger::info(sprintf(
            '[Retards/Audit] Justification refusée — retard:%d élève:%d par:%d motif:"%s"',
            $e->retardId, $e->eleveId, $e->rejeteParId, mb_substr($e->motifRefus, 0, 60),
        ));
    }

    private function onThreshold(LateThresholdReached $e): void
    {
        Logger::warning(sprintf(
            '[Retards/Audit] SEUIL ATTEINT — élève:%d total:%d seuil:%d annee:%s',
            $e->eleveId, $e->totalRetards, $e->seuilAtteint, $e->anneeScolaire,
        ));
    }
}
