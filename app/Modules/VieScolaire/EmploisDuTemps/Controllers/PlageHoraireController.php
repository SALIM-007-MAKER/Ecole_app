<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Controllers;

use App\Modules\VieScolaire\EmploisDuTemps\Models\PlageHoraireModel;
use Core\Controller;
use Core\Database;
use Core\Session;

/**
 * Gestion des plages horaires (créneaux du référentiel du module Emplois du
 * temps V2 — `vs_edt_plages_horaires`). Équivalent V2 de l'ancien
 * App\Controllers\CreneauController (V1, table `creneaux`).
 */
class PlageHoraireController extends Controller
{
    private PlageHoraireModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new PlageHoraireModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('timetable.view');

        $db = Database::getInstance()->getConnection();
        $plages = $db->query(
            "SELECT * FROM vs_edt_plages_horaires ORDER BY ordre ASC"
        )->fetchAll(\PDO::FETCH_OBJ);

        $this->render('VieScolaire::emplois_du_temps/plages/index', [
            'title'  => 'Plages horaires',
            'plages' => $plages,
            'old'    => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('timetable.create');
        $this->verifyCsrf();

        $data = [
            'libelle'     => trim($_POST['libelle'] ?? ''),
            'heure_debut' => trim($_POST['heure_debut'] ?? ''),
            'heure_fin'   => trim($_POST['heure_fin'] ?? ''),
            'ordre'       => (int)($_POST['ordre'] ?? $this->prochainOrdre()),
            'actif'       => 1,
        ];

        $errors = [];
        if ($data['libelle'] === '')     $errors[] = 'Le libellé est requis.';
        if ($data['heure_debut'] === '') $errors[] = "L'heure de début est requise.";
        if ($data['heure_fin'] === '')   $errors[] = "L'heure de fin est requise.";
        if ($data['heure_debut'] !== '' && $data['heure_fin'] !== '' && $data['heure_fin'] <= $data['heure_debut']) {
            $errors[] = "L'heure de fin doit être après l'heure de début.";
        }

        if (!empty($errors)) {
            Session::flash('error', implode(' ', $errors));
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/plages');
            return;
        }

        $this->model->insert($data);
        Session::flash('success', 'Plage horaire ajoutée.');
        $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/plages');
    }

    public function update(int $id): void
    {
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $plage = $this->model->findById($id);
        if (!$plage) {
            Session::flash('error', 'Plage horaire introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/plages');
            return;
        }

        $data = [
            'libelle'     => trim($_POST['libelle'] ?? ''),
            'heure_debut' => trim($_POST['heure_debut'] ?? ''),
            'heure_fin'   => trim($_POST['heure_fin'] ?? ''),
            'ordre'       => (int)($_POST['ordre'] ?? $plage->ordre),
            'actif'       => isset($_POST['actif']) ? 1 : 0,
        ];

        $this->model->update($id, $data);
        Session::flash('success', 'Plage horaire mise à jour.');
        $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/plages');
    }

    public function toggle(int $id): void
    {
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $plage = $this->model->findById($id);
        if ($plage) {
            $this->model->update($id, ['actif' => $plage->actif ? 0 : 1]);
            Session::flash('success', $plage->actif ? 'Plage désactivée.' : 'Plage réactivée.');
        } else {
            Session::flash('error', 'Plage horaire introuvable.');
        }
        $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/plages');
    }

    private function prochainOrdre(): int
    {
        $db = Database::getInstance()->getConnection();
        $max = (int)$db->query("SELECT MAX(ordre) FROM vs_edt_plages_horaires")->fetchColumn();
        return $max + 1;
    }
}
