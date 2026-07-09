<?php
declare(strict_types=1);

namespace App\Modules\Portals\Controllers;

use App\Modules\Portals\Framework\NavigationEngine;
use App\Modules\Portals\Framework\PortalBootstrap;
use App\Modules\Portals\Framework\PortalThemeManager;
use App\Modules\Portals\Framework\ShortcutEngine;
use App\Modules\Portals\Framework\UserPreferenceService;
use App\Modules\Portals\DTO\ShortcutDTO;
use App\Modules\Portals\Events\ShortcutCreated;
use App\Modules\Portals\Events\ShortcutDeleted;
use App\Modules\Portals\Repositories\PreferencesRepository;
use Core\Controller;
use Core\EventDispatcher;

class PreferencesController extends Controller
{
    private UserPreferenceService $prefService;
    private ShortcutEngine        $shortcutEngine;
    private NavigationEngine      $navigation;
    private PortalThemeManager    $themeManager;

    public function __construct()
    {
        parent::__construct();
        PortalBootstrap::boot();

        $repo                 = new PreferencesRepository();
        $this->prefService    = new UserPreferenceService($repo);
        $this->shortcutEngine = new ShortcutEngine($repo);
        $this->navigation     = new NavigationEngine();
        $this->themeManager   = new PortalThemeManager();
    }

    /** GET /v2/portals/{portal}/preferences */
    public function show(string $portal): void
    {
        $this->requireAuth();
        $this->validatePortal($portal);

        $userId = (int)($this->currentUser()['id'] ?? 0);
        $etab   = $this->getEtabId();
        $prefs  = $this->prefService->get($userId, $portal, $etab);

        $this->json([
            'success'   => true,
            'portal'    => $portal,
            'prefs'     => $prefs->toArray(),
            'themes'    => $this->themeManager->getAvailableThemes(),
        ]);
    }

    /** POST /v2/portals/{portal}/preferences */
    public function save(string $portal): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->validatePortal($portal);

        $userId = (int)($this->currentUser()['id'] ?? 0);
        $etab   = $this->getEtabId();
        $data   = $this->request->only(['theme', 'default_page', 'lang', 'notif_prefs']);

        $this->prefService->save($userId, $portal, $etab, $data);
        $this->json(['success' => true, 'message' => 'Préférences enregistrées.']);
    }

    /** GET /v2/portals/{portal}/preferences/widget-layout */
    public function getWidgetLayout(string $portal): void
    {
        $this->requireAuth();
        $this->validatePortal($portal);

        $userId = (int)($this->currentUser()['id'] ?? 0);
        $etab   = $this->getEtabId();

        $this->json([
            'success' => true,
            'layout'  => $this->prefService->getWidgetLayout($userId, $portal, $etab),
        ]);
    }

    /** POST /v2/portals/{portal}/preferences/widget-layout */
    public function saveWidgetLayout(string $portal): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->validatePortal($portal);

        $userId = (int)($this->currentUser()['id'] ?? 0);
        $etab   = $this->getEtabId();
        $layout = $this->request->get('layout', []);

        if (!is_array($layout)) {
            $this->json(['success' => false, 'error' => 'Format invalide.'], 422);
            return;
        }

        $this->prefService->saveWidgetLayout($userId, $portal, $etab, $layout);
        $this->json(['success' => true, 'message' => 'Layout enregistré.']);
    }

    /** POST /v2/portals/{portal}/preferences/widget/{id}/toggle */
    public function toggleWidget(string $portal, string $widgetId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId  = (int)($this->currentUser()['id'] ?? 0);
        $etab    = $this->getEtabId();
        $visible = $this->prefService->toggleWidget($userId, $portal, $etab, $widgetId);

        $this->json(['success' => true, 'visible' => $visible]);
    }

    /** GET /v2/portals/{portal}/raccourcis */
    public function getShortcuts(string $portal): void
    {
        $this->requireAuth();
        $this->validatePortal($portal);

        $userId = (int)($this->currentUser()['id'] ?? 0);
        $etab   = $this->getEtabId();

        $shortcuts = $this->shortcutEngine->getUserShortcuts($userId, $portal, $etab);
        $this->json([
            'success'   => true,
            'shortcuts' => array_map(fn($s) => $s->toArray(), $shortcuts),
            'defaults'  => array_map(fn($s) => $s->toArray(), $this->shortcutEngine->getDefaults($portal)),
        ]);
    }

    /** POST /v2/portals/{portal}/raccourcis */
    public function saveShortcuts(string $portal): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->validatePortal($portal);

        $userId    = (int)($this->currentUser()['id'] ?? 0);
        $etab      = $this->getEtabId();
        $rawList   = $this->request->get('shortcuts', []);

        if (!is_array($rawList)) {
            $this->json(['success' => false, 'error' => 'Format invalide.'], 422);
            return;
        }

        $shortcuts = array_map(fn($item) => ShortcutDTO::fromArray($item), $rawList);
        $this->shortcutEngine->saveShortcuts($userId, $portal, $etab, $shortcuts);

        EventDispatcher::dispatch(new ShortcutCreated(
            portal:          $portal,
            userId:          $userId,
            etablissementId: $etab,
            label:           count($shortcuts) . ' raccourcis',
            url:             '',
        ));

        $this->json(['success' => true, 'message' => 'Raccourcis enregistrés.']);
    }

    /** POST /v2/portals/{portal}/preferences/reset */
    public function reset(string $portal): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->validatePortal($portal);

        $userId = (int)($this->currentUser()['id'] ?? 0);
        $etab   = $this->getEtabId();

        $this->prefService->reset($userId, $portal, $etab);
        $this->json(['success' => true, 'message' => 'Préférences réinitialisées.']);
    }

    private function validatePortal(string $portal): void
    {
        if (!$this->navigation->isKnownPortal($portal)) {
            $this->json(['success' => false, 'error' => 'Portail inconnu.'], 404);
            exit;
        }
    }

    private function getEtabId(): int
    {
        $user = $this->currentUser();
        $etab = (int)($user['etablissement_id'] ?? 0);
        if ($etab === 0) {
            throw new \RuntimeException('Établissement non déterminable.');
        }
        return $etab;
    }
}
