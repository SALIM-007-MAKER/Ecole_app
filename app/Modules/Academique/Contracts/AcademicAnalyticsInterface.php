<?php

namespace App\Modules\Academique\Contracts;

use App\Modules\Academique\DTO\AnalyticsResult;
use App\Modules\Academique\DTO\DashboardMetrics;

/**
 * Contrat du moteur d'analytique académique.
 *
 * RÈGLES :
 *   • Aucun calcul de moyenne dupliqué — AcademicCalculationService uniquement
 *   • Les seuils (note_passage, mentions) viennent de AcademicCalculationService
 *   • Les classements viennent de RankingEngine
 *   • Les agrégations en volume utilisent SQL (GROUP BY) — pas de boucle PHP sur N élèves
 */
interface AcademicAnalyticsInterface
{
    // ── Indicateurs ──────────────────────────────────────────────────

    public function moyenneEtablissement(int $periodeId): AnalyticsResult;

    /** @return AnalyticsResult[] keyed by niveau */
    public function moyenneParNiveau(int $periodeId): array;

    /** @return AnalyticsResult[] keyed by classeId */
    public function moyenneParClasse(int $periodeId, ?string $niveau = null): array;

    /** @return AnalyticsResult[] keyed by matiereId */
    public function moyenneParMatiere(int $periodeId, ?int $classeId = null): array;

    public function tauxReussite(int $periodeId, ?int $classeId = null): float;

    public function tauxAbsenteisme(int $periodeId, ?int $classeId = null): float;

    /** Histogramme de distribution des notes individuelles (pas les moyennes). */
    public function distributionNotes(
        int  $periodeId,
        ?int $classeId  = null,
        ?int $matiereId = null
    ): array;

    /** Répartition des mentions (par moyenne élève, approximation SQL). */
    public function repartitionMentions(int $periodeId, ?int $classeId = null): array;

    /**
     * Évolution des résultats sur plusieurs périodes.
     * @return array [{periode_id, periode_nom, moyenne, evolution, taux_reussite}]
     */
    public function evolutionResultats(int $classeId, array $periodeIds): array;

    // ── Tableaux de bord ─────────────────────────────────────────────

    public function dashboardDirecteur(int $periodeId): DashboardMetrics;

    public function dashboardEnseignant(int $enseignantId, int $periodeId): DashboardMetrics;

    public function dashboardResponsable(int $periodeId, ?string $niveau = null): DashboardMetrics;

    // ── Export ────────────────────────────────────────────────────────

    /**
     * Données tabulaires pour export Excel.
     * @return array{title, headers, rows, summary, metadata}
     */
    public function exportExcelData(int $periodeId, string $type, array $filtres = []): array;

    /**
     * Données structurées pour export PDF.
     * @return array{title, sections[], metadata}
     */
    public function exportPdfData(int $periodeId, string $type, array $filtres = []): array;

    /**
     * Widgets légers pour le frontend (cards, mini-charts).
     * @return array of widget data
     */
    public function getWidgets(int $periodeId): array;
}
