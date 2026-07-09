<?php
declare(strict_types=1);

namespace App\Shared\Analytics;

class ReportEngine
{
    public function buildTableau(array $rows, array $colonnes, array $options = []): array
    {
        $titre      = $options['titre']      ?? 'Rapport';
        $periode    = $options['periode']    ?? date('Y-m');
        $totaux     = $options['totaux']     ?? [];
        $lignes     = [];
        foreach ($rows as $row) {
            $ligne = [];
            foreach ($colonnes as $col) {
                $ligne[$col['key']] = $row[$col['key']] ?? null;
            }
            $lignes[] = $ligne;
        }
        $footer = [];
        foreach ($totaux as $key => $fn) {
            $col   = array_column($rows, $key);
            $footer[$key] = match ($fn) {
                'sum'   => array_sum($col),
                'avg'   => count($col) > 0 ? array_sum($col) / count($col) : 0,
                'count' => count($col),
                default => null,
            };
        }
        return [
            'titre'    => $titre,
            'periode'  => $periode,
            'colonnes' => $colonnes,
            'lignes'   => $lignes,
            'footer'   => $footer,
            'nb_total' => count($lignes),
            'genere_a' => date('Y-m-d H:i:s'),
        ];
    }

    public function agreger(array $rows, string $groupKey, string $valueKey, string $fn = 'sum'): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $g = $row[$groupKey] ?? 'N/A';
            if (!isset($groups[$g])) $groups[$g] = [];
            $groups[$g][] = (float)($row[$valueKey] ?? 0);
        }
        $result = [];
        foreach ($groups as $g => $values) {
            $result[] = [
                $groupKey  => $g,
                $valueKey  => match ($fn) {
                    'sum'   => array_sum($values),
                    'avg'   => count($values) > 0 ? array_sum($values) / count($values) : 0.0,
                    'count' => count($values),
                    'min'   => min($values),
                    'max'   => max($values),
                    default => array_sum($values),
                },
            ];
        }
        usort($result, fn($a, $b) => $b[$valueKey] <=> $a[$valueKey]);
        return $result;
    }

    public function top(array $rows, int $n, string $valueKey = 'valeur'): array
    {
        usort($rows, fn($a, $b) => (float)($b[$valueKey] ?? 0) <=> (float)($a[$valueKey] ?? 0));
        return array_slice($rows, 0, $n);
    }

    public function distribuerParTranche(array $values, array $tranches): array
    {
        $dist = [];
        foreach ($tranches as $label => $range) {
            $dist[$label] = count(array_filter(
                $values,
                fn($v) => $v >= $range[0] && $v < $range[1]
            ));
        }
        return $dist;
    }

    public function csvEncode(array $rows, array $colonnes): string
    {
        $lines = [];
        $headers = array_map(fn($c) => '"' . str_replace('"', '""', $c['label'] ?? $c['key']) . '"', $colonnes);
        $lines[] = implode(';', $headers);
        foreach ($rows as $row) {
            $cells = [];
            foreach ($colonnes as $col) {
                $v = $row[$col['key']] ?? '';
                $cells[] = '"' . str_replace('"', '""', (string)$v) . '"';
            }
            $lines[] = implode(';', $cells);
        }
        return implode("\n", $lines);
    }
}
