<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentRestored extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly string $moduleSource,
        public readonly string $ancienStatut,
        public readonly string $nouveauStatut,
        public readonly int    $restoredById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'    => $this->documentId,
            'module_source'  => $this->moduleSource,
            'ancien_statut'  => $this->ancienStatut,
            'nouveau_statut' => $this->nouveauStatut,
            'restored_by'    => $this->restoredById,
        ];
    }
}
