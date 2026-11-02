<?php

namespace App\Controllers;

use App\Shared\Http\LoginThrottle;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformOperatorService;
use Core\Session;

/**
 * Connexion/déconnexion du portail Super-Admin SaaS.
 *
 * Flux totalement indépendant de AuthController::login() (établissements) ET de la table `users` : un opérateur possède sa propre
 * identité (`platform_operators` : e-mail, mot de passe haché, niveau). Aucun compte d'établissement ne peut ouvrir une session
 * opérateur, et inversement. Ne positionne JAMAIS Session::setUser() — uniquement PlatformAuth::login().
 *
 * Chaque connexion réussie, échouée, bloquée et chaque déconnexion est inscrite dans le journal d'audit global (les tentatives échouées
 * ou bloquées sont plafonnées à 30 lignes par adresse IP et par fenêtre, pour qu'un attaquant ne puisse pas saturer le journal).
 */
class PlatformAuthController extends PlatformController
{
    public function showLogin(): void
    {
        if (PlatformAuth::isLogged()) {
            $this->redirect(BASE_URL . '/platform/dashboard');
        }

        $this->render('platform/login', [
            'title'  => 'Connexion — Portail Plateforme',
            'errors' => Session::getFlash('errors', []),
        ], 'platform-auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();

        $email = mb_strtolower(trim($this->request->sanitize('email')));
        $password = (string)$this->request->post('password', '');

        // Anti-force-brute (compte opérateur plateforme).
        $throttle = new LoginThrottle(ROOT_PATH . '/storage/cache/throttle');
        $ip       = $this->request->ip();
        $cles     = ['pec:' . $email . '|' . $ip => 5, 'pip:' . $ip => 20, 'pe:' . $email => 15];
        $attente  = 0;
        foreach ($cles as $cle => $limite) {
            $attente = max($attente, $throttle->retryAfter($cle, $limite));
        }
        if ($attente > 0) {
            \Core\Logger::security('PLATFORM_LOGIN_THROTTLED', "Connexion plateforme bloquée pour : {$email} depuis {$ip}");
            $this->auditerConnexion($throttle, $ip, 'operator.login.throttled', 'denied', $email);
            Session::flash('errors', ['Trop de tentatives. Réessayez dans ' . (int)ceil($attente / 60) . ' minute(s).']);
            $this->redirect(BASE_URL . '/platform/login');
        }

        $operator = PlatformOperatorService::make()->authentifier($email, $password);

        if ($operator === null) {
            foreach (array_keys($cles) as $cle) {
                $throttle->recordFailure($cle);
            }
            \Core\Logger::security('PLATFORM_LOGIN_FAILED', "Tentative échouée pour : {$email}");
            $this->auditerConnexion($throttle, $ip, 'operator.login.failed', 'failure', $email);
            Session::flash('errors', ['Identifiants invalides ou accès non autorisé.']);
            $this->redirect(BASE_URL . '/platform/login');
        }

        $throttle->clear('pec:' . $email . '|' . $ip);

        PlatformAuth::login($operator);

        \Core\Logger::security('PLATFORM_LOGIN_SUCCESS', "operator_id={$operator['id']} niveau={$operator['niveau']}");
        $this->audit('operator.login.success', ['entity_type' => 'platform_operator', 'entity_id' => $operator['id']]);

        $this->redirect(BASE_URL . ($operator['must_change'] ? '/platform/compte/mot-de-passe' : '/platform/dashboard'));
    }

    public function logout(): void
    {
        $op = PlatformAuth::current();
        if ($op !== null) {
            $this->audit('operator.logout', ['entity_type' => 'platform_operator', 'entity_id' => (int)$op['id']]);
            \Core\Logger::security('PLATFORM_LOGOUT', "operator_id={$op['id']}");
        }
        PlatformAuth::logout();
        $this->redirect(BASE_URL . '/platform/login');
    }

    /** Écrit une tentative de connexion non réussie dans le journal global, dans la limite de 30 lignes par IP et par fenêtre. */
    private function auditerConnexion(LoginThrottle $throttle, string $ip, string $action, string $resultat, string $email): void
    {
        if ($throttle->retryAfter('pal:' . $ip, 30) > 0) {
            return;
        }
        $throttle->recordFailure('pal:' . $ip);
        PlatformAuditService::make()->record($action, ['resultat' => $resultat, 'actor_email' => $email]);
    }
}
