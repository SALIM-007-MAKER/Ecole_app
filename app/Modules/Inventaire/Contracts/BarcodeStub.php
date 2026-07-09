<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

/** Stub V2 — implémentation réelle avec library (e.g. picqer/php-barcode-generator) prévue V3 */
class BarcodeStub implements BarcodeInterface
{
    public function generate(string $code, string $format = 'CODE128'): string
    {
        return base64_encode("BARCODE:{$format}:{$code}");
    }

    public function toDataUri(string $code, string $format = 'CODE128'): string
    {
        return 'data:text/plain;base64,' . $this->generate($code, $format);
    }

    public function decode(string $imageData): ?string
    {
        return null;
    }
}
