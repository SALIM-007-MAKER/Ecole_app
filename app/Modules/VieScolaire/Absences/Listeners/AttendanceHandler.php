<?php

namespace App\Modules\VieScolaire\Absences\Listeners;

use App\Modules\VieScolaire\Absences\Events\AbsenceJustified;
use App\Modules\VieScolaire\Absences\Events\AbsenceRejected;
use App\Modules\VieScolaire\Absences\Events\StudentAbsent;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;
use Core\Logger;

class AttendanceHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof StudentAbsent    => $this->onStudentAbsent($event),
            $event instanceof AbsenceJustified => $this->onAbsenceJustified($event),
            $event instanceof AbsenceRejected  => $this->onAbsenceRejected($event),
            default                            => null,
        };
    }

    private function onStudentAbsent(StudentAbsent $event): void
    {
        Logger::info(sprintf(
            '[VieScolaire] Absence enregistrée — élève:%d classe:%d date:%s type:%s',
            $event->eleveId,
            $event->classeId,
            $event->dateAbsence,
            $event->type,
        ));

        $this->notifyParent($event->eleveId, 'absence_saisie', [
            'absence_id'   => $event->absenceId,
            'date_absence' => $event->dateAbsence,
            'type'         => $event->type,
        ]);
    }

    private function onAbsenceJustified(AbsenceJustified $event): void
    {
        Logger::info(sprintf(
            '[VieScolaire] Justification validée — absence:%d élève:%d',
            $event->absenceId,
            $event->eleveId,
        ));

        $this->notifyParent($event->eleveId, 'justification_validee', [
            'absence_id'       => $event->absenceId,
            'justification_id' => $event->justificationId,
        ]);
    }

    private function onAbsenceRejected(AbsenceRejected $event): void
    {
        Logger::info(sprintf(
            '[VieScolaire] Justification refusée — absence:%d élève:%d motif:%s',
            $event->absenceId,
            $event->eleveId,
            mb_substr($event->motifRefus, 0, 80),
        ));

        $this->notifyParent($event->eleveId, 'justification_refusee', [
            'absence_id'  => $event->absenceId,
            'motif_refus' => $event->motifRefus,
        ]);
    }

    private function notifyParent(int $eleveId, string $type, array $data): void
    {
        // NotificationService V2 integration deferred to MS2-M-004 (V2.1)
        // Log the intent so it's traceable in the meantime
        Logger::info(sprintf(
            '[VieScolaire] Notification parent différée — élève:%d type:%s',
            $eleveId,
            $type,
        ));
    }
}
