<?php

namespace App\Controllers;

use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Session;
use App\Models\UserModel;

/**
 * Connexion/déconnexion du portail Super-Admin SaaS (Phase 14.10).
 *
 * Flux totalement indépendant de AuthController::login() (établissements) :
 * vérifie les identifiants contre `users` (même hachage — un opérateur EST
 * un compte utilisateur réel, voir platform_operators.user_id) MAIS exige
 * en plus une ligne `platform_operators` active — sans quoi l'accès est
 * refusé, quel que soit le mot de passe. Ne positionne JAMAIS
 * Session::setUser() (état établissement) — uniquement PlatformAuth::login().
 */
class PlatformAuthController extends PlatformController
{
    private UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
    }

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

        $email = trim($this->request->sanitize('email'));
        $password = $this->request->post('password', '');

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user->password) || (int)$user->actif === 0) {
            \Core\Logger::security('PLATFORM_LOGIN_FAILED', "Tentative échouée pour : {$email}");
            Session::flash('errors', ['Identifiants invalides ou accès non autorisé.']);
            $this->redirect(BASE_URL . '/platform/login');
        }

        $operator = $this->findActiveOperator((int)$user->id);
        if ($operator === null) {
            \Core\Logger::security('PLATFORM_LOGIN_UNAUTHORIZED', "user_id={$user->id} ({$email}) — pas d'accès opérateur plateforme");
            Session::flash('errors', ['Identifiants invalides ou accès non autorisé.']);
            $this->redirect(BASE_URL . '/platform/login');
        }

        PlatformAuth::login([
            'id'       => $operator['id'],
            'user_id'  => (int)$user->id,
            'email'    => $user->email,
            'nom'      => $user->nom,
            'niveau'   => $operator['niveau'],
        ]);

        \Core\Logger::security('PLATFORM_LOGIN_SUCCESS', "user_id={$user->id} niveau={$operator['niveau']}");
        $this->redirect(BASE_URL . '/platform/dashboard');
    }

    public function logout(): void
    {
        $op = PlatformAuth::current();
        PlatformAuth::logout();
        if ($op !== null) {
            \Core\Logger::security('PLATFORM_LOGOUT', "user_id={$op['user_id']}");
        }
        $this->redirect(BASE_URL . '/platform/login');
    }

    private function findActiveOperator(int $userId): ?array
    {
        $pdo = \Core\Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM platform_operators WHERE user_id = ? AND actif = 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
