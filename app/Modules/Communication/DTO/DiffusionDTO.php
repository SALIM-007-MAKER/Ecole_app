<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class DiffusionDTO
{
    public function __construct(
        public readonly string  $sujet,
        public readonly string  $corps,
        public readonly array   $canaux,
        public readonly string  $cibleType,
        public readonly ?int    $cibleId    = null,
        public readonly ?string $roleCode   = null,
        public readonly ?int    $templateId = null,
        public readonly array   $variables  = [],
        public readonly string  $priorite   = 'normale',
    ) {}

    public static function fromRequest(array $data): self
    {
        $canaux = $data['canaux'] ?? ['internal'];
        if (!is_array($canaux)) {
            $canaux = [$canaux];
        }
        return new self(
            sujet:      trim($data['sujet']      ?? ''),
            corps:      trim($data['corps']      ?? ''),
            canaux:     $canaux,
            cibleType:  $data['cible_type']     ?? 'groupe',
            cibleId:    isset($data['cible_id']) ? (int) $data['cible_id'] : null,
            roleCode:   $data['role_code']      ?? null,
            templateId: isset($data['template_id']) ? (int) $data['template_id'] : null,
            variables:  $data['variables']      ?? [],
            priorite:   $data['priorite']       ?? 'normale',
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->sujet)) {
            $errors[] = 'Le sujet est requis.';
        }
        if (empty($this->corps)) {
            $errors[] = 'Le message est requis.';
        }
        if (empty($this->canaux)) {
            $errors[] = 'Au moins un canal est requis.';
        }
        return $errors;
    }
}
