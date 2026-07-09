<?php

namespace App\Modules\Academique\DTO;

/**
 * Métriques complètes pour un tableau de bord.
 *
 * Rôles supportés : 'directeur' | 'enseignant' | 'responsable'
 */
final class DashboardMetrics
{
    public function __construct(
        public readonly string  $role,

        public readonly int     $periodeId,
        public readonly string  $periodeNom,

        /** classeId pour 'enseignant', null pour 'directeur'/'responsable'. */
        public readonly ?int    $contextId,
        public readonly ?string $contextLabel,

        /**
         * KPI cards. Chaque entrée :
         *   {id, label, value, unit, trend, trend_color, icon}
         */
        public readonly array   $kpis,

        /**
         * Top N performers.
         *   {rang, eleve_id, nom, prenom, classe_nom, moyenne, mention_code}
         */
        public readonly array   $topPerformers,

        /**
         * Élèves en difficulté (moyenne < seuil).
         *   {eleve_id, nom, prenom, classe_nom, moyenne_approx}
         */
        public readonly array   $alertes,

        /**
         * Histogramme des notes : [{tranche, nb, pourcentage, color}]
         */
        public readonly array   $distributionNotes,

        /**
         * Répartition mentions : [{code, label, nb, pourcentage, css}]
         */
        public readonly array   $repartitionMentions,

        /**
         * Données d'évolution pour graphiques :
         *   [{periode_id, periode_nom, moyenne, evolution}]
         */
        public readonly array   $evolutionChart,

        /**
         * Vue d'ensemble des classes (directeur/responsable) :
         *   [{classe_id, classe_nom, niveau, nb_eleves, moyenne, taux_reussite}]
         */
        public readonly array   $classesOverview,

        /**
         * Vue d'ensemble des matières (enseignant) :
         *   [{matiere_id, matiere_nom, nb_eleves, moyenne, taux_reussite}]
         */
        public readonly array   $matieresOverview,

        public readonly string  $generatedAt,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->kpis) && empty($this->classesOverview) && empty($this->matieresOverview);
    }

    public function getKpi(string $id): ?array
    {
        foreach ($this->kpis as $kpi) {
            if (($kpi['id'] ?? '') === $id) return $kpi;
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'role'                 => $this->role,
            'periode_id'           => $this->periodeId,
            'periode_nom'          => $this->periodeNom,
            'context_id'           => $this->contextId,
            'context_label'        => $this->contextLabel,
            'kpis'                 => $this->kpis,
            'top_performers'       => $this->topPerformers,
            'alertes'              => $this->alertes,
            'distribution_notes'   => $this->distributionNotes,
            'repartition_mentions' => $this->repartitionMentions,
            'evolution_chart'      => $this->evolutionChart,
            'classes_overview'     => $this->classesOverview,
            'matieres_overview'    => $this->matieresOverview,
            'generated_at'         => $this->generatedAt,
        ];
    }

    public static function empty(string $role, int $periodeId): self
    {
        return new self(
            role               : $role,
            periodeId          : $periodeId,
            periodeNom         : '',
            contextId          : null,
            contextLabel       : null,
            kpis               : [],
            topPerformers      : [],
            alertes            : [],
            distributionNotes  : [],
            repartitionMentions: [],
            evolutionChart     : [],
            classesOverview    : [],
            matieresOverview   : [],
            generatedAt        : date('Y-m-d H:i:s'),
        );
    }
}
