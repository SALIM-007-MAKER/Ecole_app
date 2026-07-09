<?php
declare(strict_types=1);

namespace App\Modules\Portals\Direction\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\EventDispatcher;

class DirectionPortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'direction'; }

    /** GET /v2/portals/direction */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('direction', $etab, $userId, $perms);
        EventDispatcher::dispatch(new DashboardViewed('direction', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::Direction/dashboard', [
            'dashboard' => $dashboard,
            'pageTitle' => 'Tableau de bord — Direction',
        ]);
    }

    /** GET /v2/portals/direction/scolarite */
    public function scolarite(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('eleves.view');
        $this->portalRender('Portals::Direction/scolarite', ['pageTitle' => 'Scolarité']);
    }

    /** GET /v2/portals/direction/academique */
    public function academique(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('academique.bulletin.view');
        $this->portalRender('Portals::Direction/academique', ['pageTitle' => 'Résultats académiques']);
    }

    /** GET /v2/portals/direction/finance */
    public function finance(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.dashboard.view');
        $this->portalRender('Portals::Direction/finance', ['pageTitle' => 'Finance']);
    }

    /** GET /v2/portals/direction/vie-scolaire */
    public function vieScolaire(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('attendance.view');
        $this->portalRender('Portals::Direction/vie_scolaire', ['pageTitle' => 'Vie scolaire']);
    }

    /** GET /v2/portals/direction/rh */
    public function rh(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('employee.view');
        $this->portalRender('Portals::Direction/rh', ['pageTitle' => 'Ressources humaines']);
    }

    /** GET /v2/portals/direction/rapports */
    public function rapports(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('rapports.dashboard.direction');
        $this->portalRender('Portals::Direction/rapports', ['pageTitle' => 'Rapports & BI']);
    }
}
