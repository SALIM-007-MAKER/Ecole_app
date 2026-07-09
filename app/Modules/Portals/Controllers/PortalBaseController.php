<?php
declare(strict_types=1);

namespace App\Modules\Portals\Controllers;

use Core\Controller;
use App\Modules\Portals\Framework\DashboardEngine;
use App\Modules\Portals\Framework\GlobalSearchEngine;
use App\Modules\Portals\Framework\MenuEngine;
use App\Modules\Portals\Framework\NavigationEngine;
use App\Modules\Portals\Framework\NotificationCenter;
use App\Modules\Portals\Framework\PortalBootstrap;
use App\Modules\Portals\Framework\PortalLayoutManager;
use App\Modules\Portals\Framework\PortalThemeManager;
use App\Modules\Portals\Framework\ShortcutEngine;
use App\Modules\Portals\Framework\UserPreferenceService;
use App\Modules\Portals\Framework\WidgetRegistry;
use App\Modules\Portals\Events\PortalAccessed;
use App\Modules\Portals\Events\PortalError;
use App\Modules\Portals\Repositories\PreferencesRepository;
use App\Modules\Portals\Repositories\WidgetCacheRepository;
use Core\EventDispatcher;

abstract class PortalBaseController extends Controller
{
    protected readonly DashboardEngine      $dashboardEngine;
    protected readonly WidgetRegistry       $widgetRegistry;
    protected readonly MenuEngine           $menuEngine;
    protected readonly NavigationEngine     $navigationEngine;
    protected readonly NotificationCenter   $notificationCenter;
    protected readonly UserPreferenceService $preferenceService;
    protected readonly ShortcutEngine       $shortcutEngine;
    protected readonly GlobalSearchEngine   $searchEngine;
    protected readonly PortalLayoutManager  $layoutManager;
    protected readonly PortalThemeManager   $themeManager;

    public function __construct()
    {
        parent::__construct();

        // Enregistre tous les widgets et handlers (idempotent)
        PortalBootstrap::boot();

        $prefsRepo = new PreferencesRepository();

        $this->widgetRegistry     = new WidgetRegistry();
        $this->preferenceService  = new UserPreferenceService($prefsRepo);
        $this->dashboardEngine    = new DashboardEngine(
            $this->widgetRegistry,
            $this->preferenceService,
            new WidgetCacheRepository()
        );
        $this->menuEngine         = new MenuEngine();
        $this->navigationEngine   = new NavigationEngine();
        $this->notificationCenter = new NotificationCenter();
        $this->shortcutEngine     = new ShortcutEngine($prefsRepo);
        $this->searchEngine       = new GlobalSearchEngine();
        $this->layoutManager      = new PortalLayoutManager();
        $this->themeManager       = new PortalThemeManager();
    }

    /** Identifiant du portail : 'admin' | 'direction' | 'enseignant' | 'eleve' | 'parent' | 'comptabilite' | 'rh' */
    abstract protected function getPortalName(): string;

    /** Retourne l'etablissement_id de l'utilisateur courant — jamais de fallback 1 */
    protected function getEtablissementId(): int
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect(BASE_URL . '/login');
        }
        $etab = (int)($user['etablissement_id'] ?? 0);
        if ($etab === 0) {
            throw new \RuntimeException('Établissement non déterminable pour cet utilisateur.');
        }
        return $etab;
    }

    protected function getUserId(): int
    {
        return (int)($this->currentUser()['id'] ?? 0);
    }

    protected function getUserPerms(): array
    {
        return $this->currentUser()['permissions'] ?? [];
    }

    /** Vérifie l'authentification ET l'accès au portail, trace l'accès */
    protected function requirePortalAccess(): void
    {
        $this->requireAuth();
        $portal = $this->getPortalName();
        $perms  = $this->getUserPerms();

        if (!$this->navigationEngine->canAccessPortal($portal, $perms)) {
            $this->requirePermission('portal.' . $portal . '.access');
        }

        $this->dispatchPortalAccessed();
    }

    protected function dispatchPortalAccessed(): void
    {
        try {
            EventDispatcher::dispatch(new PortalAccessed(
                portal:          $this->getPortalName(),
                userId:          $this->getUserId(),
                etablissementId: $this->getEtablissementId(),
                ip:              $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                page:            $_SERVER['REQUEST_URI'] ?? '',
            ));
        } catch (\Throwable) {}
    }

    protected function dispatchPortalError(string $context, \Throwable $e): void
    {
        try {
            EventDispatcher::dispatch(new PortalError(
                portal:          $this->getPortalName(),
                userId:          $this->getUserId(),
                etablissementId: $this->getEtablissementId(),
                context:         $context,
                message:         $e->getMessage(),
                exception:       $e,
            ));
        } catch (\Throwable) {}
    }

    /**
     * Rendu avec layout portail.
     * La vue $view est au format 'Portals::Section/nom_vue'.
     */
    protected function portalRender(string $view, array $data = []): void
    {
        $portal  = $this->getPortalName();
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $perms   = $this->getUserPerms();
        $uri     = $_SERVER['REQUEST_URI'] ?? '';

        // Injecter badges dynamiques dans le menu
        $unreadCount = $this->notificationCenter->getUnreadCount($userId, $etab);
        $this->menuEngine->addBadge('notifications', $unreadCount);

        $menu      = $this->menuEngine->build($portal, $uri, $perms);
        $prefs     = $this->preferenceService->get($userId, $portal, $etab);
        $theme     = $this->themeManager->getThemeClasses($prefs->theme);
        $cssVars   = $this->themeManager->getCssVars($portal, $prefs->theme);
        $color     = $this->themeManager->getPortalColor($portal);

        $layoutData = array_merge($data, [
            'portal'          => $portal,
            'portalTitle'     => $this->getPortalTitle($portal),
            'menu'            => $menu,
            'unreadCount'     => $unreadCount,
            'theme'           => $theme,
            'cssVars'         => $cssVars,
            'portalColor'     => $color,
            'currentUser'     => $this->currentUser(),
            'prefs'           => $prefs,
            'baseUrl'         => BASE_URL,
        ]);

        $this->render($view, $layoutData, 'portal-' . $portal);
    }

    /** Rendu JSON portail (ajoute le contexte portal + etab) */
    protected function portalJson(array $data, int $status = 200): void
    {
        $this->json(array_merge($data, [
            '_portal' => $this->getPortalName(),
            '_etab'   => $this->getEtablissementId(),
        ]), $status);
    }

    /** Détecte si le client préfère JSON (API First) */
    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json')
            || ($this->request->get('format') === 'json')
            || isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function getPortalTitle(string $portal): string
    {
        return match($portal) {
            'admin'        => 'Administration',
            'direction'    => 'Direction',
            'enseignant'   => 'Espace Enseignant',
            'eleve'        => 'Espace Élève',
            'parent'       => 'Espace Parent',
            'comptabilite' => 'Comptabilité',
            'rh'           => 'Ressources Humaines',
            default        => ucfirst($portal),
        };
    }
}
