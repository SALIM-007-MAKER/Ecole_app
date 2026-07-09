<?php

namespace App\Modules\Finance\Events;

use Core\Event;

class FinancialReportExported extends Event
{
    public function __construct(
        public readonly string $reportType,
        public readonly string $format,
        public readonly int    $nbLignes,
        public readonly int    $exportedById,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'finance.report.exported';
    }

    public function toArray(): array
    {
        return [
            'report_type'  => $this->reportType,
            'format'       => $this->format,
            'nb_lignes'    => $this->nbLignes,
            'exported_by'  => $this->exportedById,
        ];
    }
}
