<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\DTO\DashboardMetricsDTO;
use App\Modules\Rapports\Events\DashboardConsulte;
use App\Modules\Rapports\Repositories\SnapshotRepository;
use App\Shared\Analytics\ChartEngine;
use App\Shared\Analytics\DashboardBuilder;
use Core\EventDispatcher;

class DashboardService
{
    private DataAggregatorService $aggregator;
    private KpiService            $kpiService;
    private TendanceService       $tendance;
    private ChartEngine           $charts;
    private DashboardBuilder      $builder;
    private SnapshotRepository    $snapshots;

    public function __construct()
    {
        $this->aggregator = new DataAggregatorService();
        $this->kpiService = new KpiService();
        $this->tendance   = new TendanceService();
        $this->charts     = new ChartEngine();
        $this->builder    = new DashboardBuilder();
        $this->snapshots  = new SnapshotRepository();
    }

    public function getDashboard(string $contexte, int $etablissementId, int $userId, array $userConfig = []): DashboardMetricsDTO
    {
        $built = match ($contexte) {
            'direction'      => $this->direction($etablissementId),
            'administration' => $this->administration($etablissementId),
            'scolarite'      => $this->scolarite($etablissementId),
            'academique'     => $this->academique($etablissementId),
            'finance'        => $this->finance($etablissementId),
            'rh'             => $this->rh($etablissementId),
            'vie_scolaire'   => $this->vieScolaire($etablissementId),
            'bibliotheque'   => $this->bibliotheque($etablissementId),
            'inventaire'     => $this->inventaire($etablissementId),
            default          => $this->direction($etablissementId),
        };

        EventDispatcher::dispatch(new DashboardConsulte($contexte, $userId, $etablissementId));

        return DashboardMetricsDTO::fromBuilder($built);
    }

