<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\PaiementModel;
use App\Models\DepenseModel;
use App\Models\FraisTypeModel;
use App\Models\FraisEleveModel;
use App\Models\ClasseModel;
use App\Models\EleveModel;

class ComptabiliteController extends Controller
{
    private PaiementModel    $paiModel;
    private DepenseModel     $depModel;
    private FraisTypeModel   $fraisModel;
    private FraisEleveModel  $feModel;
    private ClasseModel      $classeModel;

    public function __construct()
    {
        parent::__construct();
        $this->paiModel   = new PaiementModel();
        $this->depModel   = new DepenseModel();
        $this->fraisModel = new FraisTypeModel();
        $this->feModel    = new FraisEleveModel();
        $this->classeModel= new ClasseModel();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function anneeNum(string $annee): int
    {
        return (int)explode('-', $annee)[0];
    }

    private function anneesOptions(): array
    {
        $y = (int)date('Y');
        $list = [];
        for ($i = $y - 2; $i <= $y + 1; $i++) {
            $list[] = "{$i}-".($i+1);
        }
        return $list;
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('comptabilite.view');

        $annee    = $this->request->get('annee', $this->currentAnnee());
        $anneeNum = $this->anneeNum($annee);

        $statsPai = $this->paiModel->getStatsGlobales($annee);
        $statsDep = $this->depModel->getStatsGlobales($anneeNum);
        $statsRec = $this->feModel->getStatsRecouvrement($annee);

        // Données Chart.js — mensuel (par année civile)
        $moisNoms    = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $recMois     = array_fill(1, 12, 0.0);
        $depMois     = array_fill(1, 12, 0.0);

        foreach ($this->paiModel->getStatsMensuellesTotales($anneeNum) as $r) {
            $recMois[(int)$r->mois] = (float)$r->total;
        }
        foreach ($this->depModel->getStatsMensuelles($anneeNum) as $r) {
            $depMois[(int)$r->mois] = (float)$r->total;
        }

        $chartBar = [
            'labels'   => array_values($moisNoms),
            'recettes' => array_values($recMois),
            'depenses' => array_values($depMois),
        ];

        $catsDep  = $this->depModel->getStatsParCategorie($anneeNum);
        $chartDep = [
            'labels' => array_map(fn($c) => $c->nom, $catsDep),
            'data'   => array_map(fn($c) => (float)$c->total, $catsDep),
            'colors' => array_map(fn($c) => $c->couleur, $catsDep),
        ];

        $recentPaiements = $this->paiModel->paginateFiltered(1, 6, ['annee' => $annee])['items'];
        $recentDepenses  = $this->depModel->paginateFiltered(1, 6, [])['items'];

        $totalRecettes = (float)($statsPai['total'] ?? 0);
        $totalDepenses = (float)($statsDep['total'] ?? 0);

        $this->render('comptabilite/index', [
            'title'           => 'Finance — Tableau de bord',
            'annee'           => $annee,
            'anneeNum'        => $anneeNum,
            'anneesOptions'   => $this->anneesOptions(),
            'statsPai'        => $statsPai,
            'statsDep'        => $statsDep,
            'statsRec'        => $statsRec,
            'totalRecettes'   => $totalRecettes,
            'totalDepenses'   => $totalDepenses,
            'soldeNet'        => $totalRecettes - $totalDepenses,
            'chartBar'        => $chartBar,
            'chartDep'        => $chartDep,
            'recentPaiements' => $recentPaiements,
            'recentDepenses'  => $recentDepenses,
        ]);
    }

    // ─── Frais scolaires ─────────────────────────────────────────────────────

    public function frais(): void
    {
        $this->requirePermission('comptabilite.view');
        $this->render('comptabilite/frais', [
            'title'  => 'Types de frais scolaires',
            'frais'  => $this->fraisModel->findWithStats(),
            'periodes'=> FraisTypeModel::PERIODICITES,
            'old'    => Session::getFlash('old') ?? [],
            'errors' => Session::getFlash('errors') ?? [],
        ]);
    }

    public function storeFrais(): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();

        $data = [
            'nom'            => trim($this->request->post('nom', '')),
            'description'    => trim($this->request->post('description', '')),
            'montant_defaut' => (float)$this->request->post('montant_defaut', 0),
            'periodicite'    => $this->request->post('periodicite', 'annuel'),
            'actif'          => 1,
        ];

        if (!$data['nom'] || $data['montant_defaut'] < 0) {
            Session::flash('error', 'Nom et montant requis.');
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/comptabilite/frais');
            return;
        }

        $this->fraisModel->insert($data);
        Session::flash('success', 'Type de frais créé.');
        $this->redirect(BASE_URL . '/comptabilite/frais');
    }

