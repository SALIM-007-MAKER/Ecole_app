<?php
declare(strict_types=1);

namespace App\Shared\Analytics;

class KPIEngine
{
    public function variation(float $ancien, float $nouveau): float
    {
        if ($ancien == 0.0) {
            return $nouveau > 0.0 ? 100.0 : 0.0;
        }
        return round((($nouveau - $ancien) / abs($ancien)) * 100, 2);
    }

    public function tendance(array $series): string
    {
        if (count($series) < 2) {
            return 'stable';
        }
        $first = (float)reset($series);
        $last  = (float)end($series);
        $delta = $this->variation($first, $last);
        if ($delta > 2.0)  return 'hausse';
        if ($delta < -2.0) return 'baisse';
        return 'stable';
    }

    public function moyenneGlissante(array $values, int $fenetre = 3): array
    {
        $result = [];
        foreach ($values as $i => $v) {
            $start    = max(0, $i - $fenetre + 1);
            $slice    = array_slice($values, $start, $i - $start + 1);
            $result[] = count($slice) > 0 ? array_sum($slice) / count($slice) : 0.0;
        }
        return $result;
    }

    public function tauxRealisation(float $objectif, float $realise): float
    {
        if ($objectif == 0.0) return 0.0;
        return round(min(($realise / $objectif) * 100, 100), 2);
    }

    public function scoreComposite(array $kpis, array $poids): float
    {
        if (count($kpis) !== count($poids) || array_sum($poids) == 0.0) {
            return 0.0;
        }
        $score = 0.0;
        foreach ($kpis as $i => $v) {
            $score += (float)$v * (float)($poids[$i] ?? 0.0);
        }
        return round($score / array_sum($poids), 2);
    }

    public function alerteSeuil(float $valeur, float $seuil, string $direction = 'below'): bool
    {
        return $direction === 'below' ? $valeur < $seuil : $valeur > $seuil;
    }

    public function formatKpi(float $valeur, string $unite = ''): string
    {
        if (abs($valeur) >= 1_000_000) {
            return number_format($valeur / 1_000_000, 1) . 'M' . ($unite ? ' ' . $unite : '');
        }
        if (abs($valeur) >= 1_000) {
            return number_format($valeur / 1_000, 1) . 'k' . ($unite ? ' ' . $unite : '');
        }
        return number_format($valeur, 2) . ($unite ? ' ' . $unite : '');
    }
}
