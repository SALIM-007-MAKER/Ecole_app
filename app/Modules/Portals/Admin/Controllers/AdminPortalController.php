<?php
declare(strict_types=1);

namespace App\Modules\Portals\Admin\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\Database;
use Core\EventDispatcher;

class AdminPortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'admin'; }

    /** GET /v2/portals/admin */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('admin', $etab, $userId, $perms);

        EventDispatcher::dispatch(new DashboardViewed('admin', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::Admin/dashboard', [
            'dashboard' => $dashboard,
            'pageTitle' => 'Tableau de bord — Administration',
        ]);
    }

    /** GET /v2/portals/admin/audit */
    public function audit(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('users.view');
        $etab   = $this->getEtablissementId();
        $page   = max(1, (int)($this->request->get('page') ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        try {
            // Journal DE L'ÉTABLISSEMENT courant uniquement : l'établissement est explicite et obligatoire (jamais de lecture globale).
            $res   = (new \App\Services\AuditService())->rechercher($etab, [], $page, $perPage);
            $logs  = array_map(static fn(array $l) => $l + ['nom' => (string)($l['acteur_libelle'] ?? ''), 'prenom' => ''], $res['rows']);
            $total = $res['total'];
        } catch (\Throwable) {
            $logs  = [];
            $total = 0;
        }

        $this->portalRender('Portals::Admin/audit', [
            'logs'    => $logs,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pageTitle' => 'Journal d\'audit',
        ]);
    }

    /** GET /v2/portals/admin/modules */
    public function modules(): void
    {
        $this->requirePortalAccess();
        $modules = require ROOT_PATH . '/config/modules.php';

        if ($this->wantsJson()) {
            $this->portalJson(['modules' => $modules]);
            return;
        }
        $this->portalRender('Portals::Admin/modules', [
            'modules'  => $modules,
            'pageTitle' => 'État des modules',
        ]);
    }

    /** GET /v2/portals/admin/parametres */
    public function parametres(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('users.view');
        $this->portalRender('Portals::Admin/parametres', [
            'pageTitle' => 'Paramètres système',
        ]);
    }
}
