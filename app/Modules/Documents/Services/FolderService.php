<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\DTO\FolderDTO;
use App\Modules\Documents\Repositories\FolderRepository;
use Core\EventDispatcher;
use App\Modules\Documents\Events\FolderCreated;
use App\Modules\Documents\Events\FolderDeleted;

class FolderService
{
    private FolderRepository $repo;

    public function __construct()
    {
        $this->repo = new FolderRepository();
    }

    public function creer(FolderDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $id = $this->repo->insert([
            'parent_id'        => $dto->parentId,
            'nom'              => $dto->nom,
            'description'      => $dto->description,
            'module_source'    => $dto->moduleSource,
            'etablissement_id' => $dto->etablissementId,
            'icone'            => $dto->icone,
            'couleur'          => $dto->couleur,
            'ordre'            => $dto->ordre,
            'created_by'       => $userId,
        ]);

        EventDispatcher::dispatch(new FolderCreated($id, $dto->parentId, $dto->moduleSource, $userId));
        return $id;
    }

    public function modifier(int $id, FolderDTO $dto, int $userId): void
    {
        $this->requireFolder($id);
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $this->repo->update($id, [
            'nom'         => $dto->nom,
            'description' => $dto->description,
            'parent_id'   => $dto->parentId,
            'icone'       => $dto->icone,
            'couleur'     => $dto->couleur,
            'ordre'       => $dto->ordre,
        ]);
    }

    public function renommer(int $folderId, string $newNom, int $userId): void
    {
        $this->requireFolder($folderId);
        if (trim($newNom) === '') throw new \InvalidArgumentException('Le nom est requis.');
        $this->repo->update($folderId, ['nom' => trim($newNom)]);
    }

    public function deplacer(int $folderId, ?int $newParentId, int $userId): void
    {
        $this->requireFolder($folderId);
        if ($newParentId === $folderId) throw new \InvalidArgumentException('Un dossier ne peut pas être son propre parent.');
        $this->repo->update($folderId, ['parent_id' => $newParentId]);
    }

    public function supprimer(int $folderId, int $userId): void
    {
        $folder = $this->requireFolder($folderId);
        if ($this->repo->hasChildren($folderId)) {
            throw new \RuntimeException('Le dossier contient des sous-dossiers. Supprimez-les d\'abord.');
        }
        if ($this->repo->hasDocuments($folderId)) {
            throw new \RuntimeException('Le dossier contient des documents. Déplacez-les d\'abord.');
        }
        $this->repo->softDelete($folderId);
        EventDispatcher::dispatch(new FolderDeleted($folderId, $folder['module_source'], $userId));
    }

    // $moduleSource = null retourne les dossiers de tous les modules
    public function arbre(?string $moduleSource = null, int $etablissementId = 1): array
    {
        $all   = $this->repo->findByModule($moduleSource, $etablissementId);
        $map   = array_column($all, null, 'id');
        $roots = [];
        foreach ($all as &$node) {
            $node['children'] = [];
            if ($node['parent_id'] === null) {
                $roots[] = &$node;
            } else {
                $pid = $node['parent_id'];
                if (isset($map[$pid])) {
                    $map[$pid]['children'][] = &$node;
                }
            }
        }
        return $roots;
    }

    public function breadcrumb(int $folderId): array
    {
        return $this->repo->breadcrumb($folderId);
    }

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function findRoots(string $moduleSource, int $etablissementId = 1): array
    {
        return $this->repo->findRoots($moduleSource, $etablissementId);
    }

    private function requireFolder(int $id): array
    {
        $f = $this->repo->findById($id);
        if (!$f) throw new \RuntimeException('Dossier introuvable.');
        return $f;
    }
}
