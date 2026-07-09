<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FinancialReportInterface;
use App\Modules\Finance\DTO\ReportFiltersDTO;
use App\Modules\Finance\Events\FinancialReportGenerated;
use App\Modules\Finance\Events\FinancialReportExported;
use App\Modules\Finance\Repositories\FinancialReportRepository;
use App\Modules\Finance\Repositories\AccountingRepository;
use Core\AuditService;
use Core\EventDispatcher;

class FinancialReportService implements FinancialReportInterface
{
    private FinancialReportRepository $repo;
    private AccountingRepository      $accountingRepo;

    public function __construct()
    {
        $this->repo           = new FinancialReportRepository();
        $this->accountingRepo = new AccountingRepository();
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function getDashboard(ReportFiltersDTO $filters): array
    {
        $date = $filters->dateFin ?: date('Y-m-d');

        $stats         = $this->repo->getDashboardStats($date);
        $evolution     = $this->repo->getEvolutionMensuelle($filters->annee ?: (int)date('Y'));
        $topDebiteurs  = $this->repo->getTopDebiteurs(5);
        $statsParMode  = $this->repo->getStatsParMode();
        $anneesSco     = $this->repo->getAnneesScolaires();

        // Données comptables si un exercice courant existe
        $statsComptable = null;
        try {
            $exercice = $this->accountingRepo->findExerciceCourant();
            if ($exercice) {
                $statsComptable = $this->accountingRepo->getStatsDashboard($exercice->id);
            }
        } catch (\Throwable) {
            // table inexistante en V1 → silencieux
        }

        return compact(
            'stats',
            'evolution',
            'topDebiteurs',
            'statsParMode',
            'anneesSco',
            'statsComptable',
            'date'
        );
    }

    // ── Paiements ─────────────────────────────────────────────────────────────

    public function getPaiementsReport(ReportFiltersDTO $filters): array
    {
        $f = $this->filtersToArray($filters);

        $pagination   = $this->repo->paginatePaiements($f, $filters->page, $filters->perPage);
        $sommes       = $this->repo->getSommePaiements($f);
        $parClasse    = $this->repo->getPaiementsParClasse($f);
        $parMode      = $this->repo->getStatsParMode();
        $classes      = $this->repo->getClasses();
        $niveaux      = $this->repo->getNiveaux();
        $modes        = $this->repo->getModesPaiement();
        $anneesSco    = $this->repo->getAnneesScolaires();

        return compact(
            'pagination',
            'sommes',
            'parClasse',
            'parMode',
            'classes',
            'niveaux',
            'modes',
            'anneesSco',
            'filters'
        );
    }

    // ── Factures ──────────────────────────────────────────────────────────────

    public function getFacturesReport(ReportFiltersDTO $filters): array
    {
        $f = $this->filtersToArray($filters);

        $pagination = $this->repo->paginateFactures($f, $filters->page, $filters->perPage);
        $stats      = $this->repo->getStatsFactures($f);
        $classes    = $this->repo->getClasses();
        $niveaux    = $this->repo->getNiveaux();
        $anneesSco  = $this->repo->getAnneesScolaires();

        $tauxRecouvrement = ($stats->total_emis > 0)
            ? round(($stats->total_encaisse / $stats->total_emis) * 100, 1)
            : 0;

        return compact(
            'pagination',
            'stats',
            'tauxRecouvrement',
            'classes',
            'niveaux',
            'anneesSco',
            'filters'
        );
    }

    // ── Impayés ───────────────────────────────────────────────────────────────

    public function getImpayesReport(ReportFiltersDTO $filters): array
    {
        $f        = $this->filtersToArray($filters);
        $impayes  = $this->repo->getImpayes($f);
        $aging    = $this->repo->getImpayesAging($f);
        $classes  = $this->repo->getClasses();
        $niveaux  = $this->repo->getNiveaux();
        $anneesSco = $this->repo->getAnneesScolaires();

        $totalDu   = array_sum(array_column((array)$impayes, 'montant_restant'));
        $totalEleves = count(array_unique(array_column((array)$impayes, 'eleve_matricule')));

        return compact(
            'impayes',
            'aging',
            'totalDu',
            'totalEleves',
            'classes',
            'niveaux',
            'anneesSco',
            'filters'
        );
    }

    // ── Caisse ────────────────────────────────────────────────────────────────

    public function getCaisseReport(ReportFiltersDTO $filters): array
    {
        $f = $this->filtersToArray($filters);

        $pagination  = $this->repo->paginateSessions($f, $filters->page, $filters->perPage);
        $stats       = $this->repo->getStatsCaisse($f);

        return compact('pagination', 'stats', 'filters');
    }

    // ── Analytique ────────────────────────────────────────────────────────────

    public function getAnalytiqueReport(ReportFiltersDTO $filters): array
    {
        $annee     = $filters->annee ?: (int)date('Y');
        $annees    = [$annee - 2, $annee - 1, $annee];
        $anneesSco = $this->repo->getAnneesScolaires();
        $anneeSco  = $filters->anneeScolaire ?: ($anneesSco[0] ?? '');

        $comparatifAnnuel  = $this->repo->getComparatifAnnuel($annees);
        $comparatifClasse  = $this->repo->getComparatifParClasse($anneeSco);
        $projectionMens    = $this->repo->getProjectionMensuelle($annee);
        $evolution         = $this->repo->getEvolutionMensuelle($annee);
        $statsParMode      = $this->repo->getStatsParMode();

        return compact(
            'comparatifAnnuel',
            'comparatifClasse',
            'projectionMens',
            'evolution',
            'statsParMode',
            'annee',
            'annees',
            'anneeSco',
            'anneesSco',
            'filters'
        );
    }

    // ── Export dispatcher ─────────────────────────────────────────────────────

    public function exporterRapport(string $type, string $format, ReportFiltersDTO $filters, int $userId): array
    {
        $data = match($type) {
            'dashboard'  => $this->getDashboard($filters),
            'paiements'  => $this->getPaiementsReport($filters),
            'factures'   => $this->getFacturesReport($filters),
            'impayes'    => $this->getImpayesReport($filters),
            'caisse'     => $this->getCaisseReport($filters),
            'analytique' => $this->getAnalytiqueReport($filters),
            default      => throw new \InvalidArgumentException("Type de rapport inconnu: {$type}"),
        };

        $nbLignes = $this->countLignes($data);

        EventDispatcher::dispatch(new FinancialReportExported(
            reportType:   $type,
            format:       $format,
            nbLignes:     $nbLignes,
            exportedById: $userId,
        ));

        return match($format) {
            'csv'   => $this->exportCsv($type, $data),
            'excel' => $this->exportExcel($type, $data),
            'pdf'   => $this->exportPdf($type, $data),
            default => $data,
        };
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    private function exportCsv(string $type, array $data): array
    {
        $rows    = $this->flattenForExport($type, $data);
        $headers = !empty($rows) ? array_keys((array)$rows[0]) : [];

        $lines = [];
        $lines[] = implode(';', $headers);
        foreach ($rows as $row) {
            $cells = array_map(
                fn($v) => '"' . str_replace('"', '""', (string)$v) . '"',
                array_values((array)$row)
            );
            $lines[] = implode(';', $cells);
        }

        return [
            'content'     => "\xEF\xBB\xBF" . implode("\r\n", $lines), // UTF-8 BOM pour Excel
            'filename'    => 'rapport_' . $type . '_' . date('Y-m-d') . '.csv',
            'mime'        => 'text/csv; charset=UTF-8',
            'nb_lignes'   => count($rows),
        ];
    }

    private function exportExcel(string $type, array $data): array
    {
        // Excel via HTML table (compatible avec XLSX ouverture directe)
        $rows    = $this->flattenForExport($type, $data);
        $headers = !empty($rows) ? array_keys((array)$rows[0]) : [];

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        $html .= '<head><meta charset="UTF-8"/></head><body><table>';
        $html .= '<tr>' . implode('', array_map(fn($h) => "<th>{$h}</th>", $headers)) . '</tr>';
        foreach ($rows as $row) {
            $html .= '<tr>' . implode('', array_map(fn($v) => '<td>' . htmlspecialchars((string)$v) . '</td>', array_values((array)$row))) . '</tr>';
        }
        $html .= '</table></body></html>';

        return [
            'content'   => $html,
            'filename'  => 'rapport_' . $type . '_' . date('Y-m-d') . '.xls',
            'mime'      => 'application/vnd.ms-excel; charset=UTF-8',
            'nb_lignes' => count($rows),
        ];
    }

    private function exportPdf(string $type, array $data): array
    {
        // PDF is rendered server-side via dedicated print view (browser print / wkhtmltopdf)
        // Return data + metadata; controller renders the print template
        return array_merge($data, [
            '_export_pdf' => true,
            '_filename'   => 'rapport_' . $type . '_' . date('Y-m-d') . '.pdf',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function filtersToArray(ReportFiltersDTO $filters): array
    {
        return [
            'date_debut'    => $filters->dateDebut,
            'date_fin'      => $filters->dateFin,
            'annee_scolaire'=> $filters->anneeScolaire,
            'classe_id'     => $filters->classeId ?: null,
            'niveau'        => $filters->niveau,
            'eleve_id'      => $filters->eleveId ?: null,
            'statut'        => $filters->statut,
            'mode_paiement' => $filters->modePaiement,
            'q'             => $filters->q,
            'tranche'       => $filters->tranche,
        ];
    }

    private function flattenForExport(string $type, array $data): array
    {
        return match($type) {
            'paiements'  => $data['pagination']['items'] ?? [],
            'factures'   => $data['pagination']['items'] ?? [],
            'impayes'    => $data['impayes'] ?? [],
            'caisse'     => $data['pagination']['items'] ?? [],
            'analytique' => $data['comparatifClasse'] ?? [],
            default      => [],
        };
    }

    private function countLignes(array $data): int
    {
        return count(
            $data['pagination']['items']
            ?? $data['impayes']
            ?? $data['comparatifClasse']
            ?? []
        );
    }
}
