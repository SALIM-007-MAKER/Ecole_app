<?php

declare(strict_types=1);

namespace Core\QrCode;

/**
 * Encodeur QR Code minimal, sans dépendance externe (aucun Composer/CDN
 * disponible dans ce projet — voir Bulletin V1, QR de vérification).
 *
 * Portée volontairement réduite mais 100% conforme ISO/IEC 18004:2015:
 *   - Mode Byte uniquement (suffisant pour une URL de vérification)
 *   - Niveau de correction d'erreur L (capacité max)
 *   - Versions 1 à 10 (jusqu'à 174 octets en Byte/L)
 *   - Masque fixe n°0 ((row+col) % 2 == 0) — un masque fixe correctement
 *     implémenté reste pleinement conforme et scannable ; seule
 *     l'optimisation esthétique de la densité de modules est sautée.
 *
 * Algorithme de correction d'erreur (Reed-Solomon sur GF(256)) porté selon
 * le schéma de référence largement utilisé et vérifié de Project Nayuki
 * « QR Code generator library » (domaine public), adapté en PHP.
 */
final class QrEncoder
{
    private const MASK_PATTERN = 0;
    private const GF_PRIME = 0x11D;

    /** version => ['ec' => codewords EC par bloc, 'groups' => [[nbBlocs, dataParBloc], ...]] */
    private const BLOCKS = [
        1  => ['ec' => 7,  'groups' => [[1, 19]]],
        2  => ['ec' => 10, 'groups' => [[1, 34]]],
        3  => ['ec' => 15, 'groups' => [[1, 55]]],
        4  => ['ec' => 20, 'groups' => [[1, 80]]],
        5  => ['ec' => 26, 'groups' => [[1, 108]]],
        6  => ['ec' => 18, 'groups' => [[2, 68]]],
        7  => ['ec' => 20, 'groups' => [[2, 78]]],
        8  => ['ec' => 24, 'groups' => [[2, 97]]],
        9  => ['ec' => 30, 'groups' => [[2, 116]]],
        10 => ['ec' => 18, 'groups' => [[2, 68], [2, 69]]],
    ];

    private const REMAINDER_BITS = [
        1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7,
        7 => 0, 8 => 0, 9 => 0, 10 => 0,
    ];

    private const ALIGNMENT_CENTERS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** @var bool[][] */
    private array $modules = [];
    /** @var bool[][] */
    private array $isFunction = [];
    private int $size = 0;

