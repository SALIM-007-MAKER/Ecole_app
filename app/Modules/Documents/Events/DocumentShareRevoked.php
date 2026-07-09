<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentShareRevoked extends Event
{
    public function __construct(
        public readonly int $documentId,
        public readonly int $partageId,
        public readonly int $revokedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'partage_id'  => $this->partageId,
            'revoked_by'  => $this->revokedById,
        ];
    }
}
