<?php

namespace App\Modules\VieScolaire\Discipline\Listeners;

use App\Models\EleveModel;
use App\Models\UserModel;
use App\Modules\VieScolaire\Discipline\Events\DisciplineAppealSubmitted;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
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
        try {
            if ($event instanceof DisciplineCaseCreated) {
                $this->onIncident($event);
            } elseif ($event instanceof DisciplinaryActionAssigned) {
                $this->onSanction($event);
            } elseif ($event instanceof DisciplineCaseClosed) {
                $this->onCloture($event);
            } elseif ($event instanceof DisciplineAppealSubmitted) {
                $this->onAppel($event);
            }
        } catch (\Throwable $ex) {
            Logger::warning('[Discipline/Notif] échec notification : ' . $ex->getMessage());
        }
    }

    private function parentId(int $eleveId): ?int
    {
        $eleve = $this->eleveModel->findById($eleveId);
        return ($eleve && !empty($eleve->parent_id)) ? (int)$eleve->parent_id : null;
    }

    private function onIncident(DisciplineCaseCreated $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        $this->notif->notify(
            $parentId, 'discipline',
            'Incident disciplinaire signalé',
            'Un incident disciplinaire (' . $e->gravite . ') a été signalé concernant votre enfant.',
            BASE_URL . '/v2/vie-scolaire/discipline/' . $e->dossierId
        );
    }

    private function onSanction(DisciplinaryActionAssigned $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        $dateF = date('d/m/Y', strtotime($e->dateSanction));
        $this->notif->notify(
            $parentId, 'discipline',
            'Sanction prononcée',
            "Une sanction ({$e->typeSanction}) a été prononcée le {$dateF}.",
            BASE_URL . '/v2/vie-scolaire/discipline/' . $e->dossierId
        );
    }

    private function onCloture(DisciplineCaseClosed $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        $this->notif->notify(
            $parentId, 'discipline',
            'Dossier disciplinaire clos',
            'Le dossier disciplinaire de votre enfant a été clos.',
            BASE_URL . '/v2/vie-scolaire/discipline/' . $e->dossierId
        );
    }

    private function onAppel(DisciplineAppealSubmitted $e): void
    {
        $staff = $this->userModel->findAllWithRoles(['admin', 'directeur']);
        $eleve = $this->eleveModel->findById($e->eleveId);
        $nom   = $eleve ? ($eleve->prenom . ' ' . $eleve->nom) : "élève #{$e->eleveId}";
        foreach ($staff as $u) {
            $this->notif->notify(
                (int)$u->id, 'discipline',
                'Appel de sanction soumis',
                "Un appel a été déposé par la famille de {$nom} concernant une sanction — à traiter.",
                BASE_URL . '/v2/vie-scolaire/discipline/' . $e->dossierId
            );
        }
    }
}
