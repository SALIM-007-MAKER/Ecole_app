<?php

namespace App\Controllers;

use App\Shared\Http\LoginThrottle;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformTwoFactorService;
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

        // Valeur BRUTE : sanitize() échapperait le HTML (o'brien@x.com deviendrait o&#039;brien@x.com) et fausserait recherche, limitation et audit.
        $email = mb_substr(mb_strtolower(trim((string)$this->request->post('email', ''))), 0, 191);
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

        // Double authentification : le mot de passe seul ne suffit plus pour un opérateur qui l'a activée.
        $pdo = \Core\Database::getInstance()->getConnection();
        if (PlatformTwoFactorService::disponible($pdo)) {
            $tf = new PlatformTwoFactorService($pdo);
            if ($tf->estActive((int)$operator['id'])) {
                PlatformAuth::beginMfa($operator);
                $this->audit('operator.login.2fa_required', ['entity_type' => 'platform_operator', 'entity_id' => (int)$operator['id'], 'actor_email' => $email]);
                $this->redirect(BASE_URL . '/platform/login/2fa');
            }
            if (PlatformTwoFactorService::obligatoire()) {
                $operator['must_2fa'] = true;
            }
        }

        PlatformAuth::login($operator);

        \Core\Logger::security('PLATFORM_LOGIN_SUCCESS', "operator_id={$operator['id']} niveau={$operator['niveau']}");
        $this->audit('operator.login.success', ['entity_type' => 'platform_operator', 'entity_id' => $operator['id']]);
        \Core\Platform\PlatformSecurityNotifier::notifier((int)$operator['id'], \Core\Platform\PlatformSecurityNotifier::CONNEXION);

        $this->redirect(BASE_URL . ($operator['must_change'] ? '/platform/compte/mot-de-passe' : (!empty($operator['must_2fa']) ? '/platform/compte/2fa' : '/platform/dashboard')));
    }

    /** Second facteur après le mot de passe. Sans mot de passe validé (état en session absent ou expiré) : retour à la connexion. */
    public function showTwoFactor(): void
    {
        if (PlatformAuth::isLogged()) {
            $this->redirect(BASE_URL . '/platform/dashboard');
        }
        if (PlatformAuth::pendingMfa() === null) {
            Session::flash('errors', ['Veuillez d\'abord saisir votre mot de passe.']);
            $this->redirect(BASE_URL . '/platform/login');
        }
        $this->render('platform/login-2fa', ['title' => 'Vérification en deux étapes', 'errors' => Session::getFlash('errors', [])], 'platform-auth');
    }

    public function verifyTwoFactor(): void
    {
        $this->verifyCsrf();
        $operator = PlatformAuth::pendingMfa();
        if ($operator === null) {
            Session::flash('errors', ['La vérification a expiré. Veuillez vous reconnecter.']);
            $this->redirect(BASE_URL . '/platform/login');
        }
        $id = (int)$operator['id'];
        $ip = $this->request->ip();
        $throttle = $this->throttle();
        $cles = ['mfc:' . $id . '|' . $ip => 5, 'mfi:' . $ip => 20, 'mfa:' . $id => 15];
        $attente = 0;
        foreach ($cles as $cle => $limite) {
            $attente = max($attente, $throttle->retryAfter($cle, $limite));
        }
        if ($attente > 0) {
            \Core\Logger::security('PLATFORM_2FA_THROTTLED', "operator_id={$id} depuis {$ip}");
            $this->auditerConnexion($throttle, $ip, 'operator.2fa.throttled', 'denied', (string)$operator['email']);
            Session::flash('errors', ['Trop de tentatives. Réessayez dans ' . (int)ceil($attente / 60) . ' minute(s).']);
            $this->redirect(BASE_URL . '/platform/login/2fa');
        }

        $methode = PlatformTwoFactorService::make()->verifier($id, (string)$this->request->post('code', ''));
        if ($methode === null) {
            foreach (array_keys($cles) as $cle) {
                $throttle->recordFailure($cle);
            }
            \Core\Logger::security('PLATFORM_2FA_FAILED', "operator_id={$id}");
            $this->auditerConnexion($throttle, $ip, 'operator.2fa.failed', 'failure', (string)$operator['email']);
            $restants = PlatformAuth::mfaFailure();
            if ($restants === 0) {
                Session::flash('errors', ['Trop d\'essais : veuillez ressaisir votre mot de passe.']);
                $this->redirect(BASE_URL . '/platform/login');
            }
            Session::flash('errors', ['Code incorrect.']);
            $this->redirect(BASE_URL . '/platform/login/2fa');
        }

        $throttle->clear('mfc:' . $id . '|' . $ip);
        PlatformAuth::completeMfa($methode);
        \Core\Logger::security('PLATFORM_LOGIN_SUCCESS', "operator_id={$id} niveau={$operator['niveau']} 2fa={$methode}");
        $this->audit('operator.login.success', ['entity_type' => 'platform_operator', 'entity_id' => $id, 'apres' => ['second_facteur' => $methode]]);
        \Core\Platform\PlatformSecurityNotifier::notifier($id, \Core\Platform\PlatformSecurityNotifier::CONNEXION, ['methode' => $methode]);
        if ($methode === 'recovery') {
            $this->audit('operator.2fa.recovery_used', ['entity_type' => 'platform_operator', 'entity_id' => $id]);
            \Core\Platform\PlatformSecurityNotifier::notifier($id, \Core\Platform\PlatformSecurityNotifier::CODE_SECOURS_UTILISE);
            Session::flash('success', 'Un code de secours a été utilisé. Il reste ' . (new PlatformTwoFactorService(\Core\Database::getInstance()->getConnection()))->statut($id)['codes_restants'] . ' code(s) : pensez à en régénérer.');
        }
        $this->redirect(BASE_URL . (!empty($operator['must_change']) ? '/platform/compte/mot-de-passe' : '/platform/dashboard'));
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
