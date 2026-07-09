<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class OuvrageDTO
{
    public function __construct(
        public readonly string  $titre,
        public readonly ?string $sousTitre,
        public readonly ?string $isbn,
        public readonly ?string $resume,
        public readonly ?int    $anneeEdition,
        public readonly ?int    $nombrePages,
        public readonly string  $langue,
        public readonly string  $type,
        public readonly ?string $cote,
        public readonly ?string $localisationDefaut,
        public readonly ?int    $editeurId,
        public readonly ?string $imageCouverture,
        public readonly array   $auteurIds,
        public readonly array   $categorieIds,
        public readonly array   $tagIds,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            titre:              trim($data['titre'] ?? ''),
            sousTitre:          $data['sous_titre'] ?? null,
            isbn:               $data['isbn'] ?? null,
            resume:             $data['resume'] ?? null,
            anneeEdition:       isset($data['annee_edition']) && $data['annee_edition'] !== '' ? (int)$data['annee_edition'] : null,
            nombrePages:        isset($data['nombre_pages']) && $data['nombre_pages'] !== '' ? (int)$data['nombre_pages'] : null,
            langue:             $data['langue'] ?? 'fr',
            type:               $data['type'] ?? 'livre',
            cote:               $data['cote'] ?? null,
            localisationDefaut: $data['localisation_defaut'] ?? null,
            editeurId:          isset($data['editeur_id']) && $data['editeur_id'] !== '' ? (int)$data['editeur_id'] : null,
            imageCouverture:    $data['image_couverture'] ?? null,
            auteurIds:          array_map('intval', (array)($data['auteur_ids'] ?? [])),
            categorieIds:       array_map('intval', (array)($data['categorie_ids'] ?? [])),
            tagIds:             array_map('intval', (array)($data['tag_ids'] ?? [])),
        );
    }
}
