<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentSigned extends Event
{
    public function __construct(
        public readonly int    $documentId,
        public readonly int    $signataireId,
        public readonly string $signedAt,
        public readonly float  $completionPercent,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'        => $this->documentId,
            'signataire_id'      => $this->signataireId,
            'signed_at'          => $this->signedAt,
            'completion_percent' => $this->completionPercent,
        ];
    }
}
