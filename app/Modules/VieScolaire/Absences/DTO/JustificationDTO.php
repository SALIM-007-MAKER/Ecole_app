<?php

namespace App\Modules\VieScolaire\Absences\DTO;

class JustificationDTO
{
    public function __construct(
        public readonly ?int    $motifId     = null,
        public readonly ?string $description = null,
        public readonly ?string $fichier     = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            motifId:     isset($data['motif_id']) && $data['motif_id'] !== ''
                             ? (int)$data['motif_id'] : null,
            description: $data['description'] !== '' ? ($data['description'] ?? null) : null,
            fichier:     $data['fichier'] ?? null,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->motifId === null && empty($this->description)) {
            $errors[] = "Un motif ou une description est obligatoire.";
        }

        return $errors;
    }
}
