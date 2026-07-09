<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\RapportModel;
use App\Models\PeriodeModel;
use App\Models\ClasseModel;

class RapportController extends Controller
{
    private RapportModel $rapport;
    private PeriodeModel $periodeModel;
    private ClasseModel  $classeModel;

    public function __construct()
    {
        parent::__construct();
        $this->rapport      = new RapportModel();
        $this->periodeModel = new PeriodeModel();
        $this->classeModel  = new ClasseModel();
    }

    // ─── Dashboard analytique ────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('rapports.view');

        $annee     = $this->request->get('annee', RapportModel::currentAnnee());
        $periodeId = (int)$this->request->get('periode_id', 0) ?: null;

        $kpiGlobal   = $this->rapport->getKpiGlobal();
        $kpiFinance  = $this->tryGet(fn() => $this->rapport->getKpiFinance($annee));
        $kpiReussite = $this->tryGet(fn() => $this->rapport->getKpiReussite($periodeId), ['total'=>0,'reussis'=>0,'taux_reussite'=>0,'moy_generale'=>0]);

        $elevesParClasse   = $this->tryGet(fn() => $this->rapport->getElevesParClasse(), []);
        $absencesHebdo     = $this->tryGet(fn() => $this->rapport->getAbsencesHebdo(12), []);
        $financeParMois    = $this->tryGet(fn() => $this->rapport->getFinanceParMois($annee), []);
        $reussiteParClasse = $this->tryGet(fn() => $this->rapport->getReussiteParClasse($periodeId), []);
        $mentionsDistrib   = $this->tryGet(fn() => $this->rapport->getMentionsDistrib($periodeId), []);

