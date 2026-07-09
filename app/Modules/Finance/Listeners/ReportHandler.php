<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Events\FinancialReportGenerated;
use App\Modules\Finance\Events\FinancialReportExported;
use Core\AuditService;
use Core\Event;
use Core\Listener;

class ReportHandler implements Listener
{
    public function handle(Event $event): void
    {
        match(true) {
            $event instanceof FinancialReportGenerated => $this->onGenerated($event),
            $event instanceof FinancialReportExported  => $this->onExported($event),
            default                                    => null,
        };
    }

    private function onGenerated(FinancialReportGenerated $event): void
    {
        try {
            AuditService::log(
                userId:   $event->generatedById,
                action:   'rapport_genere',
                module:   'finance',
                entite:   'rapport',
                entiteId: null,
                avant:    null,
                apres:    [
                    'type'      => $event->reportType,
                    'nb_lignes' => $event->nbLignes,
                    'filters'   => $event->filters,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[ReportHandler::onGenerated] ' . $e->getMessage());
        }
    }

    private function onExported(FinancialReportExported $event): void
    {
        try {
            AuditService::log(
                userId:   $event->exportedById,
                action:   'rapport_exporte',
                module:   'finance',
                entite:   'rapport',
                entiteId: null,
                avant:    null,
                apres:    [
                    'type'     => $event->reportType,
                    'format'   => $event->format,
                    'nb_lignes'=> $event->nbLignes,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[ReportHandler::onExported] ' . $e->getMessage());
        }
    }
}
