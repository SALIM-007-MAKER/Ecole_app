<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Modules\RH\Formations\Events\TrainingCreated;
use App\Modules\RH\Formations\Events\TrainingSessionOpened;
use App\Modules\RH\Formations\Events\EmployeeEnrolled;
use App\Modules\RH\Formations\Events\TrainingCompleted;
use App\Modules\RH\Formations\Events\CertificationGranted;
use App\Modules\RH\Formations\Events\CertificationExpired;
use App\Modules\RH\Formations\Events\CompetencyValidated;

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
            $event instanceof TrainingCreated      => $this->audit->logCreate($event->createdBy, 'rh', 'formation', $event->formationId, $event->toArray()),
            $event instanceof TrainingSessionOpened=> $this->audit->logCreate($event->openedBy, 'rh', 'session_formation', $event->sessionId, $event->toArray()),
            $event instanceof EmployeeEnrolled     => $this->audit->logCreate($event->enrolledBy, 'rh', 'inscription_formation', $event->inscriptionId, $event->toArray()),
            $event instanceof TrainingCompleted    => $this->audit->log($event->validatedBy, 'validation_formation', 'rh', 'inscription_formation', $event->inscriptionId, null, $event->toArray()),
            $event instanceof CertificationGranted => $this->audit->logCreate($event->grantedBy, 'rh', 'certification_employe', $event->empCertId, $event->toArray()),
            $event instanceof CertificationExpired => $this->audit->log(0, 'expiration_certification', 'rh', 'certification_employe', $event->empCertId, null, $event->toArray()),
            $event instanceof CompetencyValidated  => $this->audit->log($event->validatedBy, 'validation_competence', 'rh', 'competence_employe', $event->employeId, null, $event->toArray()),
            default => null,
        };
    }
}
