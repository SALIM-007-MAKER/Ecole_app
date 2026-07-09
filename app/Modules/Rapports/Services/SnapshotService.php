<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\DTO\SnapshotDTO;
use App\Modules\Rapports\Repositories\SnapshotRepository;
use App\Modules\Rapports\Events\KpiSnapshot;
use Core\EventDispatcher;

class SnapshotService
{
    private SnapshotRepository    $repo;
    private DataAggregatorService $aggregator;

    public function __construct()
    {
        $this->repo       = new SnapshotRepository();
        $this->aggregator = new DataAggregatorService();
    }

    public function capturer(SnapshotDTO $dto): void
    {
        EventDispatcher::dispatch(new KpiSnapshot(
            $dto->domaine,
            $dto->metrique,
            $dto->valeur,
            $dto->periode,
            $dto->etablissementId,
        ));
    }

    public function capturerTousDomaines(int $etablissementId): int
    {
        $domaines = ['scolarite', 'academique', 'finance', 'vie_scolaire', 'rh', 'bibliotheque', 'inventaire'];
        $periode  = date('Y-m');
        $count    = 0;

        foreach ($domaines as $domaine) {
            try {
                $data = $this->aggregator->getForDomaine($domaine, $etablissementId);
                $counters = $data['counters'] ?? [];
                foreach ($counters as $metrique => $valeur) {
                    EventDispatcher::dispatch(new KpiSnapshot(
                        $domaine, $metrique, (float)$valeur, $periode, $etablissementId,
                    ));
                    $count++;
                }
            } catch (\Throwable) {
                // Ne pas bloquer la capture des autres domaines
            }
        }
        return $count;
    }

    public function historique(string $domaine, string $metrique, int $etablissementId, int $nbPeriodes = 12): array
    {
        return array_reverse(
            $this->repo->getMetrique($domaine, $metrique, $etablissementId, $nbPeriodes)
        );
    }
}
