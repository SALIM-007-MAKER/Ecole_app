<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class AmortissementDTO
{
    public function __construct(
        public readonly int    $articleId,
        public readonly float  $valeurAchat,
        public readonly string $dateAchat,
        public readonly int    $dureeAmortissementMois,
        public readonly string $methode,
        public readonly float  $valeurResiduelle,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            articleId:               (int)($data['article_id'] ?? 0),
            valeurAchat:             (float)($data['valeur_achat'] ?? 0),
            dateAchat:               $data['date_achat'] ?? date('Y-m-d'),
            dureeAmortissementMois:  (int)($data['duree_amortissement_mois'] ?? 60),
            methode:                 $data['methode'] ?? 'lineaire',
            valeurResiduelle:        (float)($data['valeur_residuelle'] ?? 0),
        );
    }
}
