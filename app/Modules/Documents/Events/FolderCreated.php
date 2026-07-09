<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class FolderCreated extends Event
{
    public function __construct(
        public readonly int    $folderId,
        public readonly ?int   $parentId,
        public readonly string $moduleSource,
        public readonly int    $createdById,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'folder_id'     => $this->folderId,
            'parent_id'     => $this->parentId,
            'module_source' => $this->moduleSource,
            'created_by'    => $this->createdById,
        ];
    }
}
