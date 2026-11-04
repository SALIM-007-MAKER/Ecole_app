<?php

namespace App\Controllers;

use Core\Platform\PaymentException;
use Core\Platform\PlatformController;
use Core\Platform\PlatformPaymentService;
use Core\Session;

/**
 * Paiements manuels des abonnements. Saisie, annulation et consultation du reçu : super_admin uniquement (données financières).
 * Toute erreur métier est affichée telle quelle (message destiné à l'opérateur) ; une erreur technique est déjà convertie par le service
 * en message générique avec référence.
 */
class PlatformPaymentController extends PlatformController
{
    private function service(): PlatformPaymentService
    {
        return PlatformPaymentService::make();
    }

    public function store(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();
        try {
            $paiementId = $this->service()->enregistrer($id, (int)$this->request->post('subscription_id', 0), [
                'montant'           => $this->request->post('montant', ''),
                'mode'              => $this->request->post('mode', ''),
                'reference_externe' => $this->request->post('reference_externe', ''),
                'date_paiement'     => $this->request->post('date_paiement', ''),
                'note'              => $this->request->post('note', ''),
            ], $this->operatorId());
            Session::flash('success', 'Paiement enregistré.');
            $this->redirect(BASE_URL . '/platform/paiements/' . $paiementId);
        } catch (PaymentException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }

    public function annuler(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();
        $paiement = $this->service()->find($id);
        try {
            $this->service()->annuler($id, (string)$this->request->post('motif', ''), $this->operatorId());
            Session::flash('success', 'Paiement annulé.');
        } catch (PaymentException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . ($paiement !== null ? '/platform/paiements/' . $id : '/platform/etablissements'));
    }

    /** Reçu / fiche d'un paiement (imprimable). */
    public function show(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $paiement = $this->service()->find($id);
        if ($paiement === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Paiement introuvable'], 'none');
            return;
        }
        $this->render('platform/paiements/show', [
            'title'    => 'Paiement ' . ($paiement['reference'] ?? ('#' . $id)),
            'paiement' => $paiement,
            'modes'    => PlatformPaymentService::MODES,
        ], 'platform');
    }
}
