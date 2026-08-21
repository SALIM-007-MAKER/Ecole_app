<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Controllers;

use App\Modules\VieScolaire\EmploisDuTemps\Models\SalleModel;
use Core\Controller;
use Core\Database;
use Core\Session;

/**
 * Gestion des salles (référentiel du module Emplois du temps V2).
 *
 * Mirroir volontairement simple de l'ancien App\Controllers\SalleController
 * (V1, table `salles`) — même geste pour l'administrateur, cible désormais
 * `vs_edt_salles`, la table que consomme réellement le module V2.
 */
class SalleController extends Controller
{
    public const TYPES = [
        'cours'        => 'Salle de cours',
        'labo'         => 'Laboratoire',
        'sport'        => 'Sport',
        'informatique' => 'Informatique',
        'autre'        => 'Autre',
    ];

    private SalleModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new SalleModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('timetable.view');

        $db = Database::getInstance()->getConnection();
        $salles = $db->query(
            "SELECT s.*, COUNT(cr.id) AS nb_seances
             FROM vs_edt_salles s
             LEFT JOIN vs_edt_creneaux cr ON cr.salle_id = s.id AND cr.deleted_at IS NULL
             GROUP BY s.id
             ORDER BY s.nom"
        )->fetchAll(\PDO::FETCH_OBJ);

        $this->render('VieScolaire::emplois_du_temps/salles/index', [
            'title'  => 'Salles',
            'salles' => $salles,
            'types'  => self::TYPES,
            'old'    => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('timetable.create');
        $this->verifyCsrf();

        $data = [
            'nom'      => trim($_POST['nom'] ?? ''),
            'capacite' => (int)($_POST['capacite'] ?? 30),
            'type'     => in_array($_POST['type'] ?? '', array_keys(self::TYPES), true) ? $_POST['type'] : 'cours',
            'actif'    => isset($_POST['actif']) ? 1 : 0,
        ];

        if ($data['nom'] === '') {
            Session::flash('error', 'Le nom de la salle est requis.');
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/salles');
            return;
        }

        $data['code'] = $this->genererCode($data['nom']);
        $this->model->insert($data);
        Session::flash('success', 'Salle « ' . $data['nom'] . ' » ajoutée.');
        $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/salles');
    }

    public function update(int $id): void
    {
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $salle = $this->model->findById($id);
        if (!$salle) {
            Session::flash('error', 'Salle introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/salles');
            return;
        }

        $data = [
            'nom'      => trim($_POST['nom'] ?? ''),
            'capacite' => (int)($_POST['capacite'] ?? 30),
            'type'     => in_array($_POST['type'] ?? '', array_keys(self::TYPES), true) ? $_POST['type'] : 'cours',
            'actif'    => isset($_POST['actif']) ? 1 : 0,
        ];

        if ($data['nom'] === '') {
            Session::flash('error', 'Le nom de la salle est requis.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/salles');
            return;
        }

        $this->model->update($id, $data);
        Session::flash('success', 'Salle mise à jour.');
        $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/salles');
    }

    public function toggle(int $id): void
    {
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $salle = $this->model->findById($id);
        if ($salle) {
            $this->model->update($id, ['actif' => $salle->actif ? 0 : 1]);
            Session::flash('success', $salle->actif ? 'Salle désactivée.' : 'Salle réactivée.');
        } else {
            Session::flash('error', 'Salle introuvable.');
        }
        $this->redirect(BASE_URL . '/v2/vie-scolaire/emplois-du-temps/salles');
    }

    /** Génère un code court unique à partir du nom (colonne `code`, UNIQUE, NOT NULL). */
    private function genererCode(string $nom): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $nom));
        $base = $base !== '' ? substr($base, 0, 15) : 'SALLE';

        $code = $base;
        $i    = 1;
        while ($this->model->findOneBy('code', $code) !== false) {
            $code = $base . $i;
            $i++;
        }
        return $code;
    }
}
