<?php

namespace App\Modules\VieScolaire\Retards\Listeners;

use App\Models\EleveModel;
use App\Models\UserModel;
use App\Modules\VieScolaire\Retards\Events\LateJustified;
use App\Modules\VieScolaire\Retards\Events\LateRejected;
use App\Modules\VieScolaire\Retards\Events\LateThresholdReached;
use App\Modules\VieScolaire\Retards\Events\StudentLate;
use App\Services\NotificationService;
use Core\Event;
use Core\Listener;
use Core\Logger;

class NotificationListener implements Listener
{
    private NotificationService $notif;
    private EleveModel           $eleveModel;
    private UserModel            $userModel;

    public function __construct()
    {
        $this->notif      = new NotificationService();
        $this->eleveModel = new EleveModel();
        $this->userModel  = new UserModel();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof LateThresholdReached => $this->onThreshold($event),
            $event instanceof LateJustified        => $this->onJustified($event),
            $event instanceof LateRejected         => $this->onRejected($event),
            $event instanceof StudentLate          => $this->onStudentLate($event),
            default                                => null,
        };
    }

    /** Retourne le parent_id de l'élève, ou null s'il n'y en a pas / élève introuvable. */
    private function parentId(int $eleveId): ?int
    {
        $eleve = $this->eleveModel->findById($eleveId);
        return ($eleve && !empty($eleve->parent_id)) ? (int)$eleve->parent_id : null;
    }

    private function onStudentLate(StudentLate $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        try {
            $dateF = date('d/m/Y', strtotime($e->dateRetard));
            $this->notif->notify(
                $parentId, 'retard',
                "Retard signalé — {$dateF}",
                "Un retard de {$e->retardMinutes} min a été enregistré le {$dateF} (arrivée {$e->heureArrivee}).",
                BASE_URL . '/v2/vie-scolaire/retards/' . $e->retardId
            );
        } catch (\Throwable $ex) {
            Logger::warning('[Retards/Notif] échec notification StudentLate : ' . $ex->getMessage());
        }
    }

    private function onThreshold(LateThresholdReached $e): void
    {
        try {
            $staff = $this->userModel->findAllWithRoles(['admin', 'directeur', 'secretaire']);
            $eleve = $this->eleveModel->findById($e->eleveId);
            $nom   = $eleve ? ($eleve->prenom . ' ' . $eleve->nom) : "élève #{$e->eleveId}";
            foreach ($staff as $u) {
                $this->notif->notify(
                    (int)$u->id, 'retard',
                    'Seuil de retards atteint',
                    "{$nom} a atteint {$e->totalRetards} retard(s), au-delà du seuil ({$e->seuilAtteint}).",
                    BASE_URL . '/v2/vie-scolaire/retards?eleve_id=' . $e->eleveId
                );
            }
        } catch (\Throwable $ex) {
            Logger::warning('[Retards/Notif] échec notification LateThresholdReached : ' . $ex->getMessage());
        }
    }

    private function onJustified(LateJustified $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        try {
            $this->notif->notify(
                $parentId, 'retard',
                'Justification de retard acceptée',
                'Votre justification pour le retard a été acceptée.',
                BASE_URL . '/v2/vie-scolaire/retards/' . $e->retardId
            );
        } catch (\Throwable $ex) {
            Logger::warning('[Retards/Notif] échec notification LateJustified : ' . $ex->getMessage());
        }
    }

    private function onRejected(LateRejected $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        try {
            $this->notif->notify(
                $parentId, 'retard',
                'Justification de retard refusée',
                'Votre justification pour le retard a été refusée' . ($e->motifRefus ? " : {$e->motifRefus}" : '.'),
                BASE_URL . '/v2/vie-scolaire/retards/' . $e->retardId
            );
        } catch (\Throwable $ex) {
            Logger::warning('[Retards/Notif] échec notification LateRejected : ' . $ex->getMessage());
        }
    }
}
