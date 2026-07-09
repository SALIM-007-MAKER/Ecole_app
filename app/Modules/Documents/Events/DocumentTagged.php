<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentTagged extends Event
{
    public function __construct(
        public readonly int   $documentId,
        public readonly array $tagIdsAdded,
        public readonly array $tagIdsRemoved,
        public readonly int   $taggedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id'    => $this->documentId,
            'tags_added'     => $this->tagIdsAdded,
            'tags_removed'   => $this->tagIdsRemoved,
            'tagged_by'      => $this->taggedById,
        ];
    }
}
