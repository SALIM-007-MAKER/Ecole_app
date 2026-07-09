<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\DTO;

class FournisseurDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly ?string $code,
        public readonly ?string $email,
        public readonly ?string $telephone,
        public readonly ?string $adresse,
        public readonly ?string $siteWeb,
        public readonly ?string $rib,
        public readonly int     $delaiLivraisonJ,
        public readonly ?string $conditionsPaiement,
        public readonly string  $statut,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nom:                trim($data['nom'] ?? ''),
            code:               $data['code'] ?? null,
            email:              $data['email'] ?? null,
            telephone:          $data['telephone'] ?? null,
            adresse:            $data['adresse'] ?? null,
            siteWeb:            $data['site_web'] ?? null,
            rib:                $data['rib'] ?? null,
            delaiLivraisonJ:    (int)($data['delai_livraison_j'] ?? 7),
            conditionsPaiement: $data['conditions_paiement'] ?? null,
            statut:             $data['statut'] ?? 'actif',
            notes:              $data['notes'] ?? null,
        );
    }
}
