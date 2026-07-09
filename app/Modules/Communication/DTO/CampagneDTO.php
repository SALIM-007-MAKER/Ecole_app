<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class CampagneDTO
{
    public function __construct(
        public readonly string  $nom,
        public readonly ?string $description = null,
        public readonly array   $canaux      = ['internal'],
        public readonly string  $cibleType   = 'tous',
        public readonly ?int    $cibleId     = null,
        public readonly ?int    $templateId  = null,
        public readonly array   $variables   = [],
        public readonly ?string $planifieAt  = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $canaux = $data['canaux'] ?? ['internal'];
        if (!is_array($canaux)) {
            $canaux = [$canaux];
        }
        return new self(
            nom:         trim($data['nom']         ?? ''),
            description: !empty($data['description']) ? trim($data['description']) : null,
            canaux:      $canaux,
            cibleType:   $data['cible_type']      ?? 'tous',
            cibleId:     isset($data['cible_id'])  ? (int) $data['cible_id']  : null,
            templateId:  isset($data['template_id']) ? (int) $data['template_id'] : null,
            variables:   $data['variables']        ?? [],
            planifieAt:  !empty($data['planifie_at']) ? $data['planifie_at'] : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->nom)) {
            $errors[] = 'Le nom de la campagne est requis.';
        }
        if (empty($this->canaux)) {
            $errors[] = 'Au moins un canal est requis.';
        }
        return $errors;
    }
}
