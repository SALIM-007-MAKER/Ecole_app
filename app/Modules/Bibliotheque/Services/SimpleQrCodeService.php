<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\Contracts\QrCodeInterface;

class SimpleQrCodeService implements QrCodeInterface
{
    public function generateSvg(string $data, int $size = 200): string
    {
        // Stub V2 — génère un QR visuel simplifié basé sur le hash des données
        $hash  = md5($data);
        $cells = 10;
        $cell  = (int)($size / ($cells + 2));
        $rects = '';
        for ($r = 0; $r < $cells; $r++) {
            for ($c = 0; $c < $cells; $c++) {
                $bit = hexdec($hash[($r * $cells + $c) % 32]) % 2;
                if ($bit) {
                    $x = ($c + 1) * $cell;
                    $y = ($r + 1) * $cell;
                    $rects .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="black"/>', $x, $y, $cell, $cell);
                }
            }
        }
        // Finder pattern corners
        $fp = static fn(int $x, int $y, int $s) => sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" fill="black"/><rect x="%d" y="%d" width="%d" height="%d" fill="white"/>',
            $x, $y, $s, $s, $x + $cell, $y + $cell, $s - 2 * $cell, $s - 2 * $cell
        );
        $rects .= $fp($cell, $cell, 3 * $cell);
        $rects .= $fp(($cells - 2) * $cell, $cell, 3 * $cell);
        $rects .= $fp($cell, ($cells - 2) * $cell, 3 * $cell);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '<rect width="%d" height="%d" fill="white"/>%s</svg>',
            $size, $size, $size, $size, $size, $size, $rects
        );
    }

    public function disponible(): bool
    {
        return true;
    }
}
