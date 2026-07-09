<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

interface BarcodeInterface
{
    public function generate(string $code, string $format = 'CODE128'): string;
    public function toDataUri(string $code, string $format = 'CODE128'): string;
    public function decode(string $imageData): ?string;
}
