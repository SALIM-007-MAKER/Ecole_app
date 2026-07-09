<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\DTO\ReportFiltersDTO;
use App\Modules\Rapports\Events\RapportGenere;
use App\Modules\Rapports\Events\RapportExporte;
use App\Modules\Rapports\Repositories\ExportRepository;
use App\Shared\Analytics\ReportEngine;
use App\Shared\Analytics\ExportEngine;
use App\Shared\Analytics\ChartEngine;
use Core\EventDispatcher;

class ReportGeneratorService
{
    private ReportEngine          $reportEngine;
    private ExportEngine          $exportEngine;
    private ChartEngine           $chartEngine;
    private DataAggregatorService $aggregator;
    private ExportRepository      $exports;

    private const COLONNES = [
        'scolarite'    => [['key' => 'classe', 'label' => 'Classe'], ['key' => 'niveau', 'label' => 'Niveau'], ['key' => 'nb_eleves', 'label' => 'Élèves']],
        'academique'   => [['key' => 'classe', 'label' => 'Classe'], ['key' => 'moyenne', 'label' => 'Moyenne'], ['key' => 'nb_notes', 'label' => 'Notes']],
        'finance'      => [['key' => 'numero', 'label' => 'N° Facture'], ['key' => 'eleve', 'label' => 'Élève'], ['key' => 'montant_ttc', 'label' => 'Montant TTC'], ['key' => 'echeance', 'label' => 'Échéance']],
        'vie_scolaire' => [['key' => 'classe', 'label' => 'Classe'], ['key' => 'nb_absences', 'label' => 'Absences'], ['key' => 'nb_justifiees', 'label' => 'Justifiées']],
        'rh'           => [['key' => 'departement', 'label' => 'Département'], ['key' => 'nb', 'label' => 'Effectif']],
        'bibliotheque' => [['key' => 'titre', 'label' => 'Titre'], ['key' => 'auteur', 'label' => 'Auteur'], ['key' => 'nb_emprunts', 'label' => 'Emprunts']],
        'inventaire'   => [['key' => 'designation', 'label' => 'Article'], ['key' => 'stock_total', 'label' => 'Stock'], ['key' => 'seuil_alerte', 'label' => 'Seuil']],
    ];

    public function __construct()
    {
        $this->reportEngine = new ReportEngine();
        $this->exportEngine = new ExportEngine();
        $this->chartEngine  = new ChartEngine();
        $this->aggregator   = new DataAggregatorService();
        $this->exports      = new ExportRepository();
    }

    public function generer(ReportFiltersDTO $filters, int $etablissementId, int $userId): array
    {
        $data     = $this->aggregator->getForDomaine($filters->domaine, $etablissementId, $filters->toArray());
        $rows     = $this->getRows($filters->domaine, $data);
        $colonnes = self::COLONNES[$filters->domaine] ?? [['key' => 'id', 'label' => 'ID']];
        $rapport  = $this->reportEngine->buildTableau($rows, $colonnes, [
            'titre'   => 'Rapport ' . ucfirst($filters->domaine),
            'periode' => $filters->periode,
        ]);

        EventDispatcher::dispatch(new RapportGenere(
            $filters->domaine,
            $filters->typeExport,
            $userId,
            count($rows),
            $etablissementId,
        ));

        return $rapport;
    }

    public function genererCsv(ReportFiltersDTO $filters, int $etablissementId, int $userId): string
    {
        $data     = $this->aggregator->getForDomaine($filters->domaine, $etablissementId, $filters->toArray());
        $rows     = $this->getRows($filters->domaine, $data);
        $colonnes = self::COLONNES[$filters->domaine] ?? [['key' => 'id', 'label' => 'ID']];
        $csv      = $this->exportEngine->toCsv($rows, $colonnes);

        $this->enregistrerExport($filters, $userId, $etablissementId, count($rows));

        return $csv;
    }

    public function genererExcel(ReportFiltersDTO $filters, int $etablissementId, int $userId): string
    {
        $data     = $this->aggregator->getForDomaine($filters->domaine, $etablissementId, $filters->toArray());
        $rows     = $this->getRows($filters->domaine, $data);
        $colonnes = self::COLONNES[$filters->domaine] ?? [['key' => 'id', 'label' => 'ID']];
        $xml      = $this->exportEngine->toExcelXml($rows, $colonnes, 'Rapport ' . ucfirst($filters->domaine));

        $this->enregistrerExport($filters, $userId, $etablissementId, count($rows));

        return $xml;
    }

    public function genererHtml(ReportFiltersDTO $filters, int $etablissementId, int $userId): string
    {
        $data     = $this->aggregator->getForDomaine($filters->domaine, $etablissementId, $filters->toArray());
        $rows     = $this->getRows($filters->domaine, $data);
        $colonnes = self::COLONNES[$filters->domaine] ?? [['key' => 'id', 'label' => 'ID']];

        $this->enregistrerExport($filters, $userId, $etablissementId, count($rows));

        return $this->exportEngine->toHtmlTable($rows, $colonnes, 'Rapport ' . ucfirst($filters->domaine));
    }

    private function getRows(string $domaine, array $data): array
    {
        return match ($domaine) {
            'scolarite'    => $data['effectifs_classes']       ?? [],
            'academique'   => $data['moyennes_classes']        ?? [],
            'finance'      => $data['factures_impayees']       ?? [],
            'vie_scolaire' => $data['absences_par_classe']     ?? [],
            'rh'           => $data['effectifs_dept']          ?? [],
            'bibliotheque' => $data['ouvrages_populaires']     ?? [],
            'inventaire'   => $data['alertes_stock']           ?? [],
            default        => [],
        };
    }

    private function enregistrerExport(ReportFiltersDTO $filters, int $userId, int $etab, int $nb): void
    {
        $fichierNom = 'rapport_' . $filters->domaine . '_' . date('Ymd_His') . '.' . $filters->typeExport;
        $exportId   = $this->exports->create([
            'domaine'          => $filters->domaine,
            'type_export'      => $filters->typeExport,
            'filtres'          => $filters->toArray(),
            'fichier_path'     => '/exports/' . $fichierNom,
            'fichier_nom'      => $fichierNom,
            'nb_lignes'        => $nb,
            'expire_at'        => date('Y-m-d H:i:s', strtotime('+7 days')),
            'user_id'          => $userId,
            'etablissement_id' => $etab,
        ]);
        EventDispatcher::dispatch(new RapportExporte(
            $exportId,
            $filters->domaine,
            $filters->typeExport,
            $fichierNom,
            $userId,
            $etab,
        ));
    }
}
