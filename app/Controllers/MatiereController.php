<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;

class MatiereController extends Controller
{
    private MatiereModel      $matiereModel;
    private ProfesseurModel   $profModel;
    private EnseignementModel $ensModel;

    public function __construct()
    {
        parent::__construct();
        $this->matiereModel = new MatiereModel();
        $this->profModel    = new ProfesseurModel();
        $this->ensModel     = new EnseignementModel();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LISTE
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('matieres.view');

        $matieres = $this->matiereModel->findWithStats();

        $totalCoef   = array_sum(array_column($matieres, 'coefficient'));
        $totalHeures = array_sum(array_column($matieres, 'volume_horaire'));

        $this->render('matieres/index', [
            'title'       => 'Gestion des matières',
            'matieres'    => $matieres,
            'totalCoef'   => $totalCoef,
            'totalHeures' => $totalHeures,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CRÉER
    // ═══════════════════════════════════════════════════════════════════════════

    public function create(): void
    {
        $this->requirePermission('matieres.create');

        $this->render('matieres/create', [
            'title'      => 'Nouvelle matière',
            'professeurs'=> $this->profModel->findForSelect(),
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('matieres.create');
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $errors = $this->validateMatiere($data);

        if ($this->matiereModel->nomExists($data['nom'])) {
            $errors['nom'][] = 'Une matière avec ce nom existe déjà.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/matieres/create');
            return;
        }

        $id = $this->matiereModel->insert($this->toDbData($data));
        Session::flash('success', 'Matière créée avec succès.');
        $this->redirect(BASE_URL . '/matieres/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DÉTAIL
    // ═══════════════════════════════════════════════════════════════════════════

    public function show(string $id): void
    {
        $this->requirePermission('matieres.view');

        $matiere = $this->matiereModel->findWithDetails((int)$id);
        if (!$matiere) {
            Session::flash('error', 'Matière introuvable.');
            $this->redirect(BASE_URL . '/matieres');
            return;
        }

        $enseignements = $this->ensModel->findByMatiereId((int)$id);

        $this->render('matieres/show', [
            'title'        => $matiere->nom,
            'matiere'      => $matiere,
            'enseignements'=> $enseignements,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MODIFIER
    // ═══════════════════════════════════════════════════════════════════════════

    public function edit(string $id): void
    {
        $this->requirePermission('matieres.edit');

        $matiere = $this->matiereModel->findById((int)$id);
        if (!$matiere) {
            Session::flash('error', 'Matière introuvable.');
            $this->redirect(BASE_URL . '/matieres');
            return;
        }

        $this->render('matieres/edit', [
            'title'      => 'Modifier — ' . $matiere->nom,
            'matiere'    => $matiere,
            'professeurs'=> $this->profModel->findForSelect(),
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('matieres.edit');
        $this->verifyCsrf();

        $matiere = $this->matiereModel->findById((int)$id);
        if (!$matiere) {
            $this->redirect(BASE_URL . '/matieres');
            return;
        }

        $data   = $this->collectFormData();
        $errors = $this->validateMatiere($data);

        if ($this->matiereModel->nomExists($data['nom'], (int)$id)) {
            $errors['nom'][] = 'Une autre matière avec ce nom existe déjà.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/matieres/' . $id . '/edit');
            return;
        }

        $this->matiereModel->update((int)$id, $this->toDbData($data));
        Session::flash('success', 'Matière modifiée avec succès.');
        $this->redirect(BASE_URL . '/matieres/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SUPPRIMER
    // ═══════════════════════════════════════════════════════════════════════════

    public function delete(string $id): void
    {
        $this->requirePermission('matieres.delete');
        $this->verifyCsrf();

        $matiere = $this->matiereModel->findById((int)$id);
        if (!$matiere) {
            $this->redirect(BASE_URL . '/matieres');
            return;
        }

        $enseignements = $this->ensModel->findByMatiereId((int)$id);
        if (!empty($enseignements)) {
            Session::flash('error', 'Impossible de supprimer : cette matière est utilisée dans ' . count($enseignements) . ' enseignement(s).');
            $this->redirect(BASE_URL . '/matieres/' . $id);
            return;
        }

        $this->matiereModel->delete((int)$id);
        Session::flash('success', 'Matière supprimée.');
        $this->redirect(BASE_URL . '/matieres');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════════════════════

    private function collectFormData(): array
    {
        return [
            'nom'            => trim($this->request->post('nom', '')),
            'coefficient'    => $this->request->post('coefficient', '2'),
            'volume_horaire' => (int)$this->request->post('volume_horaire', 2),
            'responsable_id' => $this->request->post('responsable_id', '') ?: null,
            'description'    => trim($this->request->post('description', '')) ?: null,
        ];
    }

    private function toDbData(array $data): array
    {
        return [
            'nom'            => $data['nom'],
            'coefficient'    => (float)str_replace(',', '.', $data['coefficient']),
            'volume_horaire' => $data['volume_horaire'],
            'responsable_id' => $data['responsable_id'] ? (int)$data['responsable_id'] : null,
            'description'    => $data['description'],
        ];
    }

    private function validateMatiere(array $data): array
    {
        $errors = [];

        if (empty($data['nom'])) {
            $errors['nom'][] = 'Le nom de la matière est obligatoire.';
        } elseif (strlen($data['nom']) > 100) {
            $errors['nom'][] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        $coef = (float)str_replace(',', '.', $data['coefficient']);
        if ($coef < 0.5 || $coef > 20) {
            $errors['coefficient'][] = 'Le coefficient doit être entre 0.5 et 20.';
        }

        if ($data['volume_horaire'] < 1 || $data['volume_horaire'] > 30) {
            $errors['volume_horaire'][] = 'Le volume horaire doit être entre 1 et 30 h/semaine.';
        }

        return $errors;
    }
}
