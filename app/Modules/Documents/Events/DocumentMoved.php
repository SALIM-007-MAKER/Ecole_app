<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class DocumentMoved extends Event
{
    public function __construct(
        public readonly int  $documentId,
        public readonly ?int $oldFolderId,
        public readonly ?int $newFolderId,
        public readonly int  $movedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'old_folder'  => $this->oldFolderId,
            'new_folder'  => $this->newFolderId,
            'moved_by'    => $this->movedById,
        ];
    }
}
