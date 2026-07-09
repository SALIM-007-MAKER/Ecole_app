<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\DTO\OuvrageDTO;
use App\Modules\Bibliotheque\DTO\OuvrageFiltersDTO;
use App\Modules\Bibliotheque\Events\OuvrageAjoute;
use App\Modules\Bibliotheque\Events\OuvrageModifie;
use App\Modules\Bibliotheque\Events\OuvrageArchive;
use App\Modules\Bibliotheque\Repositories\OuvrageRepository;
use App\Modules\Bibliotheque\Repositories\AuteurRepository;
use App\Modules\Bibliotheque\Repositories\EditeurRepository;
use App\Modules\Bibliotheque\Repositories\CategorieRepository;
use App\Modules\Bibliotheque\Repositories\TagRepository;
use Core\EventDispatcher;

class CatalogueService
{
    private OuvrageRepository   $ouvrages;
    private AuteurRepository    $auteurs;
    private EditeurRepository   $editeurs;
    private CategorieRepository $categories;
    private TagRepository       $tags;

    public function __construct()
    {
        $this->ouvrages   = new OuvrageRepository();
        $this->auteurs    = new AuteurRepository();
        $this->editeurs   = new EditeurRepository();
        $this->categories = new CategorieRepository();
        $this->tags       = new TagRepository();
    }

    public function ajouterOuvrage(OuvrageDTO $dto, int $userId, int $etablissementId): int
    {
        $id = $this->ouvrages->insert([
            'isbn'               => $dto->isbn,
            'isbn13'             => $dto->isbn,
            'titre'              => $dto->titre,
            'sous_titre'         => $dto->sousTitre,
            'resume'             => $dto->resume,
            'annee_edition'      => $dto->anneeEdition,
            'nombre_pages'       => $dto->nombrePages,
            'langue'             => $dto->langue,
            'image_couverture'   => $dto->imageCouverture,
            'type'               => $dto->type,
            'cote'               => $dto->cote,
            'localisation_defaut'=> $dto->localisationDefaut,
            'editeur_id'         => $dto->editeurId,
            'etablissement_id'   => $etablissementId,
            'created_by'         => $userId,
        ]);

        $this->ouvrages->syncAuteurs($id, $dto->auteurIds);
        $this->ouvrages->syncCategories($id, $dto->categorieIds);

        $tagIds = $this->resolveTagIds($dto->tagIds, $etablissementId);
        $this->ouvrages->syncTags($id, $tagIds);

        EventDispatcher::dispatch(new OuvrageAjoute($id, $dto->titre, $dto->isbn, $userId, $etablissementId));

        return $id;
    }

    public function modifierOuvrage(int $id, OuvrageDTO $dto, int $userId): void
    {
        $this->ouvrages->update($id, [
            'isbn'               => $dto->isbn,
            'isbn13'             => $dto->isbn,
            'titre'              => $dto->titre,
            'sous_titre'         => $dto->sousTitre,
            'resume'             => $dto->resume,
            'annee_edition'      => $dto->anneeEdition,
            'nombre_pages'       => $dto->nombrePages,
            'langue'             => $dto->langue,
            'image_couverture'   => $dto->imageCouverture,
            'type'               => $dto->type,
            'cote'               => $dto->cote,
            'localisation_defaut'=> $dto->localisationDefaut,
            'editeur_id'         => $dto->editeurId,
        ]);

        $this->ouvrages->syncAuteurs($id, $dto->auteurIds);
        $this->ouvrages->syncCategories($id, $dto->categorieIds);
        $this->ouvrages->syncTags($id, $dto->tagIds);

        EventDispatcher::dispatch(new OuvrageModifie($id, $dto->titre, $userId));
    }

    public function archiverOuvrage(int $id, int $userId): void
    {
        $ouvrage = $this->ouvrages->findById($id);
        if ($ouvrage === null) return;
        $this->ouvrages->softDelete($id);
        EventDispatcher::dispatch(new OuvrageArchive($id, $ouvrage['titre'], $userId));
    }

    public function trouver(int $id): ?array
    {
        return $this->ouvrages->findWithDetails($id);
    }

    public function trouverParIsbn(string $isbn): ?array
    {
        return $this->ouvrages->findByIsbn($isbn);
    }

    public function lister(OuvrageFiltersDTO $filters, int $etablissementId): array
    {
        return $this->ouvrages->search($filters, $etablissementId);
    }

    public function suggestions(string $terme, int $etablissementId): array
    {
        return $this->ouvrages->suggestions($terme, $etablissementId);
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function ajouterAuteur(array $data, int $etablissementId): int
    {
        return $this->auteurs->insert(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    public function modifierAuteur(int $id, array $data): void
    {
        $this->auteurs->update($id, $data);
    }

    public function archiverAuteur(int $id): void
    {
        $this->auteurs->softDelete($id);
    }

    public function listerAuteurs(int $etablissementId): array
    {
        return $this->auteurs->findAll($etablissementId);
    }

    public function ajouterEditeur(array $data, int $etablissementId): int
    {
        return $this->editeurs->insert(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    public function modifierEditeur(int $id, array $data): void
    {
        $this->editeurs->update($id, $data);
    }

    public function archiverEditeur(int $id): void
    {
        $this->editeurs->softDelete($id);
    }

    public function listerEditeurs(int $etablissementId): array
    {
        return $this->editeurs->findAll($etablissementId);
    }

    public function ajouterCategorie(array $data, int $etablissementId): int
    {
        return $this->categories->insert(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    public function modifierCategorie(int $id, array $data): void
    {
        $this->categories->update($id, $data);
    }

    public function archiverCategorie(int $id): void
    {
        $this->categories->softDelete($id);
    }

    public function treeCategories(int $etablissementId): array
    {
        return $this->categories->findTree($etablissementId);
    }

    public function listerCategories(int $etablissementId): array
    {
        return $this->categories->findAll($etablissementId);
    }

    public function listerTags(int $etablissementId): array
    {
        return $this->tags->findAll($etablissementId);
    }

    public function ajouterTag(array $data, int $etablissementId): int
    {
        return $this->tags->insert(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    private function resolveTagIds(array $tagIds, int $etablissementId): array
    {
        return $tagIds;
    }
}
