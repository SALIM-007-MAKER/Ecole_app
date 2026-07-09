<?php

namespace App\Modules\Scolarite\Listeners;

use App\Modules\Scolarite\Events\ClasseAffectee;
use App\Modules\Scolarite\Events\ClasseChanged;
use App\Modules\Scolarite\Events\EleveInscrit;
use App\Modules\Scolarite\Events\InscriptionCancelled;
use App\Modules\Scolarite\Events\InscriptionCreated;
use App\Modules\Scolarite\Events\InscriptionUpdated;
use App\Modules\Scolarite\Events\InscriptionValidee;
use App\Modules\Scolarite\Events\ReinscriptionCreated;
use App\Modules\Scolarite\Events\SchoolYearChanged;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class InscriptionHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            // Anciens événements Phase 1.1 (compatibilité)
            $event instanceof EleveInscrit       => $this->onEleveInscrit($event),
            $event instanceof InscriptionValidee => $this->onInscriptionValidee($event),
            $event instanceof ClasseAffectee     => $this->onClasseAffectee($event),
            // Nouveaux événements Phase 1.4
            $event instanceof InscriptionCreated   => $this->onCreated($event),
            $event instanceof InscriptionUpdated   => $this->onUpdated($event),
            $event instanceof InscriptionCancelled => $this->onCancelled($event),
            $event instanceof ReinscriptionCreated => $this->onReinscription($event),
            $event instanceof ClasseChanged        => $this->onClasseChanged($event),
            $event instanceof SchoolYearChanged    => $this->onYearChanged($event),
            default => null,
        };
    }

    // ─── Phase 1.1 (maintenus pour compatibilité) ────────────────────────────

    private function onEleveInscrit(EleveInscrit $event): void
    {
        $this->audit->logCreate(
            $event->inscritParId,
            'scolarite',
            'inscriptions',
            null,
            $event->toArray()
        );
    }

    private function onInscriptionValidee(InscriptionValidee $event): void
    {
        $this->audit->logUpdate(
            $event->valideParId,
            'scolarite',
            'inscriptions',
            $event->inscriptionId,
            ['statut' => 'en_attente'],
            ['statut' => 'validee', ...$event->toArray()]
        );
    }

    private function onClasseAffectee(ClasseAffectee $event): void
    {
        $this->audit->logCreate(
            $event->affecteParId,
            'scolarite',
            'enseignements',
            null,
            $event->toArray()
        );
    }

    // ─── Phase 1.4 ───────────────────────────────────────────────────────────

    private function onCreated(InscriptionCreated $event): void
    {
        $this->audit->logCreate(
            $event->createdById,
            'scolarite',
            'inscription',
            $event->inscriptionId,
            $event->toArray(),
        );
    }

    private function onUpdated(InscriptionUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'update',
            'scolarite',
            'inscription',
            $event->inscriptionId,
            $event->changedFields['avant'] ?? null,
            $event->changedFields['apres'] ?? null,
        );
    }

    private function onCancelled(InscriptionCancelled $event): void
    {
        $this->audit->log(
            $event->cancelledById,
            'annuler',
            'scolarite',
            'inscription',
            $event->inscriptionId,
            null,
            ['statut' => 'annulee', 'motif' => $event->motif],
        );
    }

    private function onReinscription(ReinscriptionCreated $event): void
    {
        $this->audit->logCreate(
            $event->createdById,
            'scolarite',
            'reinscription',
            $event->inscriptionId,
            $event->toArray(),
        );
    }

    private function onClasseChanged(ClasseChanged $event): void
    {
        $this->audit->log(
            $event->changedById,
            'changer_classe',
            'scolarite',
            'inscription',
            $event->inscriptionId,
            ['classe_id' => $event->ancienneClasseId],
            ['classe_id' => $event->nouvelleClasseId],
        );
    }

    private function onYearChanged(SchoolYearChanged $event): void
    {
        $this->audit->log(
            $event->changedById,
            'changer_annee',
            'scolarite',
            'inscription',
            $event->inscriptionId,
            ['annee_scolaire' => $event->ancienneAnnee],
            ['annee_scolaire' => $event->nouvelleAnnee],
        );
    }
}
