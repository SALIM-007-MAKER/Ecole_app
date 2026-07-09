<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Modules\Rapports\Events\RapportGenere;
use App\Modules\Rapports\Events\RapportPlanifie;
use App\Modules\Rapports\Events\RapportExecute;
use App\Modules\Rapports\Events\RapportExporte;
use App\Modules\Rapports\Events\KpiSnapshot;
use App\Modules\Rapports\Events\DashboardConsulte;

class BiAuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        $data = $event->toArray();

        match (true) {
            $event instanceof RapportGenere => $this->audit->log(
                $data['user_id'] ?? null,
                'generer',
                'rapports',
                'rapport',
                0,
                null,
                $data,
            ),
            $event instanceof RapportPlanifie => $this->audit->log(
                $data['user_id'] ?? null,
                'planifier',
                'rapports',
                'planification',
                (int)($data['planification_id'] ?? 0),
                null,
                $data,
            ),
            $event instanceof RapportExecute => $this->audit->log(
                null,
                'executer',
                'rapports',
                'execution',
                (int)($data['execution_id'] ?? 0),
                null,
                $data,
            ),
            $event instanceof RapportExporte => $this->audit->log(
                $data['user_id'] ?? null,
                'exporter',
                'rapports',
                'export',
                (int)($data['export_id'] ?? 0),
                null,
                $data,
            ),
            $event instanceof KpiSnapshot => $this->audit->log(
                null,
                'snapshot',
                'rapports',
                'kpi',
                0,
                null,
                $data,
            ),
            $event instanceof DashboardConsulte => $this->audit->log(
                $data['user_id'] ?? null,
                'consulter',
                'rapports',
                'dashboard',
                0,
                null,
                $data,
            ),
            default => null,
        };
    }
}
