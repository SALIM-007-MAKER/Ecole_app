<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\RH\Presences\Events\AttendanceCreated;
use App\Modules\RH\Presences\Events\AttendanceUpdated;
use App\Modules\RH\Presences\Events\AttendanceValidated;
use App\Modules\RH\Presences\Events\AttendanceLateDetected;
use App\Modules\RH\Presences\Events\AttendanceOvertimeDetected;
use App\Services\AuditService;

class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof AttendanceCreated         => $this->onCreated($event),
            $event instanceof AttendanceUpdated         => $this->onUpdated($event),
            $event instanceof AttendanceValidated       => $this->onValidated($event),
            $event instanceof AttendanceLateDetected    => $this->onLate($event),
            $event instanceof AttendanceOvertimeDetected=> $this->onOvertime($event),
            default                                     => null,
        };
    }

    private function onCreated(AttendanceCreated $e): void
    {
        $this->audit->logCreate($e->createdBy, 'rh', 'presence', $e->presenceId, $e->toArray());
    }

    private function onUpdated(AttendanceUpdated $e): void
    {
        $this->audit->log($e->updatedBy, $e->action, 'rh', 'presence', $e->presenceId, null, $e->changes);
    }

    private function onValidated(AttendanceValidated $e): void
    {
        $this->audit->log($e->validatedBy, $e->decision, 'rh', 'presence', $e->presenceId, null, [
            'decision'    => $e->decision,
            'motif_rejet' => $e->motifRejet,
        ]);
    }

    private function onLate(AttendanceLateDetected $e): void
    {
        $this->audit->log($e->createdBy, 'retard_detecte', 'rh', 'presence', $e->presenceId, null, [
            'retard_minutes' => $e->retardMinutes,
            'heure_arrivee'  => $e->heureArrivee,
            'heure_ref'      => $e->heureReference,
        ]);
    }

    private function onOvertime(AttendanceOvertimeDetected $e): void
    {
        $this->audit->log($e->createdBy, 'heures_sup_detectees', 'rh', 'presence', $e->presenceId, null, [
            'heures_supp_minutes'     => $e->heuresSuppMinutes,
            'duree_effective_minutes' => $e->dureeEffectiveMinutes,
        ]);
    }
}
