<?php

namespace App\Modules\Scolarite\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Scolarite\Events\MatiereCreated;
use App\Modules\Scolarite\Events\MatiereUpdated;
use App\Modules\Scolarite\Events\MatiereArchived;
use App\Modules\Scolarite\Events\MatiereAssignedToClasse;
use App\Modules\Scolarite\Events\MatiereRemovedFromClasse;
use App\Services\AuditService;

class MatiereHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof MatiereCreated          => $this->onCreated($event),
            $event instanceof MatiereUpdated          => $this->onUpdated($event),
            $event instanceof MatiereArchived         => $this->onArchived($event),
            $event instanceof MatiereAssignedToClasse => $this->onAssigned($event),
            $event instanceof MatiereRemovedFromClasse=> $this->onRemoved($event),
            default                                   => null,
        };
    }

    private function onCreated(MatiereCreated $e): void
    {
        $this->audit->logCreate(
            'matieres',
            $e->matiereId,
            ['nom' => $e->nom, 'coefficient' => $e->coefficient],
            $e->createdById
        );
    }

    private function onUpdated(MatiereUpdated $e): void
    {
        $this->audit->logUpdate(
            'matieres',
            $e->matiereId,
            $e->changedFields,
            $e->updatedById
        );
    }

    private function onArchived(MatiereArchived $e): void
    {
        $this->audit->logDelete(
            'matieres',
            $e->matiereId,
            ['nom' => $e->nom, 'action' => 'archive'],
            $e->archivedById
        );
    }

    private function onAssigned(MatiereAssignedToClasse $e): void
    {
        $this->audit->log(
            'enseignements',
            $e->matiereId,
            'assign_to_classe',
            [
                'classe_id'      => $e->classeId,
                'professeur_id'  => $e->professeurId,
                'annee_scolaire' => $e->anneeScolaire,
            ],
            $e->assignedById
        );
    }

    private function onRemoved(MatiereRemovedFromClasse $e): void
    {
        $this->audit->log(
            'enseignements',
            $e->matiereId,
            'remove_from_classe',
            ['classe_id' => $e->classeId],
            $e->removedById
        );
    }
}
