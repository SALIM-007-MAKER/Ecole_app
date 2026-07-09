<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentPurged extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly string $moduleSource,
        public readonly string $cheminSupprime,
        public readonly int    $purgedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'    => $this->documentId,
            'module_source'  => $this->moduleSource,
            'chemin_supprime'=> $this->cheminSupprime,
            'purged_by'      => $this->purgedById,
        ];
    }
}
