<?php

namespace App\Events;

use Core\Event;

class ImportCsvCompleted extends Event
{
    public function __construct(
        public readonly int    $importedById,
        public readonly int    $totalImported,
        public readonly int    $totalSkipped,
        public readonly string $filename,
        public readonly int    $classeId   = 0,
        public readonly array  $errors     = [],
    ) {
        parent::__construct();
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'total_imported' => $this->totalImported,
            'total_skipped'  => $this->totalSkipped,
            'filename'       => $this->filename,
            'classe_id'      => $this->classeId,
            'errors_count'   => count($this->errors),
        ];
    }
}
