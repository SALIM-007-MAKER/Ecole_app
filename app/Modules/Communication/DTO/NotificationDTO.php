<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class NotificationDTO
{
    public function __construct(
        public readonly string  $type,
        public readonly string  $titre,
        public readonly string  $corps,
        public readonly string  $moduleSource,
        public readonly ?string $entiteType  = null,
        public readonly ?int    $entiteId    = null,
        public readonly ?string $urlAction   = null,
        public readonly string  $priorite    = 'normale',
        public readonly ?string $expireAt    = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            type:         $data['type']         ?? 'info',
            titre:        trim($data['titre']   ?? ''),
            corps:        trim($data['corps']   ?? ''),
            moduleSource: $data['module_source'] ?? 'communication',
            entiteType:   $data['entite_type']  ?? null,
            entiteId:     isset($data['entite_id']) ? (int) $data['entite_id'] : null,
            urlAction:    $data['url_action']   ?? null,
            priorite:     $data['priorite']     ?? 'normale',
            expireAt:     $data['expire_at']    ?? null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->titre)) {
            $errors[] = 'Le titre est requis.';
        }
        if (!in_array($this->priorite, ['basse', 'normale', 'haute', 'critique'], true)) {
            $errors[] = 'Priorité invalide.';
        }
        return $errors;
    }
}
