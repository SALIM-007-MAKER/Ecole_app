<?php

namespace App\Controllers;

use App\Shared\Http\LoginThrottle;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPasskeyService;
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
    /** Échecs tolérés par adresse e-mail, toutes IP confondues (frein à une attaque répartie, pas verrou principal). */
    public const LIMITE_PAR_EMAIL = 60;

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
        $throttle = $this->throttle();
        $ip       = $this->request->ip();
        // Le verrou principal est le couple e-mail+IP : un tiers qui connaît l'adresse d'un opérateur ne peut pas le bloquer depuis une autre IP.
        // Le plafond par e-mail seul (toutes IP) ne sert qu'à freiner une attaque répartie ; il est volontairement haut.
        $cles     = ['pec:' . $email . '|' . $ip => 5, 'pip:' . $ip => 20, 'pe:' . $email => self::LIMITE_PAR_EMAIL];
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
        $pdo = \Core\Database::getInstance()->getConnection();
        $this->render('platform/login-2fa', [
            'title'    => 'Vérification en deux étapes',
            'errors'   => Session::getFlash('errors', []),
            'passkeys' => PlatformPasskeyService::disponible($pdo) && (new PlatformPasskeyService($pdo))->nombre((int)PlatformAuth::pendingMfa()['id']) > 0,
        ], 'platform-auth');
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

        $this->redirect(BASE_URL . $this->secondFacteurReussi($operator, $methode, $throttle, $ip));
    }

    /** Fin commune de toute vérification de second facteur réussie (code, code de secours ou passkey). Renvoie la page où envoyer l'opérateur. */
    private function secondFacteurReussi(array $operator, string $methode, LoginThrottle $throttle, string $ip): string
    {
        $id = (int)$operator['id'];
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
        return !empty($operator['must_change']) ? '/platform/compte/mot-de-passe' : '/platform/dashboard';
    }

    /** Opérateur en attente de second facteur, ou réponse JSON d'expiration (pour les appels de la page de vérification). */
    private function operateurEnAttenteJson(): array
    {
        $operator = PlatformAuth::pendingMfa();
        if ($operator === null) {
            $this->json(['ok' => false, 'message' => 'La vérification a expiré. Veuillez vous reconnecter.', 'redirect' => BASE_URL . '/platform/login'], 401);
        }
        return $operator;
    }

    /** Passkey, étape 1 : options du défi pour navigator.credentials.get(). */
    public function passkeyOptions(): void
    {
        $this->verifyCsrf();
        $operator = $this->operateurEnAttenteJson();
        $pdo = \Core\Database::getInstance()->getConnection();
        $options = PlatformPasskeyService::disponible($pdo) ? (new PlatformPasskeyService($pdo))->optionsConnexion((int)$operator['id']) : null;
        if ($options === null) {
            $this->json(['ok' => false, 'message' => 'Aucune passkey enregistrée pour ce compte.'], 404);
        }
        $this->json(['ok' => true, 'options' => $options]);
    }

    /** Passkey, étape 2 : vérification de la réponse de l'appareil. Mêmes limites d'essais que le code à 6 chiffres. */
    public function passkeyVerify(): void
    {
        $this->verifyCsrf();
        $operator = $this->operateurEnAttenteJson();
        $id = (int)$operator['id'];
        $ip = $this->request->ip();
        $throttle = $this->throttle();
        $cles = ['mfc:' . $id . '|' . $ip => 5, 'mfi:' . $ip => 20, 'mfa:' . $id => 15];
        $attente = 0;
        foreach ($cles as $cle => $limite) {
            $attente = max($attente, $throttle->retryAfter($cle, $limite));
        }
        if ($attente > 0) {
            \Core\Logger::security('PLATFORM_2FA_THROTTLED', "operator_id={$id} depuis {$ip} (passkey)");
            $this->auditerConnexion($throttle, $ip, 'operator.2fa.throttled', 'denied', (string)$operator['email']);
            $this->json(['ok' => false, 'message' => 'Trop de tentatives. Réessayez dans ' . (int)ceil($attente / 60) . ' minute(s).'], 429);
        }

        $pdo = \Core\Database::getInstance()->getConnection();
        $ok = PlatformPasskeyService::disponible($pdo) && (new PlatformPasskeyService($pdo))->verifierConnexion(
            $id,
            (string)$this->request->post('credential_id', ''),
            (string)$this->request->post('client_data', ''),
            (string)$this->request->post('authenticator_data', ''),
            (string)$this->request->post('signature', '')
        );
        if (!$ok) {
            foreach (array_keys($cles) as $cle) {
                $throttle->recordFailure($cle);
            }
            \Core\Logger::security('PLATFORM_2FA_FAILED', "operator_id={$id} (passkey)");
            $this->auditerConnexion($throttle, $ip, 'operator.2fa.failed', 'failure', (string)$operator['email']);
            if (PlatformAuth::mfaFailure() === 0) {
                Session::flash('errors', ['Trop d\'essais : veuillez ressaisir votre mot de passe.']);
                $this->json(['ok' => false, 'message' => 'Trop d\'essais : veuillez ressaisir votre mot de passe.', 'redirect' => BASE_URL . '/platform/login'], 401);
            }
            $this->json(['ok' => false, 'message' => 'Passkey non reconnue. Réessayez ou utilisez un code.'], 401);
        }
        $this->json(['ok' => true, 'redirect' => BASE_URL . $this->secondFacteurReussi($operator, 'passkey', $throttle, $ip)]);
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