    public function editFrais(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $frais = $this->fraisModel->findById((int)$id);
        if (!$frais) {
            Session::flash('error', 'Frais introuvable.'); $this->redirect(BASE_URL.'/comptabilite/frais'); return;
        }
        $this->render('comptabilite/frais_form', [
            'title'   => 'Modifier le type de frais',
            'frais'   => $frais,
            'periodes'=> FraisTypeModel::PERIODICITES,
        ]);
    }

    public function updateFrais(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();

        $this->fraisModel->update((int)$id, [
            'nom'            => trim($this->request->post('nom', '')),
            'description'    => trim($this->request->post('description', '')),
            'montant_defaut' => (float)$this->request->post('montant_defaut', 0),
            'periodicite'    => $this->request->post('periodicite', 'annuel'),
            'actif'          => (int)(bool)$this->request->post('actif', 1),
        ]);
        Session::flash('success', 'Type de frais mis à jour.');
        $this->redirect(BASE_URL . '/comptabilite/frais');
    }

    public function deleteFrais(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();
        $this->fraisModel->delete((int)$id);
        Session::flash('success', 'Type de frais supprimé.');
        $this->redirect(BASE_URL . '/comptabilite/frais');
    }

    // ─── Affectation ─────────────────────────────────────────────────────────

    public function affecter(): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->render('comptabilite/affecter', [
            'title'        => 'Affecter des frais',
            'fraisTypes'   => $this->fraisModel->findAllActifs(),
            'classes'      => $this->classeModel->findAll('niveau ASC, nom ASC'),
            'anneesOptions'=> $this->anneesOptions(),
            'currentAnnee' => $this->currentAnnee(),
            'old'          => Session::getFlash('old') ?? [],
        ]);
    }

    public function storeAffectation(): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();

        $fraisTypeId = (int)$this->request->post('frais_type_id', 0);
        $cible       = $this->request->post('cible', 'classe'); // 'classe' | 'tous'
        $classeId    = (int)$this->request->post('classe_id', 0);
        $montant     = (float)$this->request->post('montant', 0);
        $echeance    = $this->request->post('echeance') ?: null;
        $annee       = $this->request->post('annee_scolaire', $this->currentAnnee());

        if (!$fraisTypeId || $montant <= 0) {
            Session::flash('error', 'Type de frais et montant requis.');
            $this->redirect(BASE_URL . '/comptabilite/frais/affecter');
            return;
        }

        if ($cible === 'tous') {
            $n = $this->feModel->affecterTous($fraisTypeId, $montant, $echeance, $annee);
        } elseif ($classeId) {
            $n = $this->feModel->affecterClasse($classeId, $fraisTypeId, $montant, $echeance, $annee);
        } else {
            Session::flash('error', 'Sélectionnez une classe ou "Tous les élèves".');
            $this->redirect(BASE_URL . '/comptabilite/frais/affecter');
            return;
        }

        Session::flash('success', "Frais affectés à {$n} élève(s) avec succès.");
        $this->redirect(BASE_URL . '/comptabilite/impayes?annee=' . urlencode($annee));
    }

    // ─── Impayés ─────────────────────────────────────────────────────────────

    public function impayes(): void
    {
        $this->requirePermission('comptabilite.view');

        $annee  = $this->request->get('annee', $this->currentAnnee());
        $filters= [
            'annee'        => $annee,
            'classe_id'    => $this->request->get('classe_id', ''),
            'frais_type_id'=> $this->request->get('frais_type_id', ''),
        ];
        $impayes = $this->feModel->findImpayes($filters);
        $totalReste = array_sum(array_column($impayes, 'reste'));

        $this->render('comptabilite/impayes', [
            'title'        => 'Impayés',
            'impayes'      => $impayes,
            'totalReste'   => $totalReste,
            'filters'      => $filters,
            'classes'      => $this->classeModel->findAll('niveau ASC, nom ASC'),
            'fraisTypes'   => $this->fraisModel->findAllActifs(),
            'anneesOptions'=> $this->anneesOptions(),
        ]);
    }

    // ─── Caisse ──────────────────────────────────────────────────────────────

    public function caisse(): void
    {
        $this->requirePermission('comptabilite.view');

        $date = $this->request->get('date', date('Y-m-d'));
        $paiements = $this->paiModel->getForDate($date);
        $depenses  = $this->depModel->getForDate($date);

        $totalRecettes = array_sum(array_column($paiements, 'montant'));
        $totalDepenses = array_sum(array_column($depenses, 'montant'));

        $this->render('comptabilite/caisse', [
            'title'        => 'Caisse — ' . date('d/m/Y', strtotime($date)),
            'date'         => $date,
            'paiements'    => $paiements,
            'depenses'     => $depenses,
            'totalRecettes'=> $totalRecettes,
            'totalDepenses'=> $totalDepenses,
            'solde'        => $totalRecettes - $totalDepenses,
            'modes'        => PaiementModel::MODES,
        ]);
    }

    // ─── Rapports ────────────────────────────────────────────────────────────

    public function rapport(): void
    {
        $this->requirePermission('comptabilite.view');

        $anneeNum = (int)$this->request->get('annee', (int)date('Y'));
        $mois     = (int)$this->request->get('mois', 0);
        $type     = $mois ? 'mensuel' : 'annuel';

        $paiements  = $this->paiModel->getForRapport($anneeNum, $mois ?: null);
        $depenses   = $this->depModel->getForRapport($anneeNum, $mois ?: null);
        $catsDep    = $this->depModel->getStatsParCategorie($anneeNum, $mois ?: null);

        $totRec = array_sum(array_column($paiements, 'montant'));
        $totDep = array_sum(array_column($depenses,  'montant'));

        // Groupement par jour (pour rapport mensuel) ou par mois (annuel)
        $parPeriode = [];
        if ($type === 'mensuel') {
            foreach ($paiements as $p) {
                $k = $p->date_paiement;
                $parPeriode[$k]['recettes'] = ($parPeriode[$k]['recettes'] ?? 0) + (float)$p->montant;
            }
            foreach ($depenses as $d) {
                $k = $d->date_depense;
                $parPeriode[$k]['depenses'] = ($parPeriode[$k]['depenses'] ?? 0) + (float)$d->montant;
            }
            ksort($parPeriode);
        } else {
            $moisNoms = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun',
                         '07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
            foreach ($paiements as $p) {
                $k = date('m', strtotime($p->date_paiement));
                $parPeriode[$k]['label']    = $moisNoms[$k] . ' ' . $anneeNum;
                $parPeriode[$k]['recettes'] = ($parPeriode[$k]['recettes'] ?? 0) + (float)$p->montant;
            }
            foreach ($depenses as $d) {
                $k = date('m', strtotime($d->date_depense));
                $parPeriode[$k]['label']    = $moisNoms[$k] . ' ' . $anneeNum;
                $parPeriode[$k]['depenses'] = ($parPeriode[$k]['depenses'] ?? 0) + (float)$d->montant;
            }
            ksort($parPeriode);
        }

        $moisNomsList = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

        $this->render('comptabilite/rapport', [
            'title'       => 'Rapport ' . ($mois ? $moisNomsList[$mois] . ' ' : '') . $anneeNum,
            'anneeNum'    => $anneeNum,
            'mois'        => $mois,
            'type'        => $type,
            'paiements'   => $paiements,
            'depenses'    => $depenses,
            'catsDep'     => $catsDep,
            'totRec'      => $totRec,
            'totDep'      => $totDep,
            'solde'       => $totRec - $totDep,
            'parPeriode'  => $parPeriode,
            'moisNoms'    => $moisNomsList,
            'modes'       => PaiementModel::MODES,
        ]);
    }

    public function rapportPrint(): void
    {
        $this->requirePermission('comptabilite.view');

        $anneeNum = (int)$this->request->get('annee', (int)date('Y'));
        $mois     = (int)$this->request->get('mois', 0);
        $type     = $mois ? 'mensuel' : 'annuel';

        $paiements  = $this->paiModel->getForRapport($anneeNum, $mois ?: null);
        $depenses   = $this->depModel->getForRapport($anneeNum, $mois ?: null);
        $catsDep    = $this->depModel->getStatsParCategorie($anneeNum, $mois ?: null);

        $totRec = array_sum(array_column($paiements, 'montant'));
        $totDep = array_sum(array_column($depenses,  'montant'));

        $moisNomsList = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

        $this->render('comptabilite/rapport_print', [
            'title'    => 'Rapport financier',
            'anneeNum' => $anneeNum,
            'mois'     => $mois,
            'moisNoms' => $moisNomsList,
            'type'     => $type,
            'paiements'=> $paiements,
            'depenses' => $depenses,
            'catsDep'  => $catsDep,
            'totRec'   => $totRec,
            'totDep'   => $totDep,
            'solde'    => $totRec - $totDep,
        ], 'print');
    }

    public function exportExcel(): void
    {
        $this->requirePermission('comptabilite.view');

        $anneeNum = (int)$this->request->get('annee', (int)date('Y'));
        $mois     = (int)$this->request->get('mois', 0);
        $type     = $this->request->get('type', 'paiements');

        $filename = "export_{$type}_{$anneeNum}" . ($mois ? "_m{$mois}" : '') . '.csv';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo "\xEF\xBB\xBF"; // BOM UTF-8

        if ($type === 'paiements') {
            echo implode(';', ['Date','Élève','Matricule','Classe','Frais','Montant','Mode','Référence','Note']) . "\n";
            foreach ($this->paiModel->getForRapport($anneeNum, $mois ?: null) as $p) {
                echo implode(';', [
                    $p->date_paiement,
                    '"' . ($p->eleve_nom    ?? '') . '"',
                    $p->matricule     ?? '',
                    '"' . ($p->classe_niveau ?? '') . ' ' . ($p->classe_nom ?? '') . '"',
                    '"' . ($p->frais_nom    ?? '') . '"',
                    number_format((float)$p->montant, 2, ',', ' '),
                    $p->mode_paiement ?? '',
                    $p->reference     ?? '',
                    '"' . str_replace('"', '""', $p->note ?? '') . '"',
                ]) . "\n";
            }
        } else {
            echo implode(';', ['Date','Catégorie','Libellé','Montant','Mode','Référence']) . "\n";
            foreach ($this->depModel->getForRapport($anneeNum, $mois ?: null) as $d) {
                echo implode(';', [
                    $d->date_depense,
                    '"' . ($d->categorie_nom ?? '') . '"',
                    '"' . str_replace('"', '""', $d->libelle) . '"',
                    number_format((float)$d->montant, 2, ',', ' '),
                    $d->mode_paiement ?? '',
                    $d->reference ?? '',
                ]) . "\n";
            }
        }
        exit;
    }
}
