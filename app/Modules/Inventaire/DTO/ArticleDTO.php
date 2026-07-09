<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class ArticleDTO
{
    public function __construct(
        public readonly string  $reference,
        public readonly string  $designation,
        public readonly string  $type,
        public readonly ?int    $categorieId,
        public readonly string  $uniteMesure,
        public readonly float   $seuilAlerte,
        public readonly float   $seuilCritique,
        public readonly float   $valeurUnitaire,
        public readonly ?int    $fournisseurId,
        public readonly ?string $description,
        public readonly ?string $barcode,
        public readonly ?string $numeroSerie,
        public readonly ?int    $localisationDefaut,
        public readonly int     $garantieMois,
        public readonly bool    $actif,
        public readonly ?string $image,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            reference:          trim($data['reference'] ?? ''),
            designation:        trim($data['designation'] ?? ''),
            type:               $data['type'] ?? 'consommable',
            categorieId:        isset($data['categorie_id']) ? (int)$data['categorie_id'] : null,
            uniteMesure:        $data['unite_mesure'] ?? 'unité',
            seuilAlerte:        (float)($data['seuil_alerte'] ?? 0),
            seuilCritique:      (float)($data['seuil_critique'] ?? 0),
            valeurUnitaire:     (float)($data['valeur_unitaire'] ?? 0),
            fournisseurId:      isset($data['fournisseur_id']) ? (int)$data['fournisseur_id'] : null,
            description:        $data['description'] ?? null,
            barcode:            $data['barcode'] ?? null,
            numeroSerie:        $data['numero_serie'] ?? null,
            localisationDefaut: isset($data['localisation_defaut']) ? (int)$data['localisation_defaut'] : null,
            garantieMois:       (int)($data['garantie_mois'] ?? 0),
            actif:              (bool)($data['actif'] ?? true),
            image:              $data['image'] ?? null,
        );
    }
}