    private function direction(int $etab): array
    {
        $scol  = $this->aggregator->getScolarite($etab);
        $fin   = $this->aggregator->getFinance($etab);
        $acad  = $this->aggregator->getAcademique($etab);
        $rh    = $this->aggregator->getRH($etab);

        $serieRecettes = $this->charts->serieMensuelle($fin['recettes_par_mois'], 'mois', 'total');

        return (new DashboardBuilder())
            ->setContexte('direction')
            ->addKpi('nb_eleves',   'Élèves inscrits',  (int)($scol['counters']['nb_eleves']  ?? 0), 'élèves')
            ->addKpi('nb_classes',  'Classes',          (int)($scol['counters']['nb_classes'] ?? 0), '')
            ->addKpi('recettes',    'Recettes totales', (float)($fin['counters']['total_paye'] ?? 0), 'FCFA')
            ->addKpi('taux_recouv', 'Taux recouvrement', (float)($fin['taux_recouvrement'] ?? 0), '%')
            ->addKpi('moyenne_gen', 'Moy. générale',   (float)($acad['counters']['moyenne_generale'] ?? 0), '/20')
            ->addKpi('nb_employes', 'Employés actifs', (int)($rh['counters']['nb_employes_actifs'] ?? 0), '')
            ->addChart('chart_recettes', 'Recettes mensuelles',
                $this->charts->line($serieRecettes['labels'] ?? [], [['label' => 'Recettes', 'data' => $serieRecettes['values'] ?? []]]))
            ->addChart('chart_impayes', 'Factures impayées (top)',
                $this->charts->bar(
                    array_map(fn($r) => $r['eleve'] ?? '', array_slice($fin['factures_impayees'] ?? [], 0, 10)),
                    [['label' => 'Montant', 'data' => array_map(fn($r) => (float)$r['montant_ttc'], array_slice($fin['factures_impayees'] ?? [], 0, 10))]]],
                ))
            ->addTable('table_impayes', 'Impayés urgents',
                $fin['factures_impayees'] ?? [],
                [['key' => 'numero', 'label' => 'N° Facture'], ['key' => 'eleve', 'label' => 'Élève'], ['key' => 'montant_ttc', 'label' => 'Montant'], ['key' => 'echeance', 'label' => 'Échéance']],
                5)
            ->addAlertes('alertes_direction', array_merge(
                $this->tendance->alertesKpi('finance', $etab),
                $this->tendance->alertesKpi('academique', $etab),
            ))
            ->build();
    }

    private function administration(int $etab): array
    {
        $scol = $this->aggregator->getScolarite($etab);
        $fin  = $this->aggregator->getFinance($etab);
        $vs   = $this->aggregator->getVieScolaire($etab);
        $inv  = $this->aggregator->getInventaire($etab);

        return (new DashboardBuilder())
            ->setContexte('administration')
            ->addKpi('nb_eleves',     'Élèves',     (int)($scol['counters']['nb_eleves'] ?? 0), '')
            ->addKpi('nb_impayes',    'Impayés',    (int)($fin['counters']['nb_impayes'] ?? 0), 'factures')
            ->addKpi('nb_absences',   'Absences',   (int)($vs['counters']['nb_absences'] ?? 0), '')
            ->addKpi('alertes_stock', 'Alertes inv.',(int)($inv['counters']['nb_alertes_stock'] ?? 0), '')
            ->addChart('chart_genre', 'Répartition genre',
                $this->charts->doughnut(
                    array_column($scol['repartition_genre'] ?? [], 'genre'),
                    array_map(fn($r) => (int)$r['nb'], $scol['repartition_genre'] ?? []),
                ))
            ->addTable('table_absences', 'Absences par classe',
                $scol['effectifs_classes'] ?? [],
                [['key' => 'classe', 'label' => 'Classe'], ['key' => 'nb_eleves', 'label' => 'Élèves']])
            ->build();
    }

    private function scolarite(int $etab): array
    {
        $data = $this->aggregator->getScolarite($etab);
        $serie = $this->charts->serieMensuelle($data['evolution_inscriptions'] ?? [], 'mois', 'nb');

        return (new DashboardBuilder())
            ->setContexte('scolarite')
            ->addKpi('nb_eleves',  'Élèves inscrits', (int)($data['counters']['nb_eleves']  ?? 0), '')
            ->addKpi('nb_classes', 'Classes',         (int)($data['counters']['nb_classes'] ?? 0), '')
            ->addChart('chart_inscriptions', 'Évolution inscriptions',
                $this->charts->line($serie['labels'] ?? [], [['label' => 'Inscriptions', 'data' => $serie['values'] ?? []]]))
            ->addChart('chart_remplissage', 'Taux remplissage classes',
                $this->charts->horizontalBar(
                    array_column($data['taux_remplissage'] ?? [], 'classe'),
                    [['label' => 'Taux (%)', 'data' => array_map(fn($r) => (float)$r['taux'], $data['taux_remplissage'] ?? [])]],
                ))
            ->addChart('chart_genre', 'Répartition genre',
                $this->charts->doughnut(
                    array_column($data['repartition_genre'] ?? [], 'genre'),
                    array_map(fn($r) => (int)$r['nb'], $data['repartition_genre'] ?? []),
                ))
            ->addTable('table_classes', 'Effectifs par classe',
                $data['effectifs_classes'] ?? [],
                [['key' => 'classe', 'label' => 'Classe'], ['key' => 'niveau', 'label' => 'Niveau'], ['key' => 'nb_eleves', 'label' => 'Élèves']])
            ->build();
    }

    private function academique(int $etab): array
    {
        $data  = $this->aggregator->getAcademique($etab);
        $distrib = $data['distrib_mentions'] ?? [];
        $evo     = $this->charts->serieJournaliere($data['evolution_moyennes'] ?? [], 'periode', 'moyenne');

        return (new DashboardBuilder())
            ->setContexte('academique')
            ->addKpi('moyenne_gen',  'Moyenne générale', (float)($data['counters']['moyenne_generale'] ?? 0), '/20')
            ->addKpi('nb_notes',     'Notes saisies',    (int)($data['counters']['nb_notes'] ?? 0), '')
            ->addChart('chart_mentions', 'Distribution mentions',
                $this->charts->doughnut(
                    ['TB', 'B', 'AB', 'Passable', 'Insuffisant'],
                    [(int)($distrib['tres_bien'] ?? 0), (int)($distrib['bien'] ?? 0), (int)($distrib['assez_bien'] ?? 0), (int)($distrib['passable'] ?? 0), (int)($distrib['insuffisant'] ?? 0)],
                ))
            ->addChart('chart_moyennes_classes', 'Moyenne par classe',
                $this->charts->bar(
                    array_column($data['moyennes_classes'] ?? [], 'classe'),
                    [['label' => 'Moyenne', 'data' => array_map(fn($r) => (float)$r['moyenne'], $data['moyennes_classes'] ?? [])]],
                ))
            ->addChart('chart_reussite_matieres', 'Taux réussite par matière',
                $this->charts->horizontalBar(
                    array_column($data['taux_reussite_matieres'] ?? [], 'matiere'),
                    [['label' => 'Taux (%)', 'data' => array_map(fn($r) => (float)$r['taux_reussite'], $data['taux_reussite_matieres'] ?? [])]],
                ))
            ->addTable('table_classes_moy', 'Classement classes',
                $data['moyennes_classes'] ?? [],
                [['key' => 'classe', 'label' => 'Classe'], ['key' => 'moyenne', 'label' => 'Moyenne'], ['key' => 'nb_notes', 'label' => 'Notes']])
            ->addAlertes('alertes_acad', $this->tendance->alertesKpi('academique', $etab))
            ->build();
    }

    private function finance(int $etab): array
    {
        $data        = $this->aggregator->getFinance($etab);
        $serieRecettes = $this->charts->serieMensuelle($data['recettes_par_mois'] ?? [], 'mois', 'total');

        return (new DashboardBuilder())
            ->setContexte('finance')
            ->addKpi('total_facture',    'Total facturé',  (float)($data['counters']['total_facture'] ?? 0), 'FCFA')
            ->addKpi('total_paye',       'Total encaissé', (float)($data['counters']['total_paye']    ?? 0), 'FCFA')
            ->addKpi('nb_impayes',       'Impayés',        (int)($data['counters']['nb_impayes']      ?? 0), 'factures')
            ->addKpi('taux_recouvrement','Taux recouvrement', (float)($data['taux_recouvrement']     ?? 0), '%')
            ->addChart('chart_recettes', 'Recettes mensuelles',
                $this->charts->line($serieRecettes['labels'] ?? [], [['label' => 'Recettes', 'data' => $serieRecettes['values'] ?? []]]))
            ->addChart('chart_caisse', 'Mouvements caisse',
                $this->charts->doughnut(
                    array_column($data['mouvements_caisse'] ?? [], 'type_mouvement'),
                    array_map(fn($r) => (float)$r['total'], $data['mouvements_caisse'] ?? []),
                ))
            ->addTable('table_impayes', 'Top impayés',
                $data['factures_impayees'] ?? [],
                [['key' => 'numero', 'label' => 'N° Fac.'], ['key' => 'eleve', 'label' => 'Élève'], ['key' => 'montant_ttc', 'label' => 'Montant'], ['key' => 'echeance', 'label' => 'Échéance']])
            ->addAlertes('alertes_fin', $this->tendance->alertesKpi('finance', $etab))
            ->build();
    }

    private function rh(int $etab): array
    {
        $data = $this->aggregator->getRH($etab);

        return (new DashboardBuilder())
            ->setContexte('rh')
            ->addKpi('nb_employes',      'Employés actifs',     (int)($data['counters']['nb_employes_actifs'] ?? 0), '')
            ->addKpi('taux_presence',    'Taux présence RH',    (float)($data['taux_presence'] ?? 0), '%')
            ->addKpi('nb_conges',        'Congés en attente',   (int)($data['counters']['nb_conges_attente'] ?? 0), '')
            ->addChart('chart_dept', 'Effectifs par département',
                $this->charts->doughnut(
                    array_column($data['effectifs_dept'] ?? [], 'departement'),
                    array_map(fn($r) => (int)$r['nb'], $data['effectifs_dept'] ?? []),
                ))
            ->addChart('chart_conges', 'Congés par type',
                $this->charts->bar(
                    array_column($data['conges_par_type'] ?? [], 'type_conge'),
                    [['label' => 'Jours', 'data' => array_map(fn($r) => (int)$r['nb_jours'], $data['conges_par_type'] ?? [])]],
                ))
            ->addTable('table_contrats', 'Contrats expirant',
                $data['contrats_expirant'] ?? [],
                [['key' => 'employe', 'label' => 'Employé'], ['key' => 'type_contrat', 'label' => 'Type'], ['key' => 'date_fin', 'label' => 'Expiration']])
            ->build();
    }

    private function vieScolaire(int $etab): array
    {
        $data = $this->aggregator->getVieScolaire($etab);
        $serieAbs = $this->charts->serieMensuelle($data['evolution_absences'] ?? [], 'mois', 'nb');

        return (new DashboardBuilder())
            ->setContexte('vie_scolaire')
            ->addKpi('nb_absences',  'Absences',  (int)($data['counters']['nb_absences']  ?? 0), '')
            ->addKpi('nb_retards',   'Retards',   (int)($data['counters']['nb_retards']   ?? 0), '')
            ->addKpi('nb_incidents', 'Incidents', (int)($data['counters']['nb_incidents'] ?? 0), '')
            ->addChart('chart_abs_evolution', 'Évolution absences',
                $this->charts->line($serieAbs['labels'] ?? [], [['label' => 'Absences', 'data' => $serieAbs['values'] ?? []]]))
            ->addChart('chart_abs_classes', 'Absences par classe',
                $this->charts->horizontalBar(
                    array_column($data['absences_par_classe'] ?? [], 'classe'),
                    [['label' => 'Absences', 'data' => array_map(fn($r) => (int)$r['nb_absences'], $data['absences_par_classe'] ?? [])]],
                ))
            ->addChart('chart_incidents', 'Incidents par type',
                $this->charts->doughnut(
                    array_column($data['incidents_par_type'] ?? [], 'type_incident'),
                    array_map(fn($r) => (int)$r['nb'], $data['incidents_par_type'] ?? []),
                ))
            ->build();
    }

    private function bibliotheque(int $etab): array
    {
        $data = $this->aggregator->getBibliotheque($etab);
        $serieEmp = $this->charts->serieMensuelle($data['emprunts_par_mois'] ?? [], 'mois', 'nb');

        return (new DashboardBuilder())
            ->setContexte('bibliotheque')
            ->addKpi('nb_ouvrages',      'Ouvrages',       (int)($data['counters']['nb_ouvrages'] ?? 0), '')
            ->addKpi('nb_emprunts_cours','En cours',       (int)($data['counters']['nb_emprunts_cours'] ?? 0), '')
            ->addKpi('taux_retour',      'Taux retour',    (float)($data['taux_retour'] ?? 0), '%')
            ->addChart('chart_emprunts', 'Emprunts mensuels',
                $this->charts->bar($serieEmp['labels'] ?? [], [['label' => 'Emprunts', 'data' => $serieEmp['values'] ?? []]]))
            ->addTable('table_retard', 'Emprunts en retard',
                $data['emprunts_en_retard'] ?? [],
                [['key' => 'titre', 'label' => 'Ouvrage'], ['key' => 'jours_retard', 'label' => 'Retard (j)']])
            ->addTable('table_populaires', 'Ouvrages populaires',
                $data['ouvrages_populaires'] ?? [],
                [['key' => 'titre', 'label' => 'Titre'], ['key' => 'auteur', 'label' => 'Auteur'], ['key' => 'nb_emprunts', 'label' => 'Emprunts']])
            ->build();
    }

    private function inventaire(int $etab): array
    {
        $data = $this->aggregator->getInventaire($etab);

        return (new DashboardBuilder())
            ->setContexte('inventaire')
            ->addKpi('nb_articles',      'Articles actifs', (int)($data['counters']['nb_articles']      ?? 0), '')
            ->addKpi('valeur_stock',     'Valeur stock',    (float)($data['valeur_stock']               ?? 0), 'FCFA')
            ->addKpi('nb_alertes_stock', 'Alertes stock',   (int)($data['counters']['nb_alertes_stock'] ?? 0), '')
            ->addChart('chart_commandes', 'Commandes par statut',
                $this->charts->doughnut(
                    array_column($data['commandes_par_statut'] ?? [], 'statut'),
                    array_map(fn($r) => (int)$r['nb'], $data['commandes_par_statut'] ?? []),
                ))
            ->addTable('table_alertes', 'Articles en alerte stock',
                $data['alertes_stock'] ?? [],
                [['key' => 'designation', 'label' => 'Article'], ['key' => 'stock_total', 'label' => 'Stock'], ['key' => 'seuil_alerte', 'label' => 'Seuil']])
            ->addTable('table_maintenance', 'Coûts maintenance',
                $data['couts_maintenance'] ?? [],
                [['key' => 'designation', 'label' => 'Article'], ['key' => 'cout_total', 'label' => 'Coût total'], ['key' => 'nb_maintenances', 'label' => 'Nb']])
            ->addAlertes('alertes_inv', $this->tendance->alertesKpi('inventaire', $etab))
            ->build();
    }
}
