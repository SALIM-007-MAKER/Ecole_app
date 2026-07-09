<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\Repositories\EmplacementRepository;
use App\Services\AuditService;

class EmplacementService
{
    private EmplacementRepository $repo;

    public function __construct()
    {
        $this->repo = new EmplacementRepository();
    }

    public function lister(int $etablissementId): array
    {
        return $this->repo->all($etablissementId);
    }

    public function trouver(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function creer(array $data, int $userId, int $etablissementId): int
    {
        $id = $this->repo->create([
            ':nom'              => trim($data['nom'] ?? ''),
            ':code'             => $data['code'] ?? null,
            ':description'      => $data['description'] ?? null,
            ':type'             => $data['type'] ?? 'autre',
            ':parent_id'        => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':etablissement_id' => $etablissementId,
        ]);
        AuditService::logCreate('inv_emplacements', $id, $userId, ['nom' => $data['nom'] ?? '']);
        return $id;
    }

    public function modifier(int $id, array $data, int $userId): void
    {
        $this->repo->update($id, [
            ':nom'        => trim($data['nom'] ?? ''),
            ':code'       => $data['code'] ?? null,
            ':description'=> $data['description'] ?? null,
            ':type'       => $data['type'] ?? 'autre',
            ':parent_id'  => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
        ]);
        AuditService::log('update', 'inv_emplacements', $id, $userId, []);
    }

    public function supprimer(int $id, int $userId): void
    {
        if ($this->repo->hasStock($id)) {
            throw new \RuntimeException("Impossible de supprimer : stock présent dans cet emplacement.");
        }
        $this->repo->delete($id);
        AuditService::log('delete', 'inv_emplacements', $id, $userId, []);
    }
}
