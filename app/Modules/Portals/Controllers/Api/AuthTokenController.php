<?php
declare(strict_types=1);

namespace App\Modules\Portals\Controllers\Api;

use App\Modules\Portals\Repositories\ApiTokenRepository;
use Core\Controller;
use Core\Database;

/**
 * Émet et révoque des tokens Bearer pour l'API Portal (clients mobiles / PWA).
 * Le login se fait d'abord par la session standard, puis on échange contre un token.
 */
class AuthTokenController extends Controller
{
    private ApiTokenRepository $tokenRepo;

    public function __construct()
    {
        parent::__construct();
        $this->tokenRepo = new ApiTokenRepository();
    }

    /**
     * POST /api/v2/portals/auth/token
     * Body: { "portal": "eleve", "ttl": 3600 }
     * Requiert une session active.
     */
    public function issue(): void
    {
        $this->requireAuth();

        $portal = (string)($this->request->get('portal') ?? '');
        $ttl    = min(86400, max(300, (int)($this->request->get('ttl') ?? 3600)));

        $validPortals = ['admin', 'direction', 'enseignant', 'eleve', 'parent', 'comptabilite', 'rh'];
        if (!in_array($portal, $validPortals, true)) {
            $this->json(['success' => false, 'error' => 'Portail invalide.'], 422);
            return;
        }

        $user   = $this->currentUser();
        $userId = (int)($user['id'] ?? 0);
        $etab   = (int)($user['etablissement_id'] ?? 0);

        if ($etab === 0) {
            $this->json(['success' => false, 'error' => 'Établissement non déterminable.'], 422);
            return;
        }

        // Révoquer l'ancien token pour ce portail
        $this->tokenRepo->revokeAllForUser($userId, $portal);

        $token     = $this->tokenRepo->create($userId, $portal, $etab, $ttl);
        $expiresAt = date('c', time() + $ttl);

        $this->json([
            'success'    => true,
            'token'      => $token,
            'portal'     => $portal,
            'expires_at' => $expiresAt,
            'ttl'        => $ttl,
        ]);
    }

    /**
     * POST /api/v2/portals/auth/revoke
     * Header: Authorization: Bearer {token}
     */
    public function revoke(): void
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $this->tokenRepo->revoke($token);
        }
        $this->json(['success' => true]);
    }

    /**
     * POST /api/v2/portals/auth/revoke-all
     * Révoque tous les tokens de l'utilisateur (déconnexion complète).
     */
    public function revokeAll(): void
    {
        $this->requireAuth();
        $userId = (int)($this->currentUser()['id'] ?? 0);
        $this->tokenRepo->revokeAllForUser($userId);
        $this->json(['success' => true, 'message' => 'Tous les tokens révoqués.']);
    }
}
