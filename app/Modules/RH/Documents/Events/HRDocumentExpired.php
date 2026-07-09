<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Events;

use Core\Event;

class HRDocumentExpired extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly int    $employeId,
        public readonly string $type,
        public readonly string $dateExpiration,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'document_id'      => $this->documentId,
            'employe_id'       => $this->employeId,
            'type'             => $this->type,
            'date_expiration'  => $this->dateExpiration,
        ];
    }
}
