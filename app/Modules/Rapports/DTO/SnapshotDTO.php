<?php
declare(strict_types=1);

namespace App\Modules\Rapports\DTO;

class SnapshotDTO
{
    public function __construct(
        public readonly string $domaine,
        public readonly string $metrique,
        public readonly float  $valeur,
        public readonly string $periode,
        public readonly int    $etablissementId,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            domaine:         trim($data['domaine']         ?? ''),
            metrique:        trim($data['metrique']        ?? ''),
            valeur:          (float)($data['valeur']       ?? 0.0),
            periode:         trim($data['periode']         ?? date('Y-m')),
            etablissementId: (int)($data['etablissement_id'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'domaine'          => $this->domaine,
            'metrique'         => $this->metrique,
            'valeur'           => $this->valeur,
            'periode'          => $this->periode,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
