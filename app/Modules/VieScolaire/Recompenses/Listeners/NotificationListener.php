<?php

namespace App\Modules\VieScolaire\Recompenses\Listeners;

use App\Models\EleveModel;
use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Events\RewardUpdated;
use App\Services\NotificationService;
use Core\Event;
use Core\Listener;
use Core\Logger;

class NotificationListener implements Listener
{
    private NotificationService $notif;
    private EleveModel           $eleveModel;

    public function __construct()
    {
        $this->notif      = new NotificationService();
        $this->eleveModel = new EleveModel();
    }

    public function handle(Event $event): void
    {
        try {
            if ($event instanceof RewardGranted) {
                $this->onGranted($event);
            } elseif ($event instanceof RewardRevoked) {
                $this->onRevoked($event);
            }
            // RewardUpdated : modification administrative mineure, pas de
            // notification (évite le bruit pour un simple correctif).
        } catch (\Throwable $ex) {
            Logger::warning('[Recompenses/Notif] échec notification : ' . $ex->getMessage());
        }
    }

    private function parentId(int $eleveId): ?int
    {
        $eleve = $this->eleveModel->findById($eleveId);
        return ($eleve && !empty($eleve->parent_id)) ? (int)$eleve->parent_id : null;
    }

    private function onGranted(RewardGranted $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        $this->notif->notify(
            $parentId, 'recompense',
            'Récompense attribuée — félicitations !',
            "Une récompense (niveau {$e->niveau}) a été attribuée : {$e->motif}",
            BASE_URL . '/v2/vie-scolaire/recompenses/' . $e->rewardId
        );
    }

    private function onRevoked(RewardRevoked $e): void
    {
        $parentId = $this->parentId($e->eleveId);
        if ($parentId === null) {
            return;
        }
        $this->notif->notify(
            $parentId, 'recompense',
            'Récompense révoquée',
            'Une récompense précédemment attribuée a été révoquée' . ($e->motifRevocation ? " : {$e->motifRevocation}" : '.'),
            BASE_URL . '/v2/vie-scolaire/recompenses/' . $e->rewardId
        );
    }
}
