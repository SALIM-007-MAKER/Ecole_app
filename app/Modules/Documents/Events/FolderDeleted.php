<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class FolderDeleted extends Event
{
    public function __construct(
        public readonly int    $folderId,
        public readonly string $moduleSource,
        public readonly int    $deletedById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'folder_id'     => $this->folderId,
            'module_source' => $this->moduleSource,
            'deleted_by'    => $this->deletedById,
        ];
    }
}
