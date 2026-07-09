<?php

namespace App\Modules\RH\Enseignants\Listeners;

use App\Modules\RH\Enseignants\Events\TeacherAssigned;
use App\Modules\RH\Enseignants\Events\TeacherCreated;
use App\Modules\RH\Enseignants\Events\TeacherQualificationUpdated;
use App\Modules\RH\Enseignants\Events\TeacherUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof TeacherCreated              => $this->onCreated($event),
            $event instanceof TeacherUpdated              => $this->onUpdated($event),
            $event instanceof TeacherAssigned             => $this->onAssigned($event),
            $event instanceof TeacherQualificationUpdated => $this->onQualification($event),
            default                                       => null,
        };
    }

    private function onCreated(TeacherCreated $e): void
    {
        $this->audit->logCreate($e->creeParId, 'rh', 'enseignant', $e->enseignantId, $e->toArray());
    }

    private function onUpdated(TeacherUpdated $e): void
    {
        $this->audit->log($e->modifieParId, 'update', 'rh', 'enseignant', $e->enseignantId, null, $e->changes);
    }

    private function onAssigned(TeacherAssigned $e): void
    {
        $this->audit->log(
            $e->assigneParId,
            'teacher_assigned',
            'rh',
            'enseignant',
            $e->enseignantId,
            null,
            ['matieres' => $e->matieres]
        );
    }

    private function onQualification(TeacherQualificationUpdated $e): void
    {
        $this->audit->log(
            $e->modifieParId,
            'qualification_updated',
            'rh',
            'enseignant',
            $e->enseignantId,
            null,
            ['type' => $e->typeQualification, 'intitule' => $e->intitule]
        );
    }
}
