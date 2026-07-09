<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\DTO;

class ExemplaireDTO
{
    public function __construct(
        public readonly int     $ouvrageId,
        public readonly string  $numeroInventaire,
        public readonly ?string $codeBarre,
        public readonly ?string $localisation,
        public readonly string  $etat,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(array $data, int $ouvrageId): self
    {
        return new self(
            ouvrageId:        $ouvrageId,
            numeroInventaire: trim($data['numero_inventaire'] ?? ''),
            codeBarre:        $data['code_barre'] ?? null,
            localisation:     $data['localisation'] ?? null,
            etat:             $data['etat'] ?? 'bon',
            notes:            $data['notes'] ?? null,
        );
    }
}
