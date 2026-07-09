<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentTrashed extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly string $moduleSource,
        public readonly int    $deletedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'   => $this->documentId,
            'module_source' => $this->moduleSource,
            'deleted_by'    => $this->deletedById,
        ];
    }
}
