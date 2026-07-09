<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Contracts;

interface QrCodeInterface
{
    public function generateSvg(string $data, int $size = 200): string;
    public function disponible(): bool;
}
