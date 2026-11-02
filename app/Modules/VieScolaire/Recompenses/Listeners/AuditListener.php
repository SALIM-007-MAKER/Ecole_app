<?php

namespace App\Modules\VieScolaire\Recompenses\Listeners;

use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Events\RewardUpdated;
use App\Services\AuditService;
use Core\Event;
use Core\Listener;
use Core\Logger;

/**
 * Journal des récompenses : fichier texte (historique) ET journal d'audit de l'établissement (table audit_logs, par établissement).
 * Identifiants uniquement : aucun nom d'élève dans la trace.
 */
class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        if ($event instanceof RewardGranted) {
            Logger::info('recompenses.audit', [
                'action'       => 'recompense_attribuee',
                'reward_id'    => $event->rewardId,
                'eleve_id'     => $event->eleveId,
                'classe_id'    => $event->classeId,
                'categorie'    => $event->categorieCode,
                'niveau'       => $event->niveau,
                'attribue_par' => $event->attribueParId,
            ]);
            $this->audit->log($event->attribueParId, 'recompense_attribuee', 'vie_scolaire', 'recompense', $event->rewardId, null, [
                'eleve_id' => $event->eleveId, 'classe_id' => $event->classeId, 'categorie' => $event->categorieCode, 'niveau' => $event->niveau,
            ]);
        } elseif ($event instanceof RewardUpdated) {
            Logger::info('recompenses.audit', [
                'action'      => 'recompense_modifiee',
                'reward_id'   => $event->rewardId,
                'eleve_id'    => $event->eleveId,
                'modifie_par' => $event->modifieParId,
            ]);
            $this->audit->log($event->modifieParId, 'recompense_modifiee', 'vie_scolaire', 'recompense', $event->rewardId, null, [
                'eleve_id' => $event->eleveId, 'classe_id' => $event->classeId,
            ]);
        } elseif ($event instanceof RewardRevoked) {
            Logger::warning('recompenses.audit', [
                'action'      => 'recompense_revoquee',
                'reward_id'   => $event->rewardId,
                'eleve_id'    => $event->eleveId,
                'motif'       => $event->motifRevocation,
                'revoque_par' => $event->revoqueParId,
            ]);
            $this->audit->log($event->revoqueParId, 'recompense_revoquee', 'vie_scolaire', 'recompense', $event->rewardId, null, [
                'eleve_id' => $event->eleveId, 'classe_id' => $event->classeId, 'motif' => $event->motifRevocation,
            ]);
        }
    }
}
