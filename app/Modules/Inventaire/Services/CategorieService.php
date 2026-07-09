<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\Repositories\CategorieRepository;
use App\Services\AuditService;

class CategorieService
{
    private CategorieRepository $repo;

    public function __construct()
    {
        $this->repo = new CategorieRepository();
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
            ':description'      => $data['description'] ?? null,
            ':parent_id'        => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':code'             => $data['code'] ?? null,
            ':couleur'          => $data['couleur'] ?? '#6366f1',
            ':etablissement_id' => $etablissementId,
        ]);
        AuditService::logCreate('inv_categories', $id, $userId, ['nom' => $data['nom'] ?? '']);
        return $id;
    }

    public function modifier(int $id, array $data, int $userId): void
    {
        $this->repo->update($id, [
            ':nom'       => trim($data['nom'] ?? ''),
            ':description'=> $data['description'] ?? null,
            ':parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':code'      => $data['code'] ?? null,
            ':couleur'   => $data['couleur'] ?? '#6366f1',
        ]);
        AuditService::log('update', 'inv_categories', $id, $userId, ['nom' => $data['nom'] ?? '']);
    }

    public function supprimer(int $id, int $userId): void
    {
        if ($this->repo->hasArticles($id)) {
            throw new \RuntimeException("Catégorie utilisée par des articles.");
        }
        $this->repo->softDelete($id);
        AuditService::log('delete', 'inv_categories', $id, $userId, []);
    }
}
