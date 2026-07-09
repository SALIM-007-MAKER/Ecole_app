<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class ThreadDTO
{
    public function __construct(
        public readonly string  $sujet,
        public readonly array   $participantIds,
        public readonly string  $corpsInitial,
        public readonly string  $type          = 'direct',
        public readonly ?string $moduleSource  = null,
        public readonly ?string $entiteType    = null,
        public readonly ?int    $entiteId      = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $ids = $data['participant_ids'] ?? [];
        if (is_string($ids)) {
            $ids = array_map('intval', explode(',', $ids));
        } else {
            $ids = array_map('intval', (array) $ids);
        }

        return new self(
            sujet:          trim($data['sujet']         ?? ''),
            participantIds: array_filter($ids),
            corpsInitial:   trim($data['corps_initial'] ?? ''),
            type:           $data['type']               ?? 'direct',
            moduleSource:   $data['module_source']      ?? null,
            entiteType:     $data['entite_type']        ?? null,
            entiteId:       isset($data['entite_id'])   ? (int) $data['entite_id'] : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->sujet)) {
            $errors[] = 'Le sujet est requis.';
        }
        if (empty($this->participantIds)) {
            $errors[] = 'Au moins un destinataire est requis.';
        }
        if (empty($this->corpsInitial)) {
            $errors[] = 'Le message initial est requis.';
        }
        return $errors;
    }
}
