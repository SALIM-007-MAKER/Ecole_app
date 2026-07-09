<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\DTO\ReportFiltersDTO;

interface FinancialReportInterface
{
    public function getDashboard(ReportFiltersDTO $filters): array;

    public function getPaiementsReport(ReportFiltersDTO $filters): array;

    public function getFacturesReport(ReportFiltersDTO $filters): array;

    public function getImpayesReport(ReportFiltersDTO $filters): array;

    public function getCaisseReport(ReportFiltersDTO $filters): array;

    public function getAnalytiqueReport(ReportFiltersDTO $filters): array;

    public function exporterRapport(string $type, string $format, ReportFiltersDTO $filters, int $userId): array;
}
