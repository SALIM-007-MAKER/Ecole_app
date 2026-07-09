<?php

namespace App\Modules\VieScolaire\Retards\Listeners;

use App\Modules\VieScolaire\Retards\Events\StudentLate;
use App\Modules\VieScolaire\Retards\Repositories\LateRepository;
use App\Modules\VieScolaire\Retards\Services\LateService;
use Core\Event;
use Core\EventDispatcher;
use Core\Listener;
use Core\Logger;

/**
 * Gère la vérification du seuil d'alerte retards après chaque nouveau retard issu de Présences.
 * Les retards manuels (LateService::enregistrerManuellement) appellent verifierSeuil directement.
 */
class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof StudentLate) {
            $this->onStudentLate($event);
        }
    }

    private function onStudentLate(StudentLate $e): void
    {
        Logger::info(sprintf(
            '[Retards/Stats] Retard vs_retards créé — id:%d élève:%d annee:%s',
            $e->retardId, $e->eleveId, $e->anneeScolaire,
        ));

        // Vérification du seuil d'alerte
        try {
            $repo  = new LateRepository();
            $total = $repo->countByEleveAndAnnee($e->eleveId, $e->anneeScolaire);

            if ($total >= LateService::SEUIL_ALERTE) {
                (new LateService())->verifierSeuil(
                    $e->eleveId,
                    $e->anneeScolaire,
                    $e->retardId,
                    $e->classeId,
                );
            }
        } catch (\Throwable $t) {
            Logger::error('[Retards/Stats] Erreur seuil : ' . $t->getMessage());
        }
    }
}
