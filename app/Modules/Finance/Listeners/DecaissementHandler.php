<?php

namespace App\Modules\Finance\Listeners;

use App\Models\UserModel;
use App\Modules\Finance\Events\DecaissementApproved;
use App\Modules\Finance\Events\DecaissementCancelled;
use App\Modules\Finance\Events\DecaissementCreated;
use App\Modules\Finance\Events\DecaissementRejected;
use App\Modules\Finance\Events\DecaissementValidated;
use App\Services\AuditService;
use App\Services\NotificationService;
use Core\Event;
use Core\Listener;
use Core\Logger;
use Core\Tenant\BrandingService;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantContext;

/**
 * Audit + notifications du workflow Décaissements (Blueprint Finance V2 §6.2).
 * Notifications : comptable à la soumission, directeur si approbation requise
 * (montant > seuil configurable), soumetteur informé du rejet.
 */
class DecaissementHandler implements Listener
{
    private AuditService        $audit;
    private NotificationService $notif;
    private UserModel           $userModel;
    private SettingsService     $settings;

    public function __construct()
    {
        $this->audit     = new AuditService();
        $this->notif     = new NotificationService();
        $this->userModel = new UserModel();
        $this->settings  = SettingsService::make();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof DecaissementCreated   => $this->onCreated($event),
            $event instanceof DecaissementValidated => $this->onValidated($event),
            $event instanceof DecaissementApproved  => $this->onApproved($event),
            $event instanceof DecaissementRejected  => $this->onRejected($event),
            $event instanceof DecaissementCancelled => $this->onCancelled($event),
            default                                 => null,
        };
    }

    private function onCreated(DecaissementCreated $e): void
    {
        $this->audit->log($e->saisiParId, 'decaissement.created', 'finance', 'decaissement', $e->decaissementId, null, $e->toArray());

        $this->notifyRoles(['comptable', 'admin'],
            'Décaissement à valider — ' . $e->numero,
            "Un décaissement de " . number_format($e->montant, 0, ',', ' ') . " XOF (« {$e->libelle} ») a été soumis et attend validation."
        );
    }

    private function onValidated(DecaissementValidated $e): void
    {
        $this->audit->log($e->valideParId, 'decaissement.validated', 'finance', 'decaissement', $e->decaissementId,
            ['statut' => 'soumis'], ['statut' => 'valide']);

        if ($e->montant > $this->seuil()) {
            $this->notifyRoles(['directeur', 'admin'],
                'Décaissement en attente d\'approbation — ' . $e->numero,
                "Un décaissement de " . number_format($e->montant, 0, ',', ' ') . " XOF dépasse le seuil d'approbation et nécessite votre validation."
            );
        }
    }

    private function onApproved(DecaissementApproved $e): void
    {
        $this->audit->log($e->approuveParId, 'decaissement.approved', 'finance', 'decaissement', $e->decaissementId,
            ['statut' => 'valide'], ['statut' => 'approuve']);

        $this->notifyRoles(['comptable', 'admin'],
            'Décaissement approuvé — ' . $e->numero,
            "Le décaissement « {$e->numero} » a été approuvé et peut désormais être payé."
        );
    }

    private function onRejected(DecaissementRejected $e): void
    {
        $this->audit->log($e->rejeteParId, 'decaissement.rejected', 'finance', 'decaissement', $e->decaissementId,
            null, ['motif' => $e->motif]);
    }

    private function onCancelled(DecaissementCancelled $e): void
    {
        $this->audit->log($e->annuleParId, 'decaissement.cancelled', 'finance', 'decaissement', $e->decaissementId,
            ['statut' => $e->ancienStatut], ['statut' => 'annule', 'motif' => $e->motif]);
    }

    private function seuil(): float
    {
        $etabId = TenantContext::isSet() ? TenantContext::id() : BrandingService::forCurrentRequest()->etablissementId;
        return (float)$this->settings->get($etabId, 'finance', 'seuil_approbation_decaissement', 50000.0);
    }

    private function notifyRoles(array $roles, string $titre, string $message): void
    {
        try {
            $users = $this->userModel->findAllWithRoles($roles);
            $ids   = array_map(fn($u) => (int)$u->id, $users);
            if ($ids) {
                $this->notif->notifyBulk($ids, 'decaissement', $titre, $message, BASE_URL . '/v2/finance/decaissements');
            }
        } catch (\Throwable $ex) {
            Logger::warning('[Finance/Decaissement] échec notification : ' . $ex->getMessage());
        }
    }
}