        $this->render('rapports/index', [
            'title'             => 'Rapports & Analytiques',
            'annee'             => $annee,
            'annees'            => RapportModel::anneesOptions(),
            'periodes'          => $this->periodeModel->findAll('id'),
            'periodeId'         => $periodeId,
            'kpiGlobal'         => $kpiGlobal,
            'kpiFinance'        => $kpiFinance,
            'kpiReussite'       => $kpiReussite,
            'elevesParClasse'   => $elevesParClasse,
            'absencesHebdo'     => $absencesHebdo,
            'financeParMois'    => $financeParMois,
            'reussiteParClasse' => $reussiteParClasse,
            'mentionsDistrib'   => $mentionsDistrib,
        ]);
    }

    // ─── Statistiques scolaires ──────────────────────────────────────────────

    public function scolaire(): void
    {
        $this->requirePermission('rapports.view');

        $annee     = $this->request->get('annee', RapportModel::currentAnnee());
        $periodeId = (int)$this->request->get('periode_id', 0) ?: null;

        $kpiGlobal       = $this->rapport->getKpiGlobal();
        $elevesParClasse = $this->tryGet(fn() => $this->rapport->getElevesParClasse(), []);
        $elevesParNiveau = $this->tryGet(fn() => $this->rapport->getElevesParNiveau(), []);
        $distribution    = $this->tryGet(fn() => $this->rapport->getDistributionNotes($periodeId), null);

        $this->render('rapports/scolaire', [
            'title'          => 'Statistiques scolaires',
            'annee'          => $annee,
            'annees'         => RapportModel::anneesOptions(),
            'periodes'       => $this->periodeModel->findAll('id'),
            'periodeId'      => $periodeId,
            'kpiGlobal'      => $kpiGlobal,
            'elevesParClasse'=> $elevesParClasse,
            'elevesParNiveau'=> $elevesParNiveau,
            'distribution'   => $distribution,
        ]);
    }

    // ─── Statistiques financières ────────────────────────────────────────────

    public function financier(): void
    {
        $this->requirePermission('rapports.view');

        $annee = $this->request->get('annee', RapportModel::currentAnnee());

        $kpiFinance     = $this->tryGet(fn() => $this->rapport->getKpiFinance($annee), ['recettes'=>0,'depenses'=>0,'impayes'=>0,'frais_total'=>0,'solde'=>0,'taux_recouvrement'=>0]);
        $financeParMois = $this->tryGet(fn() => $this->rapport->getFinanceParMois($annee), []);
        $depensesCat    = $this->tryGet(fn() => $this->rapport->getDepensesParCategorie($annee), []);
        $recouvrement   = $this->tryGet(fn() => $this->rapport->getRecouvrementParFrais($annee), []);

        $this->render('rapports/financier', [
            'title'          => 'Statistiques financières',
            'annee'          => $annee,
            'annees'         => RapportModel::anneesOptions(),
            'kpiFinance'     => $kpiFinance,
            'financeParMois' => $financeParMois,
            'depensesCat'    => $depensesCat,
            'recouvrement'   => $recouvrement,
        ]);
    }

    // ─── Rapport de présences ────────────────────────────────────────────────

    public function presences(): void
    {
        $this->requirePermission('rapports.view');

        $absencesParClasse = $this->tryGet(fn() => $this->rapport->getAbsencesParClasse(), []);
        $absencesHebdo     = $this->tryGet(fn() => $this->rapport->getAbsencesHebdo(16), []);
        $repartition       = $this->tryGet(fn() => $this->rapport->getRepartitionAbsences(), []);
        $topAbsents        = $this->tryGet(fn() => $this->rapport->getTopAbsents(10), []);

        $this->render('rapports/presences', [
            'title'             => 'Rapport de présences',
            'absencesParClasse' => $absencesParClasse,
            'absencesHebdo'     => $absencesHebdo,
            'repartition'       => $repartition,
            'topAbsents'        => $topAbsents,
        ]);
    }

    // ─── Rapport de réussite ─────────────────────────────────────────────────

    public function reussite(): void
    {
        $this->requirePermission('rapports.view');

        $periodeId = (int)$this->request->get('periode_id', 0) ?: null;
        $classeId  = (int)$this->request->get('classe_id', 0)  ?: null;

        $kpiReussite         = $this->tryGet(fn() => $this->rapport->getKpiReussite($periodeId), ['total'=>0,'reussis'=>0,'taux_reussite'=>0,'moy_generale'=>0]);
        $reussiteParClasse   = $this->tryGet(fn() => $this->rapport->getReussiteParClasse($periodeId), []);
        $mentionsDistrib     = $this->tryGet(fn() => $this->rapport->getMentionsDistrib($periodeId), []);
        $moyennesMatiere     = $this->tryGet(fn() => $this->rapport->getMoyennesParMatiere($periodeId, $classeId), []);
        $progressionPeriodes = $this->tryGet(fn() => $this->rapport->getProgressionParPeriode($classeId), []);

        $this->render('rapports/reussite', [
            'title'               => 'Rapport de réussite',
            'periodes'            => $this->periodeModel->findAll('id'),
            'periodeId'           => $periodeId,
            'classes'             => $this->classeModel->findAll('niveau'),
            'classeId'            => $classeId,
            'kpiReussite'         => $kpiReussite,
            'reussiteParClasse'   => $reussiteParClasse,
            'mentionsDistrib'     => $mentionsDistrib,
            'moyennesMatiere'     => $moyennesMatiere,
            'progressionPeriodes' => $progressionPeriodes,
        ]);
    }

    // ─── Export PDF (via layout print) ───────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requirePermission('rapports.view');

        $annee     = $this->request->get('annee', RapportModel::currentAnnee());
        $periodeId = (int)$this->request->get('periode_id', 0) ?: null;

        $periode = $periodeId ? $this->periodeModel->findById($periodeId) : null;

        $this->render('rapports/print', [
            'title'             => 'Rapport — ' . $annee,
            'annee'             => $annee,
            'periode'           => $periode,
            'kpiGlobal'         => $this->rapport->getKpiGlobal(),
            'kpiFinance'        => $this->tryGet(fn() => $this->rapport->getKpiFinance($annee), ['recettes'=>0,'depenses'=>0,'impayes'=>0,'frais_total'=>0,'solde'=>0,'taux_recouvrement'=>0]),
            'kpiReussite'       => $this->tryGet(fn() => $this->rapport->getKpiReussite($periodeId), ['total'=>0,'reussis'=>0,'taux_reussite'=>0,'moy_generale'=>0]),
            'elevesParClasse'   => $this->tryGet(fn() => $this->rapport->getElevesParClasse(), []),
            'reussiteParClasse' => $this->tryGet(fn() => $this->rapport->getReussiteParClasse($periodeId), []),
            'mentionsDistrib'   => $this->tryGet(fn() => $this->rapport->getMentionsDistrib($periodeId), []),
            'depensesCat'       => $this->tryGet(fn() => $this->rapport->getDepensesParCategorie($annee), []),
            'financeParMois'    => $this->tryGet(fn() => $this->rapport->getFinanceParMois($annee), []),
            'absencesParClasse' => $this->tryGet(fn() => $this->rapport->getAbsencesParClasse(), []),
        ], 'print');
    }

    // ─── Export Excel (CSV UTF-8) ────────────────────────────────────────────

    public function exportExcel(string $type): void
    {
        $this->requirePermission('rapports.view');

        $annee     = $this->request->get('annee', RapportModel::currentAnnee());
        $periodeId = (int)$this->request->get('periode_id', 0) ?: null;

        $filename = 'rapport_' . $type . '_' . date('Ymd') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fprintf($out, "\xEF\xBB\xBF");

        match($type) {
            'scolaire'  => $this->csvScolaire($out),
            'financier' => $this->csvFinancier($out, $annee),
            'presences' => $this->csvPresences($out),
            'reussite'  => $this->csvReussite($out, $periodeId),
            default     => $this->csvScolaire($out),
        };

        fclose($out);
        exit;
    }

    // ─── Générateurs CSV ─────────────────────────────────────────────────────

    private function csvScolaire($out): void
    {
        fputcsv($out, ['Rapport Scolaire — ' . RapportModel::currentAnnee()], ';');
        fputcsv($out, ['Généré le ' . date('d/m/Y H:i')], ';');
        fputcsv($out, [], ';');

        fputcsv($out, ['EFFECTIFS PAR CLASSE'], ';');
        fputcsv($out, ['Classe', 'Niveau', 'Total', 'Garçons', 'Filles'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getElevesParClasse(), []) as $r) {
            fputcsv($out, [$r->nom, $r->niveau, $r->nb_eleves, $r->garcons, $r->filles], ';');
        }
    }

    private function csvFinancier($out, string $annee): void
    {
        fputcsv($out, ['Rapport Financier — ' . $annee], ';');
        fputcsv($out, ['Généré le ' . date('d/m/Y H:i')], ';');
        fputcsv($out, [], ';');

        $kpi = $this->tryGet(fn() => $this->rapport->getKpiFinance($annee), []);
        fputcsv($out, ['RÉSUMÉ'], ';');
        fputcsv($out, ['Recettes totales',   number_format($kpi['recettes']          ?? 0, 0, ',', ' ')], ';');
        fputcsv($out, ['Dépenses totales',   number_format($kpi['depenses']          ?? 0, 0, ',', ' ')], ';');
        fputcsv($out, ['Solde net',          number_format($kpi['solde']             ?? 0, 0, ',', ' ')], ';');
        fputcsv($out, ['Impayés',            number_format($kpi['impayes']           ?? 0, 0, ',', ' ')], ';');
        fputcsv($out, ['Taux recouvrement',  ($kpi['taux_recouvrement'] ?? 0) . '%'], ';');
        fputcsv($out, [], ';');

        fputcsv($out, ['RECOUVREMENT PAR TYPE DE FRAIS'], ';');
        fputcsv($out, ['Type', 'Catégorie', 'À collecter', 'Collecté', 'Nb élèves', 'Taux (%)'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getRecouvrementParFrais($annee), []) as $r) {
            $taux = $r->montant_total > 0 ? round($r->montant_paye / $r->montant_total * 100, 1) : 0;
            fputcsv($out, [$r->nom, $r->categorie, $r->montant_total, $r->montant_paye, $r->nb_eleves, $taux], ';');
        }
        fputcsv($out, [], ';');

        fputcsv($out, ['DÉPENSES PAR CATÉGORIE'], ';');
        fputcsv($out, ['Catégorie', 'Montant'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getDepensesParCategorie($annee), []) as $r) {
            fputcsv($out, [$r->nom, $r->total], ';');
        }
    }

    private function csvPresences($out): void
    {
        fputcsv($out, ['Rapport de Présences'], ';');
        fputcsv($out, ['Généré le ' . date('d/m/Y H:i')], ';');
        fputcsv($out, [], ';');

        fputcsv($out, ['ABSENCES PAR CLASSE'], ';');
        fputcsv($out, ['Classe', 'Niveau', 'Nb élèves', 'Absences', 'Retards', 'Moy/élève'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getAbsencesParClasse(), []) as $r) {
            fputcsv($out, [$r->classe, $r->niveau, $r->nb_eleves, $r->nb_seches, $r->nb_retards, $r->moy_par_eleve], ';');
        }
        fputcsv($out, [], ';');

        fputcsv($out, ['TOP ABSENTS'], ';');
        fputcsv($out, ['Nom', 'Prénom', 'Matricule', 'Classe', 'Nb absences'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getTopAbsents(20), []) as $r) {
            fputcsv($out, [$r->nom, $r->prenom, $r->matricule ?? '', $r->classe ?? '', $r->nb_absences], ';');
        }
    }

    private function csvReussite($out, ?int $periodeId): void
    {
        fputcsv($out, ['Rapport de Réussite'], ';');
        fputcsv($out, ['Généré le ' . date('d/m/Y H:i')], ';');
        fputcsv($out, [], ';');

        fputcsv($out, ['RÉUSSITE PAR CLASSE'], ';');
        fputcsv($out, ['Classe', 'Niveau', 'Total', 'Réussis', 'Taux (%)', 'Moy. classe', 'Max', 'Min'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getReussiteParClasse($periodeId), []) as $r) {
            $taux = $r->total > 0 ? round($r->reussis / $r->total * 100, 1) : 0;
            fputcsv($out, [$r->classe, $r->niveau, $r->total, $r->reussis, $taux, $r->moy_classe, $r->moy_max, $r->moy_min], ';');
        }
        fputcsv($out, [], ';');

        fputcsv($out, ['DISTRIBUTION DES MENTIONS'], ';');
        fputcsv($out, ['Mention', 'Nombre'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getMentionsDistrib($periodeId), []) as $r) {
            fputcsv($out, [$r->mention, $r->nb], ';');
        }
        fputcsv($out, [], ';');

        fputcsv($out, ['MOYENNES PAR MATIÈRE'], ';');
        fputcsv($out, ['Matière', 'Coef.', 'Moyenne', 'Nb élèves', 'Max', 'Min', 'Réussis'], ';');
        foreach ($this->tryGet(fn() => $this->rapport->getMoyennesParMatiere($periodeId), []) as $r) {
            fputcsv($out, [$r->matiere, $r->coefficient, $r->moy_matiere, $r->nb_eleves, $r->moy_max, $r->moy_min, $r->reussis], ';');
        }
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function tryGet(callable $fn, mixed $default = []): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }
}
