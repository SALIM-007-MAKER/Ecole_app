<?php

namespace App\Controllers;

use Core\Controller;
use Core\EventDispatcher;
use Core\Session;
use App\Events\PaiementValide;
use App\Models\PaiementModel;
use App\Models\FraisEleveModel;
use App\Models\FraisTypeModel;
use App\Models\ClasseModel;
use App\Models\EleveModel;

class PaiementController extends Controller
{
    private PaiementModel   $paiModel;
    private FraisEleveModel $feModel;
    private ClasseModel     $classeModel;
    private EleveModel      $eleveModel;

    public function __construct()
    {
        parent::__construct();
        $this->paiModel   = new PaiementModel();
        $this->feModel    = new FraisEleveModel();
        $this->classeModel= new ClasseModel();
        $this->eleveModel = new EleveModel();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('comptabilite.view');

        $user    = $this->currentUser();
        $filters = [
            'q'          => $this->request->get('q', ''),
            'annee'      => $this->request->get('annee', $this->currentAnnee()),
            'classe_id'  => $this->request->get('classe_id', ''),
            'eleve_id'   => $this->request->get('eleve_id', ''),
            'mode'       => $this->request->get('mode', ''),
            'date_debut' => $this->request->get('date_debut', ''),
            'date_fin'   => $this->request->get('date_fin', ''),
        ];

        // Parent : restreindre à ses enfants
        if ($user['role'] === 'parent') {
            $eleveIds = array_column(
                $this->eleveModel->findBy('parent_id', (int)$user['id']),
                'id'
            );
            // Pour l'instant on récupère tout et on filtre (simple)
            $allPaiements = [];
            foreach ($eleveIds as $eid) {
                $allPaiements = array_merge($allPaiements, $this->paiModel->findByEleve((int)$eid));
            }
            $this->render('paiements/index', [
                'title'   => 'Paiements de mes enfants',
                'result'  => ['items'=>$allPaiements,'total'=>count($allPaiements),'pages'=>1,'currentPage'=>1],
                'filters' => $filters,
                'classes' => [],
                'modes'   => PaiementModel::MODES,
                'isParent'=> true,
            ]);
            return;
        }

        $page   = max(1, (int)$this->request->get('page', 1));
        $result = $this->paiModel->paginateFiltered($page, 25, $filters);

        $this->render('paiements/index', [
            'title'   => 'Paiements',
            'result'  => $result,
            'filters' => $filters,
            'classes' => $this->classeModel->findAll('niveau ASC, nom ASC'),
            'modes'   => PaiementModel::MODES,
        ]);
    }

    // ─── Créer ───────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('comptabilite.create');

        $eleveId = (int)$this->request->get('eleve_id', 0);
        $annee   = $this->request->get('annee', $this->currentAnnee());

        $eleve     = $eleveId ? $this->eleveModel->findById($eleveId) : null;
        $fraisEleve= $eleveId ? $this->feModel->findForEleve($eleveId, $annee) : [];

        $this->render('paiements/form', [
            'title'     => 'Enregistrer un paiement',
            'classes'   => $this->classeModel->findAll('niveau ASC, nom ASC'),
            'eleve'     => $eleve,
            'fraisEleve'=> $fraisEleve,
            'annee'     => $annee,
            'modes'     => PaiementModel::MODES,
            'old'       => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('comptabilite.create');
        $this->verifyCsrf();

        $eleveId      = (int)$this->request->post('eleve_id', 0);
        $fraisEleveId = (int)$this->request->post('frais_eleve_id', 0) ?: null;
        $annee        = $this->request->post('annee_scolaire', $this->currentAnnee());
        $montant      = (float)$this->request->post('montant', 0);
        $date         = $this->request->post('date_paiement', date('Y-m-d'));
        $mode         = $this->request->post('mode_paiement', 'especes');
        $reference    = trim($this->request->post('reference', ''));
        $note         = trim($this->request->post('note', ''));

        if (!$eleveId || $montant <= 0) {
            Session::flash('error', 'Élève et montant obligatoires.');
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/paiements/create');
            return;
        }

        $user = $this->currentUser();
        $id   = $this->paiModel->insert([
            'eleve_id'       => $eleveId,
            'frais_eleve_id' => $fraisEleveId,
            'annee_scolaire' => $annee,
            'montant'        => $montant,
            'date_paiement'  => $date,
            'mode_paiement'  => $mode,
            'reference'      => $reference ?: null,
            'note'           => $note ?: null,
            'encaisse_par'   => (int)$user['id'],
        ]);

        if ($fraisEleveId) {
            $this->feModel->recalculerStatut($fraisEleveId);
        }

        $fraisNom = '';
        if ($fraisEleveId) {
            $fe = $this->feModel->findById($fraisEleveId);
            if ($fe) {
                $ft   = new \App\Models\FraisTypeModel();
                $type = $ft->findById((int)$fe->frais_type_id);
                $fraisNom = $type?->nom ?? '';
            }
        }

        EventDispatcher::dispatch(new PaiementValide(
            paiementId:   $id,
            eleveId:      $eleveId,
            montant:      $montant,
            modePaiement: $mode,
            fraisNom:     $fraisNom,
            fraisEleveId: $fraisEleveId ?? 0,
            encaisseParId:(int)$user['id'],
            reference:    $reference,
        ));

        Session::flash('success', 'Paiement enregistré.');
        $this->redirect(BASE_URL . '/paiements/' . $id);
    }

    // ─── Détail ──────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();
        $paiement = $this->paiModel->findWithDetails((int)$id);
        if (!$paiement) {
            Session::flash('error', 'Paiement introuvable.'); $this->redirect(BASE_URL.'/paiements'); return;
        }

        // Parent : vérifier appartenance
        $user = $this->currentUser();
        if ($user['role'] === 'parent') {
            $eleve = $this->eleveModel->findById($paiement->eleve_id);
            if (!$eleve || (int)$eleve->parent_id !== (int)$user['id']) {
                Session::flash('error', 'Accès non autorisé.'); $this->redirect(BASE_URL.'/paiements'); return;
            }
        }

        if (!$this->can('comptabilite.view') && $user['role'] !== 'parent') {
            Session::flash('error', 'Accès non autorisé.'); $this->redirect(BASE_URL.'/dashboard'); return;
        }

        $this->render('paiements/show', [
            'title'   => 'Détail du paiement #' . $id,
            'paiement'=> $paiement,
            'modes'   => PaiementModel::MODES,
            'canEdit' => $this->can('comptabilite.edit'),
        ]);
    }

    // ─── Reçu (print) ────────────────────────────────────────────────────────

    public function recu(string $id): void
    {
        $this->requireAuth();
        $paiement = $this->paiModel->findWithDetails((int)$id);
        if (!$paiement) {
            Session::flash('error', 'Paiement introuvable.'); $this->redirect(BASE_URL.'/paiements'); return;
        }
        $this->render('paiements/recu', [
            'title'   => 'Reçu #' . $id,
            'paiement'=> $paiement,
            'modes'   => PaiementModel::MODES,
        ], 'print');
    }

    // ─── Supprimer ───────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();

        $paiement = $this->paiModel->findById((int)$id);
        if ($paiement) {
            $feId = $paiement->frais_eleve_id ?? null;
            $this->paiModel->delete((int)$id);
            if ($feId) $this->feModel->recalculerStatut((int)$feId);
            Session::flash('success', 'Paiement supprimé.');
        } else {
            Session::flash('error', 'Paiement introuvable.');
        }
        $this->redirect(BASE_URL . '/paiements');
    }
}
