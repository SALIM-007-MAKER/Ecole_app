<?php

namespace App\Modules\VieScolaire\Retards\DTO;

class LateJustificationDTO
{
    public function __construct(
        public readonly ?string $motifDescription,
        public readonly ?string $fichierJustificatif,
    ) {}

    public static function fromRequest(array $data, ?string $uploadedFile = null): self
    {
        return new self(
            motifDescription:    !empty($data['motif_description']) ? trim($data['motif_description']) : null,
            fichierJustificatif: $uploadedFile,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->motifDescription) && empty($this->fichierJustificatif)) {
            $errors['motif_description'][] = 'Un motif ou un fichier justificatif est obligatoire.';
        }
        if ($this->motifDescription !== null && mb_strlen($this->motifDescription) < 10) {
            $errors['motif_description'][] = 'Le motif doit comporter au moins 10 caractères.';
        }

        return $errors;
    }
}
