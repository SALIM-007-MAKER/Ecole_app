<?php

namespace App\Modules\RH\Enseignants\DTO;

class QualificationDTO
{
    public const TYPES = [
        'diplome'       => 'Diplôme',
        'certification' => 'Certification',
        'formation'     => 'Formation',
        'experience'    => 'Expérience',
        'autre'         => 'Autre',
    ];

    public function __construct(
        public readonly string  $type,
        public readonly string  $intitule,
        public readonly ?string $organisme,
        public readonly ?string $dateObtention,
        public readonly ?string $dateExpiration,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            type:           trim($data['type']            ?? 'diplome'),
            intitule:       trim($data['intitule']        ?? ''),
            organisme:      trim($data['organisme']       ?? '') ?: null,
            dateObtention:  trim($data['date_obtention']  ?? '') ?: null,
            dateExpiration: trim($data['date_expiration'] ?? '') ?: null,
            notes:          trim($data['notes']           ?? '') ?: null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->intitule === '') {
            $errors['intitule'][] = "L'intitulé est obligatoire.";
        }
        if (!array_key_exists($this->type, self::TYPES)) {
            $errors['type'][] = "Type de qualification invalide.";
        }
        return $errors;
    }

    public function toArray(): array
    {
        return [
            'type'            => $this->type,
            'intitule'        => $this->intitule,
            'organisme'       => $this->organisme,
            'date_obtention'  => $this->dateObtention,
            'date_expiration' => $this->dateExpiration,
            'notes'           => $this->notes,
        ];
    }
}
