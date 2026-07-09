<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

use App\Modules\Documents\Models\ShareModel;

class ShareDTO
{
    public function __construct(
        public readonly int     $documentId,
        public readonly string  $destinataireType,
        public readonly ?int    $destinataireId    = null,
        public readonly ?string $destinataireEmail = null,
        public readonly string  $permission        = 'lecture',
        public readonly ?string $dateExpiration    = null,
        public readonly bool    $notifier          = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            documentId:        (int)($data['document_id'] ?? 0),
            destinataireType:  trim($data['destinataire_type'] ?? ''),
            destinataireId:    ($v = ($data['destinataire_id'] ?? '')) !== '' ? (int)$v : null,
            destinataireEmail: ($v = trim($data['destinataire_email'] ?? '')) !== '' ? $v : null,
            permission:        trim($data['permission'] ?? 'lecture'),
            dateExpiration:    ($v = trim($data['date_expiration'] ?? '')) !== '' ? $v : null,
            notifier:          !isset($data['notifier']) || !empty($data['notifier']),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->documentId <= 0) {
            $errors['document_id'] = 'Document requis.';
        }
        if (!in_array($this->destinataireType, ShareModel::DESTINATAIRE_TYPES, true)) {
            $errors['destinataire_type'] = 'Type de destinataire invalide.';
        }
        if ($this->destinataireType === 'externe' && empty($this->destinataireEmail)) {
            $errors['destinataire_email'] = 'Email requis pour un partage externe.';
        }
        if (!in_array($this->permission, ShareModel::PERMISSIONS, true)) {
            $errors['permission'] = 'Permission invalide.';
        }
        return $errors;
    }
}
