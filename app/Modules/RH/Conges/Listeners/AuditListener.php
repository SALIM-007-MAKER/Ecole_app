<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Listeners;

use App\Modules\RH\Conges\Events\LeaveApproved;
use App\Modules\RH\Conges\Events\LeaveCancelled;
use App\Modules\RH\Conges\Events\LeaveFinished;
use App\Modules\RH\Conges\Events\LeaveRejected;
use App\Modules\RH\Conges\Events\LeaveRequested;
use App\Modules\RH\Conges\Events\LeaveStarted;
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
        match (true) {
            $event instanceof LeaveRequested => $this->onRequested($event),
            $event instanceof LeaveApproved  => $this->onApproved($event),
            $event instanceof LeaveRejected  => $this->onRejected($event),
            $event instanceof LeaveCancelled => $this->onCancelled($event),
            $event instanceof LeaveStarted   => $this->onStarted($event),
            $event instanceof LeaveFinished  => $this->onFinished($event),
            default                          => null,
        };
    }

    private function onRequested(LeaveRequested $e): void
    {
        $this->audit->logCreate($e->createdBy, 'rh', 'conge', $e->congeId, $e->toArray());
    }

    private function onApproved(LeaveApproved $e): void
    {
        $this->audit->log($e->approvedBy, 'approuve', 'rh', 'conge', $e->congeId, null, $e->toArray());
    }

    private function onRejected(LeaveRejected $e): void
    {
        $this->audit->log($e->rejectedBy, 'rejete', 'rh', 'conge', $e->congeId, null, $e->toArray());
    }

    private function onCancelled(LeaveCancelled $e): void
    {
        $this->audit->log($e->cancelledBy, 'annule', 'rh', 'conge', $e->congeId, null, $e->toArray());
    }

    private function onStarted(LeaveStarted $e): void
    {
        $this->audit->log($e->startedBy, 'demarre', 'rh', 'conge', $e->congeId, null, $e->toArray());
    }

    private function onFinished(LeaveFinished $e): void
    {
        $this->audit->log($e->finishedBy, 'termine', 'rh', 'conge', $e->congeId, null, $e->toArray());
    }
}
