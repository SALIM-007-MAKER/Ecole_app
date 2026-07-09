<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\RH\Organisation\Events\DepartmentCreated;
use App\Modules\RH\Organisation\Events\DepartmentUpdated;
use App\Modules\RH\Organisation\Events\PositionCreated;
use App\Modules\RH\Organisation\Events\PositionAssigned;
use App\Modules\RH\Organisation\Events\OrganizationUpdated;
use App\Services\AuditService;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof DepartmentCreated  => AuditService::logCreate(
                'rh_departements', $event->departementId,
                ['nom' => $event->nom, 'code' => $event->code],
                $event->createdBy
            ),
            $event instanceof DepartmentUpdated  => AuditService::log(
                'organisation', 'departement_' . $event->action,
                $event->departementId, $event->changes, $event->updatedBy
            ),
            $event instanceof PositionCreated    => AuditService::logCreate(
                'rh_postes', $event->posteId,
                ['intitule' => $event->intitule, 'code' => $event->code],
                $event->createdBy
            ),
            $event instanceof PositionAssigned   => AuditService::log(
                'organisation', 'poste_affecte',
                $event->employe_id,
                ['poste_id' => $event->posteId, 'intitule' => $event->posteIntitule],
                $event->assignedBy
            ),
            $event instanceof OrganizationUpdated => AuditService::log(
                'organisation', $event->typeEntite . '_' . $event->action,
                $event->entiteId, $event->meta, $event->updatedBy
            ),
            default => null,
        };
    }
}
