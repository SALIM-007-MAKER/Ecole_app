<?php

namespace App\Controllers;

use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformSubscriptionService;
use Core\Platform\SubscriptionException;
use Core\Session;

/**
 * Abonnements d'un établissement. Lecture : sur la fiche de l'établissement. Écriture : super_admin uniquement (conditions commerciales
 * sensibles). Toute erreur métier est affichée telle quelle (message destiné à l'opérateur) ; une erreur technique est déjà convertie par
 * le service en message générique avec référence.
 */
class PlatformSubscriptionController extends PlatformController
{
    private function operatorUserId(): int
    {
        return (int)(PlatformAuth::current()['id'] ?? 0);
    }

    /** @param callable():string $operation renvoie le message de succès */
    private function executer(int $etablissementId, callable $operation): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();
        try {
            Session::flash('success', $operation());
        } catch (SubscriptionException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/etablissements/' . $etablissementId);
    }

    private function service(): PlatformSubscriptionService
    {
        return PlatformSubscriptionService::make();
    }

    public function souscrire(int $id): void
    {
        $this->executer($id, function () use ($id): string {
            $this->service()->souscrire($id, (int)$this->request->post('plan_id', 0), (string)$this->request->post('cycle', ''), $this->operatorUserId());
            return 'Abonnement souscrit.';
        });
    }

    public function renouveler(int $id): void
    {
        $this->executer($id, function () use ($id): string {
            $cycle = (string)$this->request->post('cycle', '');
            $this->service()->renouveler($id, $this->operatorUserId(), $cycle !== '' ? $cycle : null, (string)$this->request->post('conserver_conditions', '') === '1');
            return 'Abonnement renouvelé.';
        });
    }

    public function annuler(int $id): void
    {
        $this->executer($id, function () use ($id): string {
            $this->service()->annuler($id, (string)$this->request->post('motif', ''), $this->operatorUserId());
            return 'Abonnement annulé : l\'accès est maintenu jusqu\'à son échéance.';
        });
    }

    public function prolongerEssai(int $id): void
    {
        $this->executer($id, function () use ($id): string {
            $this->service()->prolongerEssai($id, (int)$this->request->post('jours', 0), (string)$this->request->post('motif', ''), $this->operatorUserId());
            return 'Essai prolongé.';
        });
    }
}
