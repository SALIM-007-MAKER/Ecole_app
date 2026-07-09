<?php
declare(strict_types=1);

namespace App\Modules\Api\Auth;

use App\Modules\Api\Exceptions\AuthException;
use App\Modules\Api\Repositories\RefreshTokenRepository;

class RefreshTokenService
{
    private int $refreshTtl;

    public function __construct(private readonly RefreshTokenRepository $repo)
    {
        $cfg = require ROOT_PATH . '/config/api.php';
        $this->refreshTtl = $cfg['jwt']['refresh_ttl'];
    }

    public function issue(int $userId, int $etablissementId, string $userAgent = '', string $ip = ''): string
    {
        $token    = bin2hex(random_bytes(40));
        $hash     = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->refreshTtl);

        $this->repo->create($userId, $etablissementId, $hash, $expiresAt, $userAgent, $ip);

        return $token;
    }

    /**
     * @throws AuthException si le token est invalide ou révoqué
     * @return array ['user_id', 'etablissement_id', 'token_hash']
     */
    public function consume(string $token): array
    {
        $hash = hash('sha256', $token);
        $row  = $this->repo->findValid($hash);

        if ($row === null) {
            throw new AuthException('Refresh token invalide ou expiré.', 'auth_invalid');
        }

        // Révocation après utilisation (rotation)
        $this->repo->revoke($hash);

        return $row;
    }

    public function revokeAll(int $userId): void
    {
        $this->repo->revokeAllForUser($userId);
    }
}
