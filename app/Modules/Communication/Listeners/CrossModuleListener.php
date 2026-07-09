<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Communication\Services\RoutageService;
use Core\Event;
use Core\Listener;

/**
 * Écoute les événements de TOUS les modules existants et les route
 * vers RoutageService pour génération des communications appropriées.
 * Zéro couplage : les modules sources n'importent jamais Communication.
 */
class CrossModuleListener implements Listener
{
    private RoutageService $routage;

    public function __construct()
    {
        $this->routage = new RoutageService();
    }

    public function handle(Event $event): void
    {
        try {
            $this->dispatch($event);
        } catch (\Throwable) {
            // Silencieux : la communication ne doit jamais bloquer le module source
        }
    }

    private function dispatch(Event $event): void
    {
        $class = get_class($event);

        match (true) {

            // ── MODULE ACADÉMIQUE ──────────────────────────────────────────
            $class === \App\Modules\Academique\Events\BulletinPublished::class
                => $this->routage->router($event, 'bulletin_publie'),

            $class === \App\Modules\Academique\Events\EvaluationPublished::class
                => $this->routage->router($event, 'evaluation_planifiee'),

            // ── MODULE FINANCE ─────────────────────────────────────────────
            $class === \App\Modules\Finance\Events\InvoiceCreated::class
                => $this->routage->router($event, 'facture_emise'),

            $class === \App\Modules\Finance\Events\PaymentCompleted::class
                => $this->routage->router($event, 'paiement_recu'),

            $class === \App\Modules\Finance\Events\PaymentRefunded::class
                => $this->routage->router($event, 'remboursement'),

            $class === \App\Modules\Finance\Events\InvoiceCancelled::class
                => $this->routage->router($event, 'facture_annulee'),

            // ── MODULE VIE SCOLAIRE — Absences ─────────────────────────────
            $class === \App\Modules\VieScolaire\Absences\Events\StudentAbsent::class
                => $this->routage->router($event, 'absence_eleve'),

            $class === \App\Modules\VieScolaire\Absences\Events\AbsenceJustified::class
                => $this->routage->router($event, 'justificatif_accepte'),

            $class === \App\Modules\VieScolaire\Absences\Events\AbsenceRejected::class
                => $this->routage->router($event, 'justificatif_refuse'),

            // ── MODULE VIE SCOLAIRE — Récompenses ─────────────────────────
            $class === \App\Modules\VieScolaire\Recompenses\Events\RewardGranted::class
                => $this->routage->router($event, 'recompense'),

            // ── MODULE VIE SCOLAIRE — Activités ───────────────────────────
            $class === \App\Modules\VieScolaire\Activites\Events\StudentRegisteredToActivity::class
                => $this->routage->router($event, 'inscription_activite'),

            $class === \App\Modules\VieScolaire\Activites\Events\ActivityCancelled::class
                => $this->routage->router($event, 'activite_annulee'),

            // ── MODULE VIE SCOLAIRE — Emplois du temps ────────────────────
            $class === \App\Modules\VieScolaire\EmploisDuTemps\Events\TimetablePublished::class
                => $this->routage->router($event, 'emploi_du_temps'),

            // ── MODULE RH — Congés ─────────────────────────────────────────
            $class === \App\Modules\RH\Conges\Events\LeaveRequested::class
                => $this->routage->router($event, 'conge_demande'),

            $class === \App\Modules\RH\Conges\Events\LeaveApproved::class
                => $this->routage->router($event, 'conge_approuve'),

            $class === \App\Modules\RH\Conges\Events\LeaveRejected::class
                => $this->routage->router($event, 'conge_rejete'),

            // ── MODULE RH — Contrats ───────────────────────────────────────
            $class === \App\Modules\RH\Contrats\Events\ContractExpired::class
                => $this->routage->router($event, 'contrat_expire'),

            // ── MODULE RH — Évaluations ────────────────────────────────────
            $class === \App\Modules\RH\Evaluations\Events\EvaluationPublished::class
                => $this->routage->router($event, 'evaluation_rh'),

            // ── MODULE RH — Formations ─────────────────────────────────────
            $class === \App\Modules\RH\Formations\Events\TrainingCreated::class
                => $this->routage->router($event, 'formation_disponible'),

            $class === \App\Modules\RH\Formations\Events\CertificationExpired::class
                => $this->routage->router($event, 'certification_expire'),

            // ── MODULE DOCUMENTS ───────────────────────────────────────────
            $class === \App\Modules\Documents\Events\DocumentShared::class
                => $this->routage->router($event, 'document_partage'),

            $class === \App\Modules\Documents\Events\DocumentSignatureRequested::class
                => $this->routage->router($event, 'signature_requise'),

            $class === \App\Modules\Documents\Events\DocumentExpired::class
                => $this->routage->router($event, 'document_expire'),

            default => null,
        };
    }
}

