<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Events;

use Core\Event;

class HRDocumentArchived extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly int    $employeId,
        public readonly string $type,
        public readonly string $ancienStatut,
        public readonly int    $archivedBy,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'document_id'   => $this->documentId,
            'employe_id'    => $this->employeId,
            'type'          => $this->type,
            'ancien_statut' => $this->ancienStatut,
            'archived_by'   => $this->archivedBy,
        ];
    }
}
