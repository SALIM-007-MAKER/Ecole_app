<?php

namespace App\Modules\Academique\Listeners;

use Core\Listener;
use Core\Event;
use App\Modules\Academique\Events\AnalyticsGenerated;
use App\Modules\Academique\Events\StatisticsUpdated;
use App\Services\AuditService;

class AnalyticsHandler implements Listener
{
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
        AuditService::log(
            action  : 'analytics.generated',
            entity  : 'analytics',
            entityId: $event->contextId ?? 0,
            userId  : $event->generatedById,
            details : [
                'dashboard_type' => $event->dashboardType,
                'periode_id'     => $event->periodeId,
                'computation_ms' => $event->computationMs,
            ]
        );
    }

    private function onUpdated(StatisticsUpdated $event): void
    {
        AuditService::log(
            action  : 'statistics.updated',
            entity  : 'analytics',
            entityId: $event->classeId ?? 0,
            userId  : $event->updatedById,
            details : [
                'scope'        => $event->scope,
                'periode_id'   => $event->periodeId,
                'triggered_by' => $event->triggeredBy,
            ]
        );
    }
}
