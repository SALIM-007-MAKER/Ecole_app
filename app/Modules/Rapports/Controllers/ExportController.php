<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Controllers;

use Core\Controller;
use App\Modules\Rapports\DTO\ReportFiltersDTO;
use App\Modules\Rapports\Services\ReportGeneratorService;
use App\Modules\Rapports\Repositories\ExportRepository;
use App\Shared\Analytics\ExportEngine;

class ExportController extends Controller
{
    private ReportGeneratorService $generator;
    private ExportRepository       $exportRepo;
    private ExportEngine           $engine;

    public function __construct()
    {
        $this->generator  = new ReportGeneratorService();
        $this->exportRepo = new ExportRepository();
        $this->engine     = new ExportEngine();
    }

    public function index(): void
    {
        $this->requirePermission('rapports.exporter');
        $etab   = (int)($this->user['etablissement_id'] ?? 1);
        $userId = (int)($this->user['id']               ?? 0);
        $exports = $this->exportRepo->listeParUser($userId, $etab);
        $this->render('Rapports::exports/index', ['exports' => $exports]);
    }

    public function form(): void
    {
        $this->requirePermission('rapports.exporter');
        $domaine = $_GET['domaine'] ?? 'scolarite';
        $this->render('Rapports::exports/form', ['domaine' => $domaine]);
    }

    public function csv(): void
    {
        $this->requirePermission('rapports.exporter');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type_export' => 'csv']));
        $csv     = $this->generator->genererCsv($filters, $etab, $userId);
        $filename = 'rapport_' . $filters->domaine . '_' . date('Ymd') . '.csv';
        $this->engine->sendCsvResponse($csv, $filename);
    }

    public function excel(): void
    {
        $this->requirePermission('rapports.exporter');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type_export' => 'excel']));
        $xml     = $this->generator->genererExcel($filters, $etab, $userId);
        $filename = 'rapport_' . $filters->domaine . '_' . date('Ymd') . '.xls';
        $this->engine->sendExcelResponse($xml, $filename);
    }

    public function pdf(): void
    {
        $this->requirePermission('rapports.exporter');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, ['type_export' => 'pdf']));
        $html    = $this->generator->genererHtml($filters, $etab, $userId);

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    public function apercu(): void
    {
        $this->requirePermission('rapports.exporter');
        $etab    = (int)($this->user['etablissement_id'] ?? 1);
        $userId  = (int)($this->user['id']               ?? 0);
        $filters = ReportFiltersDTO::fromRequest(array_merge($_GET, $_POST));
        $rapport = $this->generator->generer($filters, $etab, $userId);
        $this->render('Rapports::exports/apercu', ['rapport' => $rapport, 'filters' => $filters]);
    }
}
