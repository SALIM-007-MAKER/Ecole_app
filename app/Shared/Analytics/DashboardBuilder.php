<?php
declare(strict_types=1);

namespace App\Shared\Analytics;

class DashboardBuilder
{
    private array $widgets = [];
    private string $contexte = '';
    private array $meta = [];

    public function setContexte(string $contexte): self
    {
        $this->contexte = $contexte;
        return $this;
    }

    public function addKpi(string $id, string $label, float|int|string $valeur, string $unite = '', float|null $variation = null, string $tendance = 'stable'): self
    {
        $this->widgets[] = [
            'type'      => 'kpi',
            'id'        => $id,
            'label'     => $label,
            'valeur'    => $valeur,
            'unite'     => $unite,
            'variation' => $variation,
            'tendance'  => $tendance,
        ];
        return $this;
    }

    public function addChart(string $id, string $titre, array $chartData, string $taille = 'medium'): self
    {
        $this->widgets[] = [
            'type'   => 'chart',
            'id'     => $id,
            'titre'  => $titre,
            'data'   => $chartData,
            'taille' => $taille,
        ];
        return $this;
    }

    public function addTable(string $id, string $titre, array $rows, array $colonnes, int $limit = 10): self
    {
        $this->widgets[] = [
            'type'     => 'table',
            'id'       => $id,
            'titre'    => $titre,
            'colonnes' => $colonnes,
            'rows'     => array_slice($rows, 0, $limit),
            'total'    => count($rows),
        ];
        return $this;
    }

    public function addAlertes(string $id, array $alertes): self
    {
        $this->widgets[] = [
            'type'    => 'alertes',
            'id'      => $id,
            'alertes' => $alertes,
        ];
        return $this;
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function build(): array
    {
        return [
            'contexte'   => $this->contexte,
            'widgets'    => $this->widgets,
            'meta'       => array_merge([
                'genere_a' => date('Y-m-d H:i:s'),
                'periode'  => date('Y-m'),
            ], $this->meta),
        ];
    }

    public function applyUserConfig(array $config): self
    {
        if (empty($config['widgets'])) return $this;
        $orderedIds  = $config['widgets'];
        $byId        = array_column($this->widgets, null, 'id');
        $ordered     = [];
        foreach ($orderedIds as $item) {
            $wid = is_array($item) ? ($item['id'] ?? '') : $item;
            if (isset($byId[$wid])) {
                $w = $byId[$wid];
                if (is_array($item) && isset($item['visible']) && $item['visible'] === false) {
                    continue;
                }
                $ordered[] = $w;
                unset($byId[$wid]);
            }
        }
        foreach ($byId as $w) {
            $ordered[] = $w;
        }
        $this->widgets = $ordered;
        return $this;
    }
}
