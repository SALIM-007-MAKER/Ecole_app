<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\DepenseModel;
use App\Models\PaiementModel;

class DepenseController extends Controller
{
    private DepenseModel $depModel;

    public function __construct()
    {
        parent::__construct();
        $this->depModel = new DepenseModel();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('comptabilite.view');

        $filters = [
            'q'           => $this->request->get('q', ''),
            'categorie_id'=> $this->request->get('categorie_id', ''),
            'mode'        => $this->request->get('mode', ''),
            'date_debut'  => $this->request->get('date_debut', ''),
            'date_fin'    => $this->request->get('date_fin', ''),
        ];

        $page   = max(1, (int)$this->request->get('page', 1));
        $result = $this->depModel->paginateFiltered($page, 25, $filters);

        $this->render('depenses/index', [
            'title'     => 'Dépenses',
            'result'    => $result,
            'filters'   => $filters,
            'categories'=> $this->depModel->getCategories(),
            'modes'     => PaiementModel::MODES,
        ]);
    }

    // ─── Créer / Éditer ──────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('comptabilite.create');
        $this->render('depenses/form', [
            'title'     => 'Ajouter une dépense',
            'depense'   => null,
            'categories'=> $this->depModel->getCategories(),
            'modes'     => PaiementModel::MODES,
            'old'       => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('comptabilite.create');
        $this->verifyCsrf();

        $data = $this->extractData();
        if (!$data['libelle'] || $data['montant'] <= 0) {
            Session::flash('error', 'Libellé et montant requis.');
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/depenses/create');
            return;
        }
        $data['saisi_par'] = (int)$this->currentUser()['id'];
        $this->depModel->insert($data);
        Session::flash('success', 'Dépense enregistrée.');
        $this->redirect(BASE_URL . '/depenses');
    }

    public function edit(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $depense = $this->depModel->findById((int)$id);
        if (!$depense) {
            Session::flash('error', 'Dépense introuvable.'); $this->redirect(BASE_URL.'/depenses'); return;
        }
        $this->render('depenses/form', [
            'title'     => 'Modifier la dépense',
            'depense'   => $depense,
            'categories'=> $this->depModel->getCategories(),
            'modes'     => PaiementModel::MODES,
            'old'       => [],
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();

        $data = $this->extractData();
        if (!$data['libelle'] || $data['montant'] <= 0) {
            Session::flash('error', 'Libellé et montant requis.');
            $this->redirect(BASE_URL . '/depenses/' . $id . '/edit');
            return;
        }
        $this->depModel->update((int)$id, $data);
        Session::flash('success', 'Dépense mise à jour.');
        $this->redirect(BASE_URL . '/depenses');
    }

    public function delete(string $id): void
    {
        $this->requirePermission('comptabilite.edit');
        $this->verifyCsrf();
        $this->depModel->delete((int)$id);
        Session::flash('success', 'Dépense supprimée.');
        $this->redirect(BASE_URL . '/depenses');
    }

    private function extractData(): array
    {
        return [
            'categorie_id'  => $this->request->post('categorie_id') ?: null,
            'libelle'       => trim($this->request->post('libelle', '')),
            'montant'       => (float)$this->request->post('montant', 0),
            'date_depense'  => $this->request->post('date_depense', date('Y-m-d')),
            'mode_paiement' => $this->request->post('mode_paiement', 'especes'),
            'reference'     => trim($this->request->post('reference', '')) ?: null,
            'note'          => trim($this->request->post('note', '')) ?: null,
        ];
    }
}
