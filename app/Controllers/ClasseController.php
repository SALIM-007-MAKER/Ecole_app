<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;

class ClasseController extends Controller
{
    private ClasseModel      $classeModel;
    private EleveModel       $eleveModel;
    private MatiereModel     $matiereModel;
    private ProfesseurModel  $profModel;
    private EnseignementModel $ensModel;

    public function __construct()
    {
        parent::__construct();
        $this->classeModel  = new ClasseModel();
        $this->eleveModel   = new EleveModel();
        $this->matiereModel = new MatiereModel();
        $this->profModel    = new ProfesseurModel();
        $this->ensModel     = new EnseignementModel();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LISTE
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('classes.view');

        $classes = $this->classeModel->findWithStats();

        $this->render('classes/index', [
            'title'   => 'Gestion des classes',
            'classes' => $classes,
            'stats'   => [
                'total'          => count($classes),
                'total_eleves'   => array_sum(array_column($classes, 'nb_eleves')),
                'total_ens'      => array_sum(array_column($classes, 'nb_enseignements')),
                'niveaux'        => count(array_unique(array_column($classes, 'niveau'))),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CRÉER
    // ═══════════════════════════════════════════════════════════════════════════

    public function create(): void
    {
        $this->requirePermission('classes.create');

        $this->render('classes/create', [
            'title'   => 'Nouvelle classe',
            'niveaux' => ClasseModel::NIVEAUX,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('classes.create');
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $errors = $this->validateClasse($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/classes/create');
            return;
        }

        $id = $this->classeModel->insert($data);
        Session::flash('success', 'Classe créée avec succès.');
        $this->redirect(BASE_URL . '/classes/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DÉTAIL
    // ═══════════════════════════════════════════════════════════════════════════

    public function show(string $id): void
    {
        $this->requirePermission('classes.view');

        $classe = $this->classeModel->findWithDetails((int)$id);
        if (!$classe) {
            Session::flash('error', 'Classe introuvable.');
            $this->redirect(BASE_URL . '/classes');
            return;
        }

        $eleves     = $this->classeModel->getEleves((int)$id);
        $disponibles = $this->classeModel->getElevesDisponibles((int)$id);
        $enseignements = $this->ensModel->findByClasseId((int)$id);
        $matieres   = $this->matiereModel->findForSelect();
        $professeurs = $this->profModel->findForSelect();
        $annee      = date('Y') . '-' . (date('Y') + 1);

        $this->render('classes/show', [
            'title'        => $classe->niveau . ' ' . $classe->nom,
            'classe'       => $classe,
            'eleves'       => $eleves,
            'disponibles'  => $disponibles,
            'enseignements'=> $enseignements,
            'matieres'     => $matieres,
            'professeurs'  => $professeurs,
            'annee'        => $annee,
            'activeTab'    => $_GET['tab'] ?? 'eleves',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MODIFIER
    // ═══════════════════════════════════════════════════════════════════════════

    public function edit(string $id): void
    {
        $this->requirePermission('classes.edit');

        $classe = $this->classeModel->findById((int)$id);
        if (!$classe) {
            Session::flash('error', 'Classe introuvable.');
            $this->redirect(BASE_URL . '/classes');
            return;
        }

        $this->render('classes/edit', [
            'title'   => 'Modifier — ' . $classe->niveau . ' ' . $classe->nom,
            'classe'  => $classe,
            'niveaux' => ClasseModel::NIVEAUX,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        $classe = $this->classeModel->findById((int)$id);
        if (!$classe) {
            $this->redirect(BASE_URL . '/classes');
            return;
        }

        $data   = $this->collectFormData();
        $errors = $this->validateClasse($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/classes/' . $id . '/edit');
            return;
        }

        $this->classeModel->update((int)$id, $data);
        Session::flash('success', 'Classe modifiée avec succès.');
        $this->redirect(BASE_URL . '/classes/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SUPPRIMER
    // ═══════════════════════════════════════════════════════════════════════════

    public function delete(string $id): void
    {
        $this->requirePermission('classes.delete');
        $this->verifyCsrf();

        $classe = $this->classeModel->findById((int)$id);
        if (!$classe) {
            $this->redirect(BASE_URL . '/classes');
            return;
        }

        $nbEleves = count($this->classeModel->getEleves((int)$id));
        if ($nbEleves > 0) {
            Session::flash('error', "Impossible de supprimer : {$nbEleves} élève(s) sont affecté(s) à cette classe.");
            $this->redirect(BASE_URL . '/classes/' . $id);
            return;
        }

        $this->classeModel->delete((int)$id);
        Session::flash('success', 'Classe supprimée.');
        $this->redirect(BASE_URL . '/classes');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AFFECTATION ÉLÈVES
    // ═══════════════════════════════════════════════════════════════════════════

    public function affecterEleve(string $id): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        $classeId = (int)$id;
        $eleveId  = (int)($this->request->post('eleve_id', 0));

        $classe = $this->classeModel->findById($classeId);
        $eleve  = $this->eleveModel->findById($eleveId);

        if ($classe && $eleve) {
            $maxEleves = (int)$classe->max_eleves;
            $nbActuels = count($this->classeModel->getEleves($classeId));

            if ($maxEleves > 0 && $nbActuels >= $maxEleves) {
                Session::flash('error', "Capacité maximale ({$maxEleves} élèves) atteinte pour cette classe.");
            } else {
                $this->eleveModel->update($eleveId, ['classe_id' => $classeId]);
                Session::flash('success', htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) . ' affecté(e) à cette classe.');
            }
        }

        $this->redirect(BASE_URL . '/classes/' . $id . '?tab=eleves');
    }

    public function retirerEleve(string $id): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        $eleveId = (int)($this->request->post('eleve_id', 0));
        $eleve   = $this->eleveModel->findById($eleveId);

        if ($eleve && $eleve->classe_id == (int)$id) {
            $this->eleveModel->update($eleveId, ['classe_id' => null]);
            Session::flash('success', htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) . ' retiré(e) de la classe.');
        }

        $this->redirect(BASE_URL . '/classes/' . $id . '?tab=eleves');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AFFECTATION ENSEIGNANTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function affecterEnseignant(string $id): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        $classeId = (int)$id;
        $profId   = (int)($this->request->post('professeur_id', 0));
        $matId    = (int)($this->request->post('matiere_id', 0));
        $annee    = trim($this->request->post('annee_scolaire', date('Y') . '-' . (date('Y') + 1)));

        if ($profId && $matId && $annee) {
            if ($this->ensModel->exists($profId, $matId, $classeId, $annee)) {
                Session::flash('error', 'Cet enseignement est déjà affecté à cette classe pour cette année.');
            } else {
                $this->ensModel->insert([
                    'professeur_id'  => $profId,
                    'matiere_id'     => $matId,
                    'classe_id'      => $classeId,
                    'annee_scolaire' => $annee,
                ]);
                Session::flash('success', 'Enseignement ajouté avec succès.');
            }
        }

        $this->redirect(BASE_URL . '/classes/' . $id . '?tab=enseignants');
    }

    public function retirerEnseignant(string $id): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        $ensId    = (int)($this->request->post('enseignement_id', 0));
        $classeId = (int)$id;

        $ens = $this->ensModel->findById($ensId);
        if ($ens && $ens->classe_id == $classeId) {
            $this->ensModel->delete($ensId);
            Session::flash('success', 'Enseignement retiré.');
        }

        $this->redirect(BASE_URL . '/classes/' . $id . '?tab=enseignants');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════════════════════

    private function collectFormData(): array
    {
        return [
            'nom'            => strtoupper(trim($this->request->post('nom', ''))),
            'niveau'         => trim($this->request->post('niveau', '')),
            'annee_scolaire' => trim($this->request->post('annee_scolaire', '')),
            'max_eleves'     => (int)$this->request->post('max_eleves', 40),
            'description'    => trim($this->request->post('description', '')) ?: null,
        ];
    }

    private function validateClasse(array $data): array
    {
        $errors = [];

        if (empty($data['nom'])) {
            $errors['nom'][] = 'Le nom de la classe est obligatoire.';
        } elseif (strlen($data['nom']) > 10) {
            $errors['nom'][] = 'Le nom ne doit pas dépasser 10 caractères.';
        }

        if (empty($data['niveau'])) {
            $errors['niveau'][] = 'Le niveau est obligatoire.';
        }

        if (empty($data['annee_scolaire'])) {
            $errors['annee_scolaire'][] = "L'année scolaire est obligatoire.";
        } elseif (!preg_match('/^\d{4}-\d{4}$/', $data['annee_scolaire'])) {
            $errors['annee_scolaire'][] = "Format invalide. Utilisez YYYY-YYYY (ex: 2024-2025).";
        }

        if ($data['max_eleves'] < 1 || $data['max_eleves'] > 100) {
            $errors['max_eleves'][] = 'La capacité doit être entre 1 et 100.';
        }

        return $errors;
    }
}
