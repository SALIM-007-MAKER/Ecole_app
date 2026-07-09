<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentSignatureRequested extends Event
{
    public function __construct(
        public readonly int   $documentId,
        public readonly array $signataires,
        public readonly int   $requestedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'  => $this->documentId,
            'signataires'  => $this->signataires,
            'requested_by' => $this->requestedById,
        ];
    }
}
