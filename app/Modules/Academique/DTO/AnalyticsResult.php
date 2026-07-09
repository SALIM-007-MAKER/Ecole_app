<?php

namespace App\Modules\Academique\DTO;

/**
 * Résultat immuable d'un indicateur analytique.
 *
 * Un AnalyticsResult peut représenter :
 *   • une moyenne (type='moyenne', value=12.45)
 *   • un taux (type='taux_reussite', value=78.5)
 *   • un histogramme (type='distribution', breakdown=[...])
 *   • une évolution (value=current, previousValue=last periode)
 */
final class AnalyticsResult
{
    public function __construct(
        /** Identifiant du type d'indicateur. Ex: 'moyenne_classe', 'taux_reussite'. */
        public readonly string  $type,

        /** Libellé lisible. */
        public readonly string  $label,

        /** Valeur principale (moyenne, taux, etc.). */
        public readonly float   $value,

        /** Valeur de la période précédente (pour les tendances). */
        public readonly ?float  $previousValue,

        /** Taille de l'échantillon (nb élèves, nb notes…). */
        public readonly int     $count,

        /**
         * Décomposition détaillée. Ex pour 'distribution' :
         *   [{tranche, nb, pourcentage, color}]
         * Pour 'mentions':
         *   [{code, label, nb, pourcentage, css}]
         */
        public readonly array   $breakdown,

        /**
         * Contexte additionnel (periode_id, classe_id, niveau, matiere_id…).
         */
        public readonly array   $metadata,

        public readonly string  $computedAt,
    ) {}

    // ── Tendance ──────────────────────────────────────────────────────

    /** Delta absolu vs période précédente (en points). Null si pas de comparaison. */
    public function getEvolution(): ?float
    {
        if ($this->previousValue === null) return null;
        return round($this->value - $this->previousValue, 2);
    }

    /** true = amélioration, false = régression, null = pas de données. */
    public function isImproving(): ?bool
    {
        $evo = $this->getEvolution();
        if ($evo === null) return null;
        return $evo > 0;
    }

    public function getTrendIcon(): string
    {
        $evo = $this->getEvolution();
        if ($evo === null) return 'minus';
        return $evo > 0 ? 'trending-up' : ($evo < 0 ? 'trending-down' : 'minus');
    }

    public function getTrendColor(): string
    {
        $evo = $this->getEvolution();
        if ($evo === null) return 'slate';
        return $evo > 0 ? 'emerald' : ($evo < 0 ? 'red' : 'amber');
    }

    // ── Sérialisation ─────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'type'           => $this->type,
            'label'          => $this->label,
            'value'          => $this->value,
            'previous_value' => $this->previousValue,
            'count'          => $this->count,
            'evolution'      => $this->getEvolution(),
            'is_improving'   => $this->isImproving(),
            'trend_icon'     => $this->getTrendIcon(),
            'trend_color'    => $this->getTrendColor(),
            'breakdown'      => $this->breakdown,
            'metadata'       => $this->metadata,
            'computed_at'    => $this->computedAt,
        ];
    }

    /** Construit un AnalyticsResult vide (aucune donnée disponible). */
    public static function empty(string $type, string $label, array $metadata = []): self
    {
        return new self(
            type         : $type,
            label        : $label,
            value        : 0.0,
            previousValue: null,
            count        : 0,
            breakdown    : [],
            metadata     : $metadata,
            computedAt   : date('Y-m-d H:i:s'),
        );
    }
}
