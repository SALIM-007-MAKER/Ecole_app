<?php

namespace App\Modules\VieScolaire\Discipline\DTO;

class AppealDTO
{
    public function __construct(
        public readonly string  $description,
        public readonly ?string $pieceJointe,
    ) {}

    public static function fromRequest(array $data, ?string $uploadedFile = null): self
    {
        return new self(
            description: trim($data['description'] ?? ''),
            pieceJointe: $uploadedFile,
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (mb_strlen(trim($this->description)) < 20) {
            $errors['description'][] = 'Le motif d\'appel doit comporter au moins 20 caractères.';
        }

        return $errors;
    }
}
