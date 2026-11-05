<?php

namespace App\Controllers;

use App\Shared\Http\LoginThrottle;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPasswordPolicy;
use Core\Session;

/**
 * Compte de l'opérateur connecté : changement de mot de passe.
 *
 * Seule page du portail accessible tant que l'opérateur DOIT changer son mot de passe (premier mot de passe généré, ou récupération
 * d'accès). Le mot de passe actuel est exigé ; les essais sont limités.
 */
class PlatformAccountController extends PlatformController
{
    protected bool $accessibleAvecMotDePasseAChanger = true;
    /** Le mot de passe se change AVANT l'activation de la 2FA : cette page reste ouverte tant que la 2FA est à régler. */
    protected bool $accessibleAvecDeuxFacteursARegler = true;

    public function showPassword(): void
    {
        $this->requirePlatformAuth();

        $this->render('platform/compte/mot-de-passe', [
            'title'      => 'Mon mot de passe',
            'errors'     => Session::getFlash('errors', []),
            'obligatoire' => PlatformAuth::mustChangePassword(),
            'longueur'   => PlatformPasswordPolicy::LONGUEUR_MIN,
        ], 'platform');
    }

    public function updatePassword(): void
    {
        $this->requirePlatformAuth();
        $this->verifyCsrf();

        $id = $this->operatorId();
        $throttle = $this->throttle();
        $cle = 'pwc:' . $id;
        if ($throttle->retryAfter($cle, 5) > 0) {
            Session::flash('errors', ['Trop de tentatives. Réessayez dans quelques minutes.']);
            $this->redirect(BASE_URL . '/platform/compte/mot-de-passe');
        }

        $erreurs = PlatformOperatorService::make()->changerMotDePasse(
            $id,
            (string)$this->request->post('current_password', ''),
            (string)$this->request->post('new_password', ''),
            (string)$this->request->post('new_password_confirmation', '')
        );
        if ($erreurs !== []) {
            // Seul un mot de passe actuel erroné est un échec d'authentification : une confirmation mal tapée ou un mot de passe
            // trop faible ne doivent pas verrouiller la page.
            if (in_array(PlatformOperatorService::ERREUR_MDP_ACTUEL, $erreurs, true)) {
                $throttle->recordFailure($cle);
            }
            Session::flash('errors', $erreurs);
            $this->redirect(BASE_URL . '/platform/compte/mot-de-passe');
        }

        $throttle->clear($cle);
        PlatformAuth::passwordChanged();
        Session::regenerate();
        Session::flash('success', 'Mot de passe modifié.');
        $this->redirect(BASE_URL . '/platform/dashboard');
    }
}
