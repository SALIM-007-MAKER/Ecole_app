<?php

namespace App\Modules\Academique\Listeners;

use App\Modules\Academique\Events\PeriodeActivated;
use App\Modules\Academique\Events\PeriodeArchived;
use App\Modules\Academique\Events\PeriodeCreated;
use App\Modules\Academique\Events\PeriodeLocked;
use App\Modules\Academique\Events\PeriodeUnlocked;
use App\Modules\Academique\Events\PeriodeUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class PeriodeHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof PeriodeCreated   => $this->onCreated($event),
            $event instanceof PeriodeUpdated   => $this->onUpdated($event),
            $event instanceof PeriodeActivated => $this->onActivated($event),
            $event instanceof PeriodeLocked    => $this->onLocked($event),
            $event instanceof PeriodeUnlocked  => $this->onUnlocked($event),
            $event instanceof PeriodeArchived  => $this->onArchived($event),
            default => null,
        };
    }

    private function onCreated(PeriodeCreated $event): void
    {
        $this->audit->logCreate(
            $event->createdById,
            'academique',
            'periode_scolaire',
            $event->periodeId,
            $event->toArray(),
        );
    }

    private function onUpdated(PeriodeUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'academique',
            'periode_scolaire',
            $event->periodeId,
            $event->changedFields['avant'] ?? null,
            $event->changedFields['apres'] ?? null,
        );
    }

    private function onActivated(PeriodeActivated $event): void
    {
        $this->audit->log(
            $event->activatedById,
            'activer',
            'academique',
            'periode_scolaire',
            $event->periodeId,
            null,
            ['nom' => $event->nom, 'annee_scolaire' => $event->anneeScolaire],
        );
    }

    private function onLocked(PeriodeLocked $event): void
    {
        // Le verrouillage est orthogonal au statut (voir PeriodeScolaireService) :
        // il ne modifie pas `statut`, uniquement `verrouille_par`/`verrouille_le`.
        $this->audit->log(
            $event->lockedById,
            'verrouiller',
            'academique',
            'periode_scolaire',
            $event->periodeId,
            ['verrouille' => false],
            ['nom' => $event->nom, 'verrouille' => true],
        );
    }

    private function onUnlocked(PeriodeUnlocked $event): void
    {
        $this->audit->log(
            $event->unlockedById,
            'deverrouiller',
            'academique',
            'periode_scolaire',
            $event->periodeId,
            ['verrouille' => true],
            ['nom' => $event->nom, 'verrouille' => false],
        );
    }

    private function onArchived(PeriodeArchived $event): void
    {
        $this->audit->logDelete(
            $event->archivedById,
            'academique',
            'periode_scolaire',
            $event->periodeId,
            ['nom' => $event->nom, 'annee_scolaire' => $event->anneeScolaire],
        );
    }
}
