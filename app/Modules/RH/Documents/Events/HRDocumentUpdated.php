<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Events;

use Core\Event;

class HRDocumentUpdated extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly int    $employeId,
        public readonly int    $version,
        public readonly string $action,
        public readonly int    $updatedBy,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'employe_id'  => $this->employeId,
            'version'     => $this->version,
            'action'      => $this->action,
            'updated_by'  => $this->updatedBy,
        ];
    }
}
