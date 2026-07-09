<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Contracts;

interface BarcodeInterface
{
    public function generateSvg(string $code, string $type = 'C128'): string;
    public function disponible(): bool;
}
