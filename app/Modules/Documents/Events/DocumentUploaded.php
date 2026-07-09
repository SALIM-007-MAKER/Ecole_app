<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentUploaded extends Event
{
    public function __construct(
        public readonly int     $documentId,
        public readonly string  $moduleSource,
        public readonly ?string $entiteType,
        public readonly ?int    $entiteId,
        public readonly string  $mimeType,
        public readonly int     $tailleOctets,
        public readonly int     $uploadedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'   => $this->documentId,
            'module_source' => $this->moduleSource,
            'entite_type'   => $this->entiteType,
            'entite_id'     => $this->entiteId,
            'mime_type'     => $this->mimeType,
            'taille_octets' => $this->tailleOctets,
            'uploaded_by'   => $this->uploadedById,
        ];
    }
}
