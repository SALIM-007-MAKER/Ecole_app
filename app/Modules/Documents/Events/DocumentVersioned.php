<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentVersioned extends Event
{
    public function __construct(
        public readonly int $documentId,
        public readonly int $oldVersion,
        public readonly int $newVersion,
        public readonly int $tailleOctets,
        public readonly int $updatedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'   => $this->documentId,
            'old_version'   => $this->oldVersion,
            'new_version'   => $this->newVersion,
            'taille_octets' => $this->tailleOctets,
            'updated_by'    => $this->updatedById,
        ];
    }
}
