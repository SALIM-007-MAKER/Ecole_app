<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

interface AmortissementInterface
{
    public function calculerLineaire(float $valeurAchat, int $dureeMois, float $valeurResiduelle, int $moisEcoules): float;
    public function calculerDegressif(float $valeurAchat, int $dureeMois, float $valeurResiduelle, int $moisEcoules): float;
    public function tableauAmortissement(array $params): array;
}
