<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\CreneauModel;

class CreneauController extends Controller
{
    private CreneauModel $creneauModel;

    public function __construct()
    {
        parent::__construct();
        $this->creneauModel = new CreneauModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('emploi_du_temps.view');

        $this->render('creneaux/index', [
            'title'    => 'Créneaux horaires',
            'creneaux' => $this->creneauModel->findAllActifs(),
            'types'    => CreneauModel::TYPES,
            'old'      => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('emploi_du_temps.create');
        $this->verifyCsrf();

        $data = [
            'nom'         => trim($this->request->post('nom', '')),
            'heure_debut' => $this->request->post('heure_debut', ''),
            'heure_fin'   => $this->request->post('heure_fin',   ''),
            'type'        => $this->request->post('type',        'cours'),
            'ordre'       => (int)($this->request->post('ordre', '') ?: $this->creneauModel->getNextOrdre()),
            'actif'       => 1,
        ];

        $errors = [];
        if (empty($data['nom']))         $errors[] = 'Nom requis.';
        if (empty($data['heure_debut'])) $errors[] = 'Heure de début requise.';
        if (empty($data['heure_fin']))   $errors[] = 'Heure de fin requise.';
        if (!empty($data['heure_debut']) && !empty($data['heure_fin'])
            && $data['heure_fin'] <= $data['heure_debut']) {
            $errors[] = 'L\'heure de fin doit être après l\'heure de début.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/creneaux');
            return;
        }

        $this->creneauModel->insert($data);
        Session::flash('success', 'Créneau ajouté.');
        $this->redirect(BASE_URL . '/creneaux');
    }

    public function update(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');
        $this->verifyCsrf();

        $creneau = $this->creneauModel->findById($id);
        if (!$creneau) {
            Session::flash('error', 'Créneau introuvable.');
            $this->redirect(BASE_URL . '/creneaux');
            return;
        }

        $data = [
            'nom'         => trim($this->request->post('nom', '')),
            'heure_debut' => $this->request->post('heure_debut', ''),
            'heure_fin'   => $this->request->post('heure_fin',   ''),
            'type'        => $this->request->post('type',        'cours'),
            'ordre'       => (int)($this->request->post('ordre', '') ?: $creneau->ordre),
            'actif'       => $this->request->post('actif') !== null ? 1 : 0,
        ];

        $this->creneauModel->update($id, $data);
        Session::flash('success', 'Créneau mis à jour.');
        $this->redirect(BASE_URL . '/creneaux');
    }

    public function delete(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');
        $this->verifyCsrf();

        $creneau = $this->creneauModel->findById($id);
        if (!$creneau) {
            Session::flash('error', 'Créneau introuvable.');
        } else {
            $this->creneauModel->delete($id);
            Session::flash('success', 'Créneau supprimé.');
        }
        $this->redirect(BASE_URL . '/creneaux');
    }
}
