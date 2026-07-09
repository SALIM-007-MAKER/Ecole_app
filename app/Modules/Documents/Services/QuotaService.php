<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\DTO\QuotaDTO;
use App\Modules\Documents\Repositories\QuotaRepository;

class QuotaService
{
    private QuotaRepository $repo;

    public function __construct()
    {
        $this->repo = new QuotaRepository();
    }

    public function verifier(string $moduleSource, int $tailleOctets, int $etablissementId = 1): void
    {
        $quota = $this->repo->findByModule($moduleSource, $etablissementId);
        if (!$quota || (int)$quota['quota_octets'] === 0) return; // 0 = illimité

        $disponible = (int)$quota['quota_octets'] - (int)$quota['utilise_octets'];
        if ($tailleOctets > $disponible) {
            throw new \OverflowException(
                "Quota dépassé pour le module $moduleSource. " .
                "Disponible : " . QuotaDTO::formatOctets($disponible) . ", " .
                "Requis : " . QuotaDTO::formatOctets($tailleOctets) . "."
            );
        }
    }

    public function incrementer(string $moduleSource, int $tailleOctets, int $etablissementId = 1): void
    {
        $this->repo->incrementer($moduleSource, $tailleOctets, $etablissementId);
    }

    public function decrementer(string $moduleSource, int $tailleOctets, int $etablissementId = 1): void
    {
        $this->repo->decrementer($moduleSource, $tailleOctets, $etablissementId);
    }

    public function rapport(string $moduleSource, int $etablissementId = 1): QuotaDTO
    {
        $row = $this->repo->findByModule($moduleSource, $etablissementId) ?? [
            'module_source'  => $moduleSource,
            'quota_octets'   => 0,
            'utilise_octets' => 0,
        ];
        return QuotaDTO::fromRow($row);
    }

    public function rapportGlobal(int $etablissementId = 1): array
    {
        return array_map(
            fn($r) => QuotaDTO::fromRow($r),
            $this->repo->findAll($etablissementId)
        );
    }

    public function definir(string $moduleSource, int $quotaOctets, int $etablissementId = 1): void
    {
        $this->repo->upsert($moduleSource, null, null, $quotaOctets, $etablissementId);
    }

    public function tableau(int $etablissementId = 1): array
    {
        return array_map(
            fn($r) => QuotaDTO::fromRow($r),
            $this->repo->findAll($etablissementId)
        );
    }
}
