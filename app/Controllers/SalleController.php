<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\SalleModel;

class SalleController extends Controller
{
    private SalleModel $salleModel;

    public function __construct()
    {
        parent::__construct();
        $this->salleModel = new SalleModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('emploi_du_temps.view');

        $annee = $this->request->get('annee', '') ?: $this->currentAnnee();
        $salles = $this->salleModel->findWithStats($annee);

        $this->render('salles/index', [
            'title'  => 'Gestion des salles',
            'salles' => $salles,
            'types'  => SalleModel::TYPES,
            'annee'  => $annee,
            'old'    => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('emploi_du_temps.create');
        $this->verifyCsrf();

        $data = [
            'nom'         => trim($this->request->post('nom', '')),
            'capacite'    => (int)$this->request->post('capacite', 0),
            'type'        => $this->request->post('type', 'salle_cours'),
            'batiment'    => trim($this->request->post('batiment', '')),
            'description' => trim($this->request->post('description', '')),
            'actif'       => $this->request->post('actif') !== null ? 1 : 0,
        ];

        if (empty($data['nom'])) {
            Session::flash('error', 'Le nom de la salle est requis.');
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/salles');
            return;
        }

        $this->salleModel->insert($data);
        Session::flash('success', 'Salle « ' . htmlspecialchars($data['nom']) . ' » ajoutée.');
        $this->redirect(BASE_URL . '/salles');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');

        $salle = $this->salleModel->findById($id);
        if (!$salle) {
            Session::flash('error', 'Salle introuvable.');
            $this->redirect(BASE_URL . '/salles');
            return;
        }

        $this->render('salles/form', [
            'title' => 'Modifier la salle',
            'salle' => $salle,
            'types' => SalleModel::TYPES,
            'old'   => Session::getFlash('old') ?? (array)$salle,
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');
        $this->verifyCsrf();

        $salle = $this->salleModel->findById($id);
        if (!$salle) {
            Session::flash('error', 'Salle introuvable.');
            $this->redirect(BASE_URL . '/salles');
            return;
        }

        $data = [
            'nom'         => trim($this->request->post('nom', '')),
            'capacite'    => (int)$this->request->post('capacite', 0),
            'type'        => $this->request->post('type', 'salle_cours'),
            'batiment'    => trim($this->request->post('batiment', '')),
            'description' => trim($this->request->post('description', '')),
            'actif'       => $this->request->post('actif') !== null ? 1 : 0,
        ];

        if (empty($data['nom'])) {
            Session::flash('error', 'Le nom est requis.');
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/salles/' . $id . '/edit');
            return;
        }

        $this->salleModel->update($id, $data);
        Session::flash('success', 'Salle mise à jour.');
        $this->redirect(BASE_URL . '/salles');
    }

    public function delete(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');
        $this->verifyCsrf();

        $salle = $this->salleModel->findById($id);
        if (!$salle) {
            Session::flash('error', 'Salle introuvable.');
        } else {
            $this->salleModel->delete($id);
            Session::flash('success', 'Salle supprimée.');
        }
        $this->redirect(BASE_URL . '/salles');
    }
}
