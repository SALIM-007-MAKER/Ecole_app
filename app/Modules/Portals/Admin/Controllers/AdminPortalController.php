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
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT al.*, u.nom, u.prenom FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 ORDER BY al.created_at DESC LIMIT :lim OFFSET :off'
            );
            $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
            $stmt->execute();
            $logs  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
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