    /** Génère un <svg> autonome (carrés noir/blanc) représentant le QR code de $data. */
    public function generateSvg(string $data, int $moduleSizePx = 4): string
    {
        $matrix = $this->encodeMatrix($data);
        $size   = count($matrix);
        $px     = $size * $moduleSizePx;

        $rects = '';
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix[$row][$col]) {
                    $rects .= sprintf(
                        '<rect x="%d" y="%d" width="%d" height="%d"/>',
                        $col * $moduleSizePx, $row * $moduleSizePx, $moduleSizePx, $moduleSizePx
                    );
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges">'
            . '<rect width="%d" height="%d" fill="#fff"/><g fill="#000">%s</g></svg>',
            $px, $px, $px, $px, $px, $px, $rects
        );
    }

    /** @return bool[][] matrice size x size, true = module noir. */
    public function encodeMatrix(string $data): array
    {
        $version = $this->selectVersion(strlen($data));

        $bitStream       = $this->buildBitStream($data, $version);
        $dataCodewords   = $this->bitsToBytes($bitStream);
        $finalCodewords  = $this->interleave($dataCodewords, $version);
        $bitsFinal        = $this->bytesToBits($finalCodewords);
        for ($i = 0, $n = self::REMAINDER_BITS[$version]; $i < $n; $i++) {
            $bitsFinal[] = 0;
        }

        return $this->buildMatrix($bitsFinal, $version);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Sélection de version
    // ─────────────────────────────────────────────────────────────────

    private function selectVersion(int $byteLength): int
    {
        for ($v = 1; $v <= 10; $v++) {
            $countBits    = $v <= 9 ? 8 : 16;
            $headerBits   = 4 + $countBits;
            $capacityBits = $this->totalDataCodewords($v) * 8;
            $neededBits   = $headerBits + $byteLength * 8;
            if ($neededBits <= $capacityBits && $byteLength < (1 << $countBits)) {
                return $v;
            }
        }
        throw new \RuntimeException(
            "Donnée trop longue pour QrEncoder (max ~174 octets, EC=L, versions 1-10)."
        );
    }

    private function totalDataCodewords(int $version): int
    {
        $total = 0;
        foreach (self::BLOCKS[$version]['groups'] as [$count, $dataLen]) {
            $total += $count * $dataLen;
        }
        return $total;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Flux de bits — mode Byte (ISO/IEC 18004 §7.4.4)
    // ─────────────────────────────────────────────────────────────────

    /** @return int[] tableau de bits (0/1) */
    private function buildBitStream(string $data, int $version): array
    {
        $bits = [];
        $this->appendBits($bits, 0b0100, 4); // indicateur de mode : Byte

        $countBits = $version <= 9 ? 8 : 16;
        $this->appendBits($bits, strlen($data), $countBits);

        foreach (str_split($data) as $ch) {
            $this->appendBits($bits, ord($ch), 8);
        }

        $capacityBits = $this->totalDataCodewords($version) * 8;

        // Terminateur (jusqu'à 4 bits à 0)
        $terminator = max(0, min(4, $capacityBits - count($bits)));
        for ($i = 0; $i < $terminator; $i++) $bits[] = 0;

        // Complément au multiple de 8
        while (count($bits) % 8 !== 0) $bits[] = 0;

        // Octets de bourrage 0xEC / 0x11 alternés
        $pad = [0xEC, 0x11];
        $i = 0;
        while (count($bits) < $capacityBits) {
            $this->appendBits($bits, $pad[$i % 2], 8);
            $i++;
        }

        return $bits;
    }

    private function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function bitsToBytes(array $bits): array
    {
        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | ($bits[$i + $j] ?? 0);
            }
            $bytes[] = $byte;
        }
        return $bytes;
    }

    private function bytesToBits(array $bytes): array
    {
        $bits = [];
        foreach ($bytes as $byte) {
            $this->appendBits($bits, $byte, 8);
        }
        return $bits;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Reed-Solomon sur GF(256) — ISO/IEC 18004 §7.5
    // ─────────────────────────────────────────────────────────────────

    /** Multiplication dans GF(256), polynôme primitif x^8+x^4+x^3+x^2+1 (0x11D). */
    private function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $carry = ($z >> 7) & 1;
            $z = (($z << 1) & 0xFF) ^ ($carry * (self::GF_PRIME & 0xFF));
            $z ^= ((($y >> $i) & 1) * $x) & 0xFF;
            $z &= 0xFF;
        }
        return $z;
    }

    /** Polynôme diviseur (générateur) RS de degré $degree, coefficients degré fort → faible. */
    private function generatorPolynomial(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;

        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = $this->gfMultiply($root, 0x02);
        }
        return $result;
    }

    /** @return int[] codewords EC (longueur = count($divisor)) pour un bloc de données. */
    private function rsComputeRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = ($b ^ $result[0]) & 0xFF;
            array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $d) {
                $result[$i] ^= $this->gfMultiply($d, $factor);
            }
        }
        return $result;
    }

    /**
     * Découpe les codewords de données en blocs, calcule les EC par bloc,
     * puis entrelace (ISO/IEC 18004 §8.6) — bloc de données d'abord
     * (colonne par colonne, les blocs les plus courts épuisés en premier),
     * puis EC (toujours de même longueur entre blocs).
     */
    private function interleave(array $dataCodewords, int $version): array
    {
        $groups = self::BLOCKS[$version]['groups'];
        $ecLen  = self::BLOCKS[$version]['ec'];

        $blocks = [];
        $offset = 0;
        foreach ($groups as [$count, $dataLen]) {
            for ($b = 0; $b < $count; $b++) {
                $blocks[] = array_slice($dataCodewords, $offset, $dataLen);
                $offset += $dataLen;
            }
        }

        $divisor  = $this->generatorPolynomial($ecLen);
        $ecBlocks = array_map(fn(array $block) => $this->rsComputeRemainder($block, $divisor), $blocks);

        $result     = [];
        $maxDataLen = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block)) $result[] = $block[$i];
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $ecBlock) {
                $result[] = $ecBlock[$i];
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Construction de la matrice (ISO/IEC 18004 §6.3, §7.7-7.9)
    // ─────────────────────────────────────────────────────────────────

    /** @return bool[][] */
    private function buildMatrix(array $dataBits, int $version): array
    {
        $this->size       = 17 + 4 * $version;
        $this->modules    = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));

        $this->drawTimingPatterns();
        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);
        $this->drawAlignmentPatterns($version);

        // Réserve les zones format/version (valeurs réelles dessinées après masquage)
        $this->drawFormatBits(0);
        if ($version >= 7) {
            $this->drawVersionBits($version);
        }

        $this->drawCodewords($dataBits);
        $this->applyMask();

        // Bits réels finaux (non masqués, dessinés en dernier)
        $this->drawFormatBits($this->computeFormatBits());
        if ($version >= 7) {
            $this->drawVersionBits($version);
        }

        return $this->modules;
    }

    private function setFunctionModule(int $col, int $row, bool $black): void
    {
        $this->modules[$row][$col]    = $black;
        $this->isFunction[$row][$col] = true;
    }

    private function drawTimingPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }
    }

    /** Motif de repérage 7x7 + séparateur, centré en ($centerCol, $centerRow), rogné aux bords de la grille. */
    private function drawFinderPattern(int $centerCol, int $centerRow): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $col  = $centerCol + $dx;
                $row  = $centerRow + $dy;
                if ($col >= 0 && $col < $this->size && $row >= 0 && $row < $this->size) {
                    $this->setFunctionModule($col, $row, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignmentPatterns(int $version): void
    {
        $positions = self::ALIGNMENT_CENTERS[$version];
        $n = count($positions);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                // Coins déjà occupés par les motifs de repérage — à sauter.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignmentPattern($positions[$i], $positions[$j]);
            }
        }
    }

    private function drawAlignmentPattern(int $centerCol, int $centerRow): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule(
                    $centerCol + $dx,
                    $centerRow + $dy,
                    max(abs($dx), abs($dy)) !== 1
                );
            }
        }
    }

    /** Bits d'information de format : 2 bits niveau EC (L=01) + 3 bits masque, + BCH(15,5), masqués par 0x5412. */
    private function computeFormatBits(): int
    {
        $eccBits = 0b01; // niveau L
        $data    = ($eccBits << 3) | self::MASK_PATTERN;

        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }

        return ((($data << 10) | $rem) ^ 0x5412) & 0x7FFF;
    }

    private function drawFormatBits(int $bits): void
    {
        $bit = fn(int $i): bool => (($bits >> $i) & 1) !== 0;

        for ($i = 0; $i <= 5; $i++)  $this->setFunctionModule(8, $i, $bit($i));
        $this->setFunctionModule(8, 7, $bit(6));
        $this->setFunctionModule(8, 8, $bit(7));
        $this->setFunctionModule(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) $this->setFunctionModule(14 - $i, 8, $bit($i));

        for ($i = 0; $i < 8; $i++)  $this->setFunctionModule($this->size - 1 - $i, 8, $bit($i));
        for ($i = 8; $i < 15; $i++) $this->setFunctionModule(8, $this->size - 15 + $i, $bit($i));
        $this->setFunctionModule(8, $this->size - 8, true); // module toujours noir
    }

    /** Bits d'information de version (versions >= 7 uniquement) : 6 bits + BCH(18,6), non masqués. */
    private function drawVersionBits(int $version): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) !== 0;
            $a = $this->size - 11 + ($i % 3);
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    /** Place les bits de données/EC en zigzag (ISO/IEC 18004 §7.7.3), colonnes de 2 de droite à gauche. */
    private function drawCodewords(array $bits): void
    {
        $bitIndex  = 0;
        $totalBits = count($bits);

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) $right = 5;
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $col     = $right - $j;
                    $upward  = ((($right + 1) & 2) === 0);
                    $row     = $upward ? ($this->size - 1 - $vert) : $vert;
                    if (!$this->isFunction[$row][$col]) {
                        $bit = $bitIndex < $totalBits && $bits[$bitIndex] ? true : false;
                        $this->modules[$row][$col] = $bit;
                        $bitIndex++;
                    }
                }
            }
        }
    }

    /** Masque fixe n°0 : (row+col) % 2 == 0 — appliqué uniquement aux modules hors motifs de fonction. */
    private function applyMask(): void
    {
        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if (!$this->isFunction[$row][$col] && (($row + $col) % 2 === 0)) {
                    $this->modules[$row][$col] = !$this->modules[$row][$col];
                }
            }
        }
    }
}
