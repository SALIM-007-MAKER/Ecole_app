<?php

namespace App\Modules\Academique\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\Academique\Events\AnalyticsGenerated;
use App\Modules\Academique\Events\StatisticsUpdated;
use App\Services\AuditService;

class AnalyticsHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        if ($event instanceof AnalyticsGenerated) {
            $this->onGenerated($event);
        } elseif ($event instanceof StatisticsUpdated) {
            $this->onUpdated($event);
        }
    }

    private function onGenerated(AnalyticsGenerated $event): void
    {
        $this->audit->log(
            $event->generatedById,
            'analytics.generated',
            'academique',
            'analytics',
            $event->contextId ?? 0,
            null,
            [
                'dashboard_type' => $event->dashboardType,
                'periode_id'     => $event->periodeId,
                'computation_ms' => $event->computationMs,
            ]
        );
    }

    private function onUpdated(StatisticsUpdated $event): void
    {
        $this->audit->log(
            $event->updatedById,
            'statistics.updated',
            'academique',
            'analytics',
            $event->classeId ?? 0,
            null,
            [
                'scope'        => $event->scope,
                'periode_id'   => $event->periodeId,
                'triggered_by' => $event->triggeredBy,
            ]
        );
    }
}
