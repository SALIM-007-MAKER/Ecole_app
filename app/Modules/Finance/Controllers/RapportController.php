<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\DTO\ReportFiltersDTO;
use App\Modules\Finance\Events\FinancialReportGenerated;
use App\Modules\Finance\Policies\FinancialReportPolicy;
use App\Modules\Finance\Services\FinancialReportService;
use Core\EventDispatcher;
use Core\Session;
use Core\View;

class RapportController
{
    private FinancialReportService $service;
    private FinancialReportPolicy  $policy;
    private View                   $view;

    public function __construct()
    {
        $this->service = new FinancialReportService();
        $this->policy  = new FinancialReportPolicy();
        $this->view    = new View();
    }

    // GET /v2/finance/rapports
    public function index(): void
    {
        $user = $this->auth();
        if (!$this->policy->canView($user)) {
            $this->deny();
        }
        $this->view->render('Finance::rapports/index', ['user' => $user]);
    }

    // GET /v2/finance/rapports/dashboard
    public function dashboard(): void
    {
        $user    = $this->auth();
        if (!$this->policy->canViewDashboard($user)) {
            $this->deny();
        }
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => 'dashboard']));
        $data    = $this->service->getDashboard($filters);
        $this->dispatchGenerated('dashboard', $filters, $data, $user['id']);
        $this->view->render('Finance::rapports/dashboard', array_merge($data, ['filters' => $filters, 'user' => $user]));
    }

    // GET /v2/finance/rapports/paiements
    public function paiements(): void
    {
        $user    = $this->auth();
        if (!$this->policy->canView($user)) {
            $this->deny();
        }
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => 'paiements']));
        $data    = $this->service->getPaiementsReport($filters);
        $this->dispatchGenerated('paiements', $filters, $data, $user['id']);
        $this->view->render('Finance::rapports/paiements', array_merge($data, ['user' => $user]));
    }

    // GET /v2/finance/rapports/factures
    public function factures(): void
    {
        $user    = $this->auth();
        if (!$this->policy->canView($user)) {
            $this->deny();
        }
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => 'factures']));
        $data    = $this->service->getFacturesReport($filters);
        $this->dispatchGenerated('factures', $filters, $data, $user['id']);
        $this->view->render('Finance::rapports/factures', array_merge($data, ['user' => $user]));
    }

    // GET /v2/finance/rapports/impayes
    public function impayes(): void
    {
        $user    = $this->auth();
        if (!$this->policy->canView($user)) {
            $this->deny();
        }
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => 'impayes']));
        $data    = $this->service->getImpayesReport($filters);
        $this->dispatchGenerated('impayes', $filters, $data, $user['id']);
        $this->view->render('Finance::rapports/impayes', array_merge($data, ['user' => $user]));
    }

    // GET /v2/finance/rapports/caisse
    public function caisse(): void
    {
        $user    = $this->auth();
        if (!$this->policy->canView($user)) {
            $this->deny();
        }
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => 'caisse']));
        $data    = $this->service->getCaisseReport($filters);
        $this->dispatchGenerated('caisse', $filters, $data, $user['id']);
        $this->view->render('Finance::rapports/caisse', array_merge($data, ['user' => $user]));
    }

    // GET /v2/finance/rapports/analytique
    public function analytique(): void
    {
        $user    = $this->auth();
        if (!$this->policy->canView($user)) {
            $this->deny();
        }
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => 'analytique']));
        $data    = $this->service->getAnalytiqueReport($filters);
        $this->dispatchGenerated('analytique', $filters, $data, $user['id']);
        $this->view->render('Finance::rapports/analytique', array_merge($data, ['user' => $user]));
    }

    // GET /v2/finance/rapports/export?type=paiements&format=csv
    public function export(): void
    {
        $user = $this->auth();
        if (!$this->policy->canExport($user)) {
            $this->deny();
        }

        $type   = $_GET['type']   ?? 'paiements';
        $format = $_GET['format'] ?? 'csv';
        $filters = ReportFiltersDTO::fromRequest($_GET);

        $result = $this->service->exporterRapport($type, $format, $filters, $user['id']);

        if ($format === 'pdf') {
            $this->view->render('Finance::rapports/print', array_merge($result, [
                'type'   => $type,
                'user'   => $user,
            ]), 'none');
            return;
        }

        header('Content-Type: ' . ($result['mime'] ?? 'text/plain'));
        header('Content-Disposition: attachment; filename="' . ($result['filename'] ?? 'export.txt') . '"');
        header('Cache-Control: no-cache');
        echo $result['content'];
        exit;
    }

    // GET /v2/finance/rapports/print?type=paiements
    public function print(): void
    {
        $user = $this->auth();
        if (!$this->policy->canPrint($user)) {
            $this->deny();
        }

        $type    = $_GET['type'] ?? 'dashboard';
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type' => $type, 'per_page' => '500']));

        $data = match($type) {
            'paiements'  => $this->service->getPaiementsReport($filters),
            'factures'   => $this->service->getFacturesReport($filters),
            'impayes'    => $this->service->getImpayesReport($filters),
            'caisse'     => $this->service->getCaisseReport($filters),
            'analytique' => $this->service->getAnalytiqueReport($filters),
            default      => $this->service->getDashboard($filters),
        };

        $this->view->render('Finance::rapports/print', array_merge($data, [
            'type'    => $type,
            'filters' => $filters,
            'user'    => $user,
        ]), 'none');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function auth(): array
    {
        $user = Session::getUser();
        if (!$user) {
            $this->redirect('/login');
            exit;
        }
        return $user;
    }

    private function redirect(string $url): void
    {
        if ($url !== '' && !preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, BASE_URL) && $url[0] === '/') {
            $url = rtrim(BASE_URL, '/') . $url;
        }
        header('Location: ' . $url);
    }

    private function deny(): never
    {
        http_response_code(403);
        $this->view->render('errors/403', [], 'none');
        exit;
    }

    private function dispatchGenerated(string $type, ReportFiltersDTO $filters, array $data, int $userId): void
    {
        try {
            $nb = count(
                $data['pagination']['items']
                ?? $data['impayes']
                ?? $data['comparatifClasse']
                ?? []
            );
            EventDispatcher::dispatch(new FinancialReportGenerated(
                reportType:    $type,
                filters:       $filters->toArray(),
                nbLignes:      $nb,
                generatedById: $userId,
            ));
        } catch (\Throwable $e) {
            error_log('[RapportController::dispatchGenerated] ' . $e->getMessage());
        }
    }
}
