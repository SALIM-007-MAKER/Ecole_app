<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\DTO\TagDTO;
use App\Modules\Documents\Repositories\TagRepository;

class TagService
{
    private TagRepository $repo;

    public function __construct()
    {
        $this->repo = new TagRepository();
    }

    public function creer(TagDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $existing = $this->repo->findByNom($dto->nom, $dto->etablissementId);
        if ($existing) throw new \RuntimeException("Un tag '{$dto->nom}' existe déjà.");

        return $this->repo->insert([
            'nom'              => $dto->nom,
            'couleur'          => $dto->couleur,
            'module_source'    => $dto->moduleSource,
            'etablissement_id' => $dto->etablissementId,
            'created_by'       => $userId,
        ]);
    }

    public function attacher(int $documentId, array $tagIds, int $userId): void
    {
        $this->repo->attachTags($documentId, $tagIds, $userId);
    }

    public function detacher(int $documentId, int $tagId, int $userId): void
    {
        $this->repo->detachTag($documentId, $tagId);
    }

    public function synchroniser(int $documentId, array $tagIds, int $userId): void
    {
        $this->repo->syncTags($documentId, $tagIds, $userId);
    }

    public function lister(?string $moduleSource = null, int $etablissementId = 1): array
    {
        return $this->repo->findAll($moduleSource, $etablissementId);
    }

    public function listerDuDocument(int $documentId): array
    {
        return $this->repo->findByDocument($documentId);
    }

    public function tous(): array
    {
        return $this->lister();
    }
}
