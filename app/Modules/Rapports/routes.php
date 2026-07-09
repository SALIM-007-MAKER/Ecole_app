<?php
declare(strict_types=1);

use Core\Router;
use App\Modules\Rapports\Controllers\DashboardController;
use App\Modules\Rapports\Controllers\KpiController;
use App\Modules\Rapports\Controllers\ExportController;
use App\Modules\Rapports\Controllers\PlanificationController;
use App\Modules\Rapports\Controllers\ApiAnalyticsController;

// ─────────────────────────────────────────────────────────────────
// DASHBOARDS (9 contextes)
// ─────────────────────────────────────────────────────────────────

$router->get('/v2/rapports', DashboardController::class . '@direction');
$router->get('/v2/rapports/dashboard/direction', DashboardController::class . '@direction');
$router->get('/v2/rapports/dashboard/administration', DashboardController::class . '@administration');
$router->get('/v2/rapports/dashboard/scolarite', DashboardController::class . '@scolarite');
$router->get('/v2/rapports/dashboard/academique', DashboardController::class . '@academique');
$router->get('/v2/rapports/dashboard/finance', DashboardController::class . '@finance');
$router->get('/v2/rapports/dashboard/rh', DashboardController::class . '@rh');
$router->get('/v2/rapports/dashboard/vie-scolaire', DashboardController::class . '@vieScolaire');
$router->get('/v2/rapports/dashboard/bibliotheque', DashboardController::class . '@bibliotheque');
$router->get('/v2/rapports/dashboard/inventaire', DashboardController::class . '@inventaire');

// ─────────────────────────────────────────────────────────────────
// KPIs & TENDANCES
// ─────────────────────────────────────────────────────────────────

$router->get('/v2/rapports/kpis', KpiController::class . '@index');
$router->get('/v2/rapports/kpis/tendance', KpiController::class . '@tendanceView');
$router->get('/v2/rapports/kpis/alertes', KpiController::class . '@alertes');
$router->post('/v2/rapports/kpis/snapshot', KpiController::class . '@snapshot');
$router->get('/v2/rapports/kpis/historique', KpiController::class . '@historiqueJson');

// ─────────────────────────────────────────────────────────────────
// EXPORTS
// ─────────────────────────────────────────────────────────────────

$router->get('/v2/rapports/exports', ExportController::class . '@index');
$router->get('/v2/rapports/exports/form', ExportController::class . '@form');
$router->get('/v2/rapports/exports/apercu', ExportController::class . '@apercu');
$router->post('/v2/rapports/exports/apercu', ExportController::class . '@apercu');
$router->get('/v2/rapports/exports/csv', ExportController::class . '@csv');
$router->get('/v2/rapports/exports/excel', ExportController::class . '@excel');
$router->get('/v2/rapports/exports/pdf', ExportController::class . '@pdf');

// ─────────────────────────────────────────────────────────────────
// PLANIFICATIONS
// ─────────────────────────────────────────────────────────────────

$router->get('/v2/rapports/planifications', PlanificationController::class . '@index');
$router->get('/v2/rapports/planifications/create', PlanificationController::class . '@create');
$router->post('/v2/rapports/planifications', PlanificationController::class . '@store');
$router->get('/v2/rapports/planifications/{id}', PlanificationController::class . '@show');
$router->get('/v2/rapports/planifications/{id}/edit', PlanificationController::class . '@edit');
$router->post('/v2/rapports/planifications/{id}/modifier', PlanificationController::class . '@update');
$router->post('/v2/rapports/planifications/{id}/supprimer', PlanificationController::class . '@destroy');
$router->post('/v2/rapports/planifications/{id}/executer', PlanificationController::class . '@executer');

// ─────────────────────────────────────────────────────────────────
// API ANALYTICS (JSON)
// ─────────────────────────────────────────────────────────────────

$router->get('/v2/rapports/api/kpis', ApiAnalyticsController::class . '@kpis');
$router->get('/v2/rapports/api/domaine', ApiAnalyticsController::class . '@domaine');
$router->get('/v2/rapports/api/tendance', ApiAnalyticsController::class . '@tendanceJson');
$router->get('/v2/rapports/api/alertes', ApiAnalyticsController::class . '@alertesJson');
$router->get('/v2/rapports/api/prevision', ApiAnalyticsController::class . '@prevision');
