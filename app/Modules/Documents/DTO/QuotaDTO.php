<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTO;

class QuotaDTO
{
    public function __construct(
        public readonly string $moduleSource,
        public readonly int    $quotaOctets,
        public readonly int    $utilisOctets,
        public readonly float  $pourcentage,
        public readonly bool   $illimite,
    ) {}

    public function octetsDisponibles(): int
    {
        if ($this->illimite) return PHP_INT_MAX;
        return max(0, $this->quotaOctets - $this->utilisOctets);
    }

    public static function fromRow(array $row): self
    {
        $quota  = (int)$row['quota_octets'];
        $utilise = (int)$row['utilise_octets'];
        $illimite = $quota === 0;
        $pct = $illimite ? 0 : ($quota > 0 ? round(($utilise / $quota) * 100, 1) : 0);

        return new self(
            moduleSource:  $row['module_source'],
            quotaOctets:   $quota,
            utilisOctets:  $utilise,
            pourcentage:   $pct,
            illimite:      $illimite,
        );
    }

    public static function formatOctets(int $octets): string
    {
        if ($octets >= 1073741824) return round($octets / 1073741824, 1) . ' Go';
        if ($octets >= 1048576)    return round($octets / 1048576, 1) . ' Mo';
        if ($octets >= 1024)       return round($octets / 1024, 1) . ' Ko';
        return $octets . ' o';
    }
}
