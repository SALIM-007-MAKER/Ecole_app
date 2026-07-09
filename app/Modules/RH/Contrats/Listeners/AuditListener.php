<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\RH\Contrats\Events\ContractCreated;
use App\Modules\RH\Contrats\Events\ContractUpdated;
use App\Modules\RH\Contrats\Events\ContractRenewed;
use App\Modules\RH\Contrats\Events\ContractExpired;
use App\Modules\RH\Contrats\Events\ContractTerminated;
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
            $event instanceof ContractCreated    => $this->onCreated($event),
            $event instanceof ContractUpdated    => $this->onUpdated($event),
            $event instanceof ContractRenewed    => $this->onRenewed($event),
            $event instanceof ContractExpired    => $this->onExpired($event),
            $event instanceof ContractTerminated => $this->onTerminated($event),
            default                              => null,
        };
    }

    private function onCreated(ContractCreated $e): void
    {
        $this->audit->logCreate($e->createdBy, 'rh', 'contrat', $e->contratId, $e->toArray());
    }

    private function onUpdated(ContractUpdated $e): void
    {
        $this->audit->log($e->updatedBy, $e->action, 'rh', 'contrat', $e->contratId, null, $e->changes);
    }

    private function onRenewed(ContractRenewed $e): void
    {
        $this->audit->log(
            $e->renewedBy, 'renouvellement', 'rh', 'contrat', $e->ancienContratId,
            ['contrat_id' => $e->ancienContratId],
            ['nouveau_contrat_id' => $e->nouveauContratId, 'numero' => $e->nouveauNumero]
        );
    }

    private function onExpired(ContractExpired $e): void
    {
        $this->audit->log(null, 'expiration', 'rh', 'contrat', $e->contratId, null, ['date_fin' => $e->dateFin]);
    }

    private function onTerminated(ContractTerminated $e): void
    {
        $this->audit->log(
            $e->terminatedBy, 'resiliation', 'rh', 'contrat', $e->contratId,
            null, ['motif' => $e->motif, 'date' => $e->dateResiliation]
        );
    }
}
