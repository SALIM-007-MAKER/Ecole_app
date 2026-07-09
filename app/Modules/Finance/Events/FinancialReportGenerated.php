<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FinancialReportGenerated extends Event
{
    public function __construct(
        public readonly string $reportType,
        public readonly array  $filters,
        public readonly int    $nbLignes,
        public readonly int    $generatedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.report.generated';
    }

    public function toArray(): array
    {
        return [
            'report_type'    => $this->reportType,
            'filters'        => $this->filters,
            'nb_lignes'      => $this->nbLignes,
            'generated_by'   => $this->generatedById,
        ];
    }
}
