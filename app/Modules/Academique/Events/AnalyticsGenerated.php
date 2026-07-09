<?php

namespace App\Modules\Academique\Events;

use Core\Event;

class AnalyticsGenerated extends Event
{
    public function __construct(
        public readonly string  $dashboardType,   // 'directeur' | 'enseignant' | 'responsable'
        public readonly int     $periodeId,
        public readonly ?int    $contextId,        // classeId or enseignantId
        public readonly string  $generatedByRole,
        public readonly int     $generatedById,
        public readonly float   $computationMs,    // performance tracking
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'analytics.generated';
    }

    public function toArray(): array
    {
        return [
            'dashboard_type'    => $this->dashboardType,
            'periode_id'        => $this->periodeId,
            'context_id'        => $this->contextId,
            'generated_by_role' => $this->generatedByRole,
            'generated_by_id'   => $this->generatedById,
            'computation_ms'    => $this->computationMs,
            'fired_at'          => $this->getFiredAt(),
        ];
    }
}
