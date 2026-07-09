<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\Auth;

use App\Modules\Api\Auth\JwtService;
use App\Modules\Api\Auth\RefreshTokenService;
use App\Modules\Api\Controllers\ApiBaseController;
use App\Modules\Api\Exceptions\AuthException;
use App\Modules\Api\Exceptions\ValidationException;
use App\Modules\Api\Repositories\RefreshTokenRepository;
use Core\Database;

/**
 * POST /api/v1/auth/login
 * POST /api/v1/auth/refresh
 * POST /api/v1/auth/logout
 */
class LoginController extends ApiBaseController
{
    private JwtService           $jwt;
    private RefreshTokenService  $refreshService;

    public function __construct()
    {
        parent::__construct();
        $this->jwt            = new JwtService();
        $this->refreshService = new RefreshTokenService(new RefreshTokenRepository());
    }

    /** POST /api/v1/auth/login */
    public function login(): void
    {
        $this->checkRateLimit('auth');

        $body  = $this->body();
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';

        if ($email === '' || $pass === '') {
            throw new ValidationException(['email' => ['Email et mot de passe requis.']]);
        }

        $user = $this->findAndVerifyUser($email, $pass);
        [$access, $refresh, $expiresIn] = $this->issueTokenPair($user);

        $this->apiSuccess([
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'token_type'    => 'Bearer',
            'expires_in'    => $expiresIn,
            'user' => [
                'id'               => $user['id'],
                'nom'              => $user['nom'],
                'prenom'           => $user['prenom'],
                'email'            => $user['email'],
                'role'             => $user['role'],
                'etablissement_id' => $user['etablissement_id'],
            ],
        ]);
    }

    /** POST /api/v1/auth/refresh */
    public function refresh(): void
    {
        $body         = $this->body();
        $refreshToken = $body['refresh_token'] ?? '';

        if ($refreshToken === '') {
            throw new ValidationException(['refresh_token' => ['Refresh token requis.']]);
        }

        $tokenData = $this->refreshService->consume($refreshToken);
        $user      = $this->loadUser((int)$tokenData['user_id']);

        if ($user === null) {
            throw new AuthException('Utilisateur introuvable.', 'auth_invalid');
        }

        [$access, $newRefresh, $expiresIn] = $this->issueTokenPair($user);

        $this->apiSuccess([
            'access_token'  => $access,
            'refresh_token' => $newRefresh,
            'token_type'    => 'Bearer',
            'expires_in'    => $expiresIn,
        ]);
    }

    /** POST /api/v1/auth/logout */
    public function logout(): void
    {
        $ctx = $this->requireApiAuth();
        $this->refreshService->revokeAll($ctx->userId);
        $this->apiSuccess(null, 200, 'Déconnexion réussie. Tous les tokens révoqués.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findAndVerifyUser(string $email, string $password): array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nom, prenom, email, password, role, etablissement_id, statut
             FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'] ?? '')) {
            throw new AuthException('Email ou mot de passe incorrect.', 'auth_invalid');
        }

        if (($user['statut'] ?? '') !== 'actif') {
            throw new AuthException('Compte désactivé.', 'auth_invalid');
        }

        $cfg = require ROOT_PATH . '/config/permissions.php';
        $user['permissions'] = $cfg[$user['role']] ?? [];

        return $user;
    }

    private function loadUser(int $userId): ?array
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nom, prenom, email, role, etablissement_id FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($user) {
            $cfg = require ROOT_PATH . '/config/permissions.php';
            $user['permissions'] = $cfg[$user['role']] ?? [];
        }

        return $user;
    }

    private function issueTokenPair(array $user): array
    {
        $cfg        = require ROOT_PATH . '/config/api.php';
        $expiresIn  = $cfg['jwt']['access_ttl'];

        $access = $this->jwt->encode([
            'sub'   => (string)$user['id'],
            'etab'  => $user['etablissement_id'],
            'role'  => $user['role'],
            'perms' => $user['permissions'],
        ]);

        $refresh = $this->refreshService->issue(
            (int)$user['id'],
            (int)$user['etablissement_id'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $this->getClientIp()
        );

        return [$access, $refresh, $expiresIn];
    }
}
