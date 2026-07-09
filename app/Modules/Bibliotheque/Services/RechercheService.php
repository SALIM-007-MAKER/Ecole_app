<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\DTO\RechercheDTO;
use App\Modules\Bibliotheque\Repositories\OuvrageRepository;

class RechercheService
{
    private OuvrageRepository $ouvrages;

    public function __construct()
    {
        $this->ouvrages = new OuvrageRepository();
    }

    public function rechercher(RechercheDTO $dto, int $etablissementId): array
    {
        return $this->ouvrages->search($dto, $etablissementId);
    }

    public function suggestions(string $terme, int $etablissementId, int $limit = 5): array
    {
        return $this->ouvrages->suggestions($terme, $etablissementId, $limit);
    }

    public function ouvragesSimilaires(int $ouvrageId, int $etablissementId, int $limit = 5): array
    {
        $ouvrage = $this->ouvrages->findWithDetails($ouvrageId);
        if ($ouvrage === null) return [];

        $dto = new RechercheDTO(
            terme: null,
            categorieId: !empty($ouvrage['categories']) ? (int)$ouvrage['categories'][0]['id'] : null,
            auteurId: null,
            editeurId: null,
            langue: $ouvrage['langue'] ?? null,
            type: null,
            anneeMin: null,
            anneeMax: null,
            tagIds: [],
            disponibleSeulement: false,
            tri: 'pertinence',
            page: 1,
            perPage: $limit + 1
        );

        $resultats = $this->ouvrages->search($dto, $etablissementId);
        return array_filter($resultats['data'] ?? [], fn($o) => (int)$o['id'] !== $ouvrageId);
    }
}
