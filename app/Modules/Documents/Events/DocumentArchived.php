<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentArchived extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly string $moduleSource,
        public readonly string $ancienStatut,
        public readonly int    $archivedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'   => $this->documentId,
            'module_source' => $this->moduleSource,
            'ancien_statut' => $this->ancienStatut,
            'archived_by'   => $this->archivedById,
        ];
    }
}
