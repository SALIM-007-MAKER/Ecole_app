<?php
declare(strict_types=1);

namespace App\Shared\Analytics;

class ChartEngine
{
    private const PALETTE_VIOLET = [
        '#7c3aed','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe',
        '#4f46e5','#6366f1','#818cf8','#93c5fd','#38bdf8',
    ];

    public function line(array $labels, array $datasets, array $options = []): array
    {
        return $this->build('line', $labels, $datasets, $options);
    }

    public function bar(array $labels, array $datasets, array $options = []): array
    {
        return $this->build('bar', $labels, $datasets, $options);
    }

    public function horizontalBar(array $labels, array $datasets, array $options = []): array
    {
        return $this->build('bar', $labels, $datasets, array_merge($options, ['indexAxis' => 'y']));
    }

    public function doughnut(array $labels, array $values, array $options = []): array
    {
        return [
            'type' => 'doughnut',
            'data' => [
                'labels'   => $labels,
                'datasets' => [[
                    'data'            => $values,
                    'backgroundColor' => array_slice(self::PALETTE_VIOLET, 0, count($labels)),
                    'borderWidth'     => 2,
                    'borderColor'     => '#fff',
                ]],
            ],
            'options' => array_merge([
                'responsive'        => true,
                'maintainAspectRatio' => false,
                'plugins'           => [
                    'legend' => ['position' => 'bottom'],
                    'title'  => ['display' => isset($options['titre']), 'text' => $options['titre'] ?? ''],
                ],
            ], $options),
        ];
    }

    public function radar(array $labels, array $datasets, array $options = []): array
    {
        return $this->build('radar', $labels, $datasets, $options);
    }

    public function serieMensuelle(array $rows, string $dateKey, string $valueKey): array
    {
        $map = [];
        foreach ($rows as $row) {
            $mois = substr((string)($row[$dateKey] ?? ''), 0, 7);
            if (!$mois) continue;
            $map[$mois] = ($map[$mois] ?? 0.0) + (float)($row[$valueKey] ?? 0);
        }
        ksort($map);
        return [
            'labels' => array_keys($map),
            'values' => array_values($map),
        ];
    }

    public function serieJournaliere(array $rows, string $dateKey, string $valueKey): array
    {
        $map = [];
        foreach ($rows as $row) {
            $jour = substr((string)($row[$dateKey] ?? ''), 0, 10);
            if (!$jour) continue;
            $map[$jour] = ($map[$jour] ?? 0.0) + (float)($row[$valueKey] ?? 0);
        }
        ksort($map);
        return [
            'labels' => array_keys($map),
            'values' => array_values($map),
        ];
    }

    private function build(string $type, array $labels, array $datasets, array $options): array
    {
        $formattedDatasets = [];
        foreach ($datasets as $i => $ds) {
            $color = self::PALETTE_VIOLET[$i % count(self::PALETTE_VIOLET)];
            $formattedDatasets[] = array_merge([
                'borderColor'     => $color,
                'backgroundColor' => $type === 'line' ? $color . '22' : $color,
                'borderWidth'     => 2,
                'tension'         => 0.4,
                'fill'            => $type === 'line',
            ], $ds);
        }
        return [
            'type' => $type,
            'data' => [
                'labels'   => $labels,
                'datasets' => $formattedDatasets,
            ],
            'options' => array_merge([
                'responsive'        => true,
                'maintainAspectRatio' => false,
                'indexAxis'         => $options['indexAxis'] ?? 'x',
                'plugins' => [
                    'legend' => ['display' => count($datasets) > 1],
                    'title'  => ['display' => isset($options['titre']), 'text' => $options['titre'] ?? ''],
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                ],
            ], $options),
        ];
    }
}
