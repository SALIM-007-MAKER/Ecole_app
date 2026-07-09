<?php
declare(strict_types=1);

namespace App\Modules\Rapports\DTO;

class DashboardMetricsDTO
{
    public function __construct(
        public readonly string $contexte,
        public readonly array  $kpis,
        public readonly array  $charts,
        public readonly array  $tables,
        public readonly array  $alertes,
        public readonly string $periodeLabel,
        public readonly string $genereA,
    ) {}

    public static function fromBuilder(array $built): self
    {
        $kpis    = [];
        $charts  = [];
        $tables  = [];
        $alertes = [];
        foreach ($built['widgets'] ?? [] as $w) {
            match ($w['type'] ?? '') {
                'kpi'     => $kpis[]    = $w,
                'chart'   => $charts[]  = $w,
                'table'   => $tables[]  = $w,
                'alertes' => $alertes[] = $w,
                default   => null,
            };
        }
        return new self(
            contexte:     $built['contexte']         ?? '',
            kpis:         $kpis,
            charts:       $charts,
            tables:       $tables,
            alertes:      $alertes,
            periodeLabel: $built['meta']['periode']  ?? date('Y-m'),
            genereA:      $built['meta']['genere_a'] ?? date('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'contexte'      => $this->contexte,
            'kpis'          => $this->kpis,
            'charts'        => $this->charts,
            'tables'        => $this->tables,
            'alertes'       => $this->alertes,
            'periode_label' => $this->periodeLabel,
            'genere_a'      => $this->genereA,
        ];
    }
}
