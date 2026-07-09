<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\Contracts\BarcodeInterface;

class SimpleBarcodeService implements BarcodeInterface
{
    public function generateSvg(string $code, string $type = 'C128'): string
    {
        $bars   = '';
        $width  = 0;
        $x      = 10;
        foreach (str_split($code) as $char) {
            $ord   = ord($char);
            $barW  = ($ord % 3) + 1;
            $gapW  = ($ord % 2) + 1;
            $bars .= sprintf('<rect x="%d" y="10" width="%d" height="50" fill="black"/>', $x, $barW);
            $x    += $barW + $gapW;
            $width = $x;
        }
        $totalW = $width + 10;
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="80" viewBox="0 0 %d 80">'
            . '<rect width="%d" height="80" fill="white"/>%s'
            . '<text x="%d" y="75" font-size="10" text-anchor="middle" font-family="monospace">%s</text>'
            . '</svg>',
            $totalW, $totalW, $totalW, $bars, $totalW / 2,
            htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
        );
    }

    public function disponible(): bool
    {
        return true;
    }
}
