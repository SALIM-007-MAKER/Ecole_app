<?php
declare(strict_types=1);

namespace App\Modules\Portals\Controllers\Api;

use App\Modules\Portals\Framework\DashboardEngine;
use App\Modules\Portals\Framework\GlobalSearchEngine;
use App\Modules\Portals\Framework\NavigationEngine;
use App\Modules\Portals\Framework\NotificationCenter;
use App\Modules\Portals\Framework\PortalBootstrap;
use App\Modules\Portals\Framework\UserPreferenceService;
use App\Modules\Portals\Framework\WidgetEngine;
use App\Modules\Portals\Framework\WidgetRegistry;
use App\Modules\Portals\Events\SearchPerformed;
use App\Modules\Portals\Events\WidgetRefreshed;
use App\Modules\Portals\Repositories\ApiTokenRepository;
use App\Modules\Portals\Repositories\PreferencesRepository;
use App\Modules\Portals\Repositories\WidgetCacheRepository;
use Core\Controller;
use Core\EventDispatcher;

/**
 * API JSON Bearer-token pour les clients mobiles et PWA.
 * Toutes les méthodes répondent uniquement en JSON.
 */
class PortalApiController extends Controller
{
    private ApiTokenRepository   $tokenRepo;
    private NavigationEngine     $navigation;
    private DashboardEngine      $dashboardEngine;
    private WidgetEngine         $widgetEngine;
    private GlobalSearchEngine   $searchEngine;
    private NotificationCenter   $notifCenter;
    private UserPreferenceService $prefService;
    private WidgetRegistry       $registry;

    public function __construct()
    {
        parent::__construct();
        PortalBootstrap::boot();

        $prefsRepo             = new PreferencesRepository();
        $cacheRepo             = new WidgetCacheRepository();
        $this->registry        = new WidgetRegistry();
        $this->tokenRepo       = new ApiTokenRepository();
        $this->navigation      = new NavigationEngine();
        $this->prefService     = new UserPreferenceService($prefsRepo);
        $this->dashboardEngine = new DashboardEngine($this->registry, $this->prefService, $cacheRepo);
        $this->widgetEngine    = new WidgetEngine($this->registry, $cacheRepo);
        $this->searchEngine    = new GlobalSearchEngine();
        $this->notifCenter     = new NotificationCenter();
    }

    /** GET /api/v2/portals/{portal}/dashboard */
    public function dashboard(string $portal): void
    {
        $user = $this->resolveApiBearerUser();
        $this->validatePortalAccess($portal, $user);

        $etab      = (int)$user['etablissement_id'];
        $userId    = (int)$user['id'];
        $perms     = $user['permissions'] ?? [];
        $dashboard = $this->dashboardEngine->build($portal, $etab, $userId, $perms);

        $this->json(['success' => true, 'dashboard' => $dashboard->toArray()]);
    }

    /** GET /api/v2/portals/{portal}/widgets/{id} */
    public function widget(string $portal, string $widgetId): void
    {
        $user   = $this->resolveApiBearerUser();
        $etab   = (int)$user['etablissement_id'];
        $userId = (int)$user['id'];

        $widget = $this->widgetEngine->render($widgetId, $etab, $userId, ['portal' => $portal]);
        EventDispatcher::dispatch(new WidgetRefreshed($widgetId, $portal, $userId, $etab));

        $this->json(['success' => true, 'widget' => $widget->toArray()]);
    }

    /** GET /api/v2/portals/{portal}/search?q=... */
    public function search(string $portal): void
    {
        $user  = $this->resolveApiBearerUser();
        $this->validatePortalAccess($portal, $user);

        $query  = (string)($this->request->get('q') ?? '');
        $limit  = min(20, max(5, (int)($this->request->get('limit') ?? 10)));
        $etab   = (int)$user['etablissement_id'];
        $userId = (int)$user['id'];
        $perms  = $user['permissions'] ?? [];

        $results = $this->searchEngine->search($query, $portal, $etab, $userId, $perms, $limit);

        EventDispatcher::dispatch(new SearchPerformed(
            query:           $query,
            portal:          $portal,
            userId:          $userId,
            etablissementId: $etab,
            resultCount:     $results->total,
            timeMs:          $results->timeMs,
        ));

        $this->json(['success' => true, 'results' => $results->toArray()]);
    }

    /** GET /api/v2/portals/{portal}/notifications */
    public function notifications(string $portal): void
    {
        $user   = $this->resolveApiBearerUser();
        $etab   = (int)$user['etablissement_id'];
        $userId = (int)$user['id'];
        $page   = max(1, (int)($this->request->get('page') ?? 1));

        $data = $this->notifCenter->getAll($userId, $etab, $page, 20);
        $this->json(['success' => true, 'notifications' => $data]);
    }

    /** POST /api/v2/portals/{portal}/notifications/{id}/read */
    public function markNotificationRead(string $portal, string $id): void
    {
        $user    = $this->resolveApiBearerUser();
        $userId  = (int)$user['id'];
        $success = $this->notifCenter->markRead((int)$id, $userId);

        $this->json(['success' => $success]);
    }

    /** POST /api/v2/portals/{portal}/notifications/read-all */
    public function markAllRead(string $portal): void
    {
        $user   = $this->resolveApiBearerUser();
        $etab   = (int)$user['etablissement_id'];
        $userId = (int)$user['id'];
        $count  = $this->notifCenter->markAllRead($userId, $etab);

        $this->json(['success' => true, 'marked' => $count]);
    }

    /**
     * Résout l'utilisateur depuis un token Bearer OU depuis la session.
     * Priorité : Bearer token > Session.
     */
    private function resolveApiBearerUser(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $row   = $this->tokenRepo->findValid($token);
            if ($row === null) {
                $this->json(['success' => false, 'error' => 'Token invalide ou expiré.'], 401);
                exit;
            }
            return [
                'id'               => (int)$row['user_id'],
                'etablissement_id' => (int)$row['etablissement_id'],
                'portal'           => $row['portal'],
                'permissions'      => [],
                '_bearer_portal'   => $row['portal'],  // portail pour lequel le token a été émis
            ];
        }

        // Fallback session (navigateur)
        $this->requireAuth();
        $user = $this->currentUser();
        if ($user === null) {
            $this->json(['success' => false, 'error' => 'Non authentifié.'], 401);
            exit;
        }
        return $user;
    }

    private function validatePortalAccess(string $portal, array $user): void
    {
        if (!$this->navigation->isKnownPortal($portal)) {
            $this->json(['success' => false, 'error' => 'Portail inconnu.'], 404);
            exit;
        }
        // Token Bearer : le portail demandé doit correspondre au portail pour lequel le token a été émis.
        if (isset($user['_bearer_portal']) && $user['_bearer_portal'] !== $portal) {
            $this->json(['success' => false, 'error' => 'Token non autorisé pour ce portail.'], 403);
            exit;
        }
        $perms = $user['permissions'] ?? [];
        if (!empty($perms) && !$this->navigation->canAccessPortal($portal, $perms)) {
            $this->json(['success' => false, 'error' => 'Accès refusé à ce portail.'], 403);
            exit;
        }
    }
}
