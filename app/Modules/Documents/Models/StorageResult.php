<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

class StorageResult
{
    public function __construct(
        public readonly string $chemin,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int    $tailleOctets,
        public readonly string $checksumSha256,
    ) {}
}
