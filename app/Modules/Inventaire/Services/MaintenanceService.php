<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\MaintenanceDTO;
use App\Modules\Inventaire\Repositories\MaintenanceRepository;
use App\Modules\Inventaire\Events\MaintenanceCreee;
use App\Modules\Inventaire\Events\MaintenanceTerminee;
use App\Services\AuditService;
use Core\EventDispatcher;

class MaintenanceService
{
    private MaintenanceRepository $maintenances;

    public function __construct()
    {
        $this->maintenances = new MaintenanceRepository();
    }

    public function lister(int $etablissementId, array $filters = []): array
    {
        return $this->maintenances->all($etablissementId, $filters);
    }

    public function trouver(int $id): ?array
    {
        return $this->maintenances->findById($id);
    }

    public function planifier(MaintenanceDTO $dto, int $userId, int $etablissementId): int
    {
        $id = $this->maintenances->create([
            ':article_id'      => $dto->articleId,
            ':type'            => $dto->type,
            ':statut'          => 'planifiee',
            ':date_planifiee'  => $dto->datePlanifiee,
            ':prestataire'     => $dto->prestataire,
            ':cout'            => $dto->cout,
            ':description'     => $dto->description,
            ':created_by'      => $userId,
            ':etablissement_id'=> $etablissementId,
        ]);

        AuditService::logCreate('inv_maintenances', $id, $userId, ['article_id' => $dto->articleId]);
        EventDispatcher::dispatch(new MaintenanceCreee($id, $dto->articleId, $dto->type, $dto->datePlanifiee, $userId, $etablissementId));
        return $id;
    }

    public function demarrer(int $id, int $userId): void
    {
        $m = $this->maintenances->findById($id);
        if (!$m || $m['statut'] !== 'planifiee') throw new \RuntimeException("Maintenance non démarrable.");
        $this->maintenances->updateStatut($id, 'en_cours', ['date_debut' => date('Y-m-d')]);
        AuditService::log('start', 'inv_maintenances', $id, $userId, []);
    }

    public function terminer(int $id, float $cout, string $rapport, int $userId, int $etablissementId): void
    {
        $m = $this->maintenances->findById($id);
        if (!$m || !in_array($m['statut'], ['planifiee', 'en_cours'], true)) {
            throw new \RuntimeException("Maintenance non terminable.");
        }
        $this->maintenances->updateStatut($id, 'terminee', [
            'date_fin' => date('Y-m-d'),
            'cout'     => $cout,
            'rapport'  => $rapport,
        ]);

        AuditService::log('finish', 'inv_maintenances', $id, $userId, ['cout' => $cout]);
        EventDispatcher::dispatch(new MaintenanceTerminee($id, (int)$m['article_id'], $cout, $rapport, $userId, $etablissementId));
    }

    public function annuler(int $id, int $userId): void
    {
        $m = $this->maintenances->findById($id);
        if (!$m || !in_array($m['statut'], ['planifiee', 'en_cours'], true)) {
            throw new \RuntimeException("Maintenance non annulable.");
        }
        $this->maintenances->updateStatut($id, 'annulee');
        AuditService::log('cancel', 'inv_maintenances', $id, $userId, []);
    }

    public function duesThisMonth(int $etablissementId): array
    {
        return $this->maintenances->duesThisMonth($etablissementId);
    }
}
