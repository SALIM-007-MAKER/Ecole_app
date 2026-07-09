<?php
declare(strict_types=1);

namespace App\Modules\Rapports\DTO;

class KpiDTO
{
    public function __construct(
        public readonly string       $domaine,
        public readonly string       $metrique,
        public readonly float|int    $valeur,
        public readonly string       $unite,
        public readonly float|null   $variation,
        public readonly string       $tendance,
        public readonly string       $periode,
        public readonly array        $serie     = [],
    ) {}

    public static function make(
        string     $domaine,
        string     $metrique,
        float|int  $valeur,
        string     $unite     = '',
        ?float     $variation = null,
        string     $tendance  = 'stable',
        string     $periode   = '',
        array      $serie     = [],
    ): self {
        return new self(
            domaine:   $domaine,
            metrique:  $metrique,
            valeur:    $valeur,
            unite:     $unite,
            variation: $variation,
            tendance:  $tendance,
            periode:   $periode ?: date('Y-m'),
            serie:     $serie,
        );
    }

    public function toArray(): array
    {
        return [
            'domaine'   => $this->domaine,
            'metrique'  => $this->metrique,
            'valeur'    => $this->valeur,
            'unite'     => $this->unite,
            'variation' => $this->variation,
            'tendance'  => $this->tendance,
            'periode'   => $this->periode,
            'serie'     => $this->serie,
        ];
    }
}
