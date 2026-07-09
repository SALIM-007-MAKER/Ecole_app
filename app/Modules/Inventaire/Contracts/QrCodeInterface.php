<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

interface QrCodeInterface
{
    public function generate(string $data, int $size = 200): string;
    public function toDataUri(string $data, int $size = 200): string;
}
