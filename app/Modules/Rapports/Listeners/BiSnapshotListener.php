<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Rapports\Events\KpiSnapshot;
use App\Modules\Rapports\Repositories\SnapshotRepository;

class BiSnapshotListener implements Listener
{
    private SnapshotRepository $repo;

    public function __construct()
    {
        $this->repo = new SnapshotRepository();
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof KpiSnapshot)) {
            return;
        }
        $this->repo->upsert(
            $event->domaine,
            $event->metrique,
            $event->valeur,
            $event->periode,
            $event->etablissementId,
        );
    }
}
