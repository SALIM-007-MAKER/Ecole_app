<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\RH\Affectations\Events\AssignmentCreated;
use App\Modules\RH\Affectations\Events\AssignmentUpdated;
use App\Modules\RH\Affectations\Events\AssignmentTransferred;
use App\Modules\RH\Affectations\Events\AssignmentArchived;
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
            $event instanceof AssignmentCreated     => $this->onCreated($event),
            $event instanceof AssignmentUpdated     => $this->onUpdated($event),
            $event instanceof AssignmentTransferred => $this->onTransferred($event),
            $event instanceof AssignmentArchived    => $this->onArchived($event),
            default                                 => null,
        };
    }

    private function onCreated(AssignmentCreated $e): void
    {
        $this->audit->logCreate($e->createdBy, 'rh', 'affectation', $e->affectationId, $e->toArray());
    }

    private function onUpdated(AssignmentUpdated $e): void
    {
        $this->audit->log($e->updatedBy, $e->action, 'rh', 'affectation', $e->affectationId, null, $e->changes);
    }

    private function onTransferred(AssignmentTransferred $e): void
    {
        $this->audit->log($e->transferredBy, 'transfert', 'rh', 'affectation', $e->affectationId, $e->from, $e->to);
    }

    private function onArchived(AssignmentArchived $e): void
    {
        $this->audit->log($e->archivedBy, 'archivage', 'rh', 'affectation', $e->affectationId, null, ['motif' => $e->motif]);
    }
}
