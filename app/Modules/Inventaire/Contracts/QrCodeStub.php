<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

/** Stub V2 — implémentation réelle (e.g. endroid/qr-code) prévue V3 */
class QrCodeStub implements QrCodeInterface
{
    public function generate(string $data, int $size = 200): string
    {
        return base64_encode("QRCODE:{$size}:{$data}");
    }

    public function toDataUri(string $data, int $size = 200): string
    {
        return 'data:text/plain;base64,' . $this->generate($data, $size);
    }
}
