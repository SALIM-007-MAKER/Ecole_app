<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\ProfesseurModel;
use App\Models\ClasseModel;
use App\Models\MatiereModel;
use App\Models\EnseignementModel;

class ProfesseurController extends Controller
{
    private ProfesseurModel   $profModel;
    private ClasseModel       $classeModel;
    private MatiereModel      $matiereModel;
    private EnseignementModel $ensModel;

    private string $photoDir;

    public function __construct()
    {
        parent::__construct();
        $this->photoDir     = ROOT_PATH . '/public/uploads/professeurs/';
        $this->profModel    = new ProfesseurModel();
        $this->classeModel  = new ClasseModel();
        $this->matiereModel = new MatiereModel();
        $this->ensModel     = new EnseignementModel();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LISTE
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('enseignants.view');

        $filters = $this->collectFilters();
        $page    = max(1, (int)$this->request->get('page', 1));
        $perPage = 15;

        $pagination = $this->profModel->paginateFiltered($page, $perPage, $filters);
        $all        = $this->profModel->findWithStats();

        $this->render('professeurs/index', [
            'title'      => 'Gestion des enseignants',
            'pagination' => $pagination,
            'filters'    => $filters,
            'grades'     => ProfesseurModel::GRADES,
            'stats'      => [
                'total'      => count($all),
                'actifs'     => count(array_filter($all, fn($p) => $p->actif)),
                'nb_classes' => count(array_unique(array_filter(array_column($all, 'nb_classes')))),
                'certifies'  => count(array_filter($all, fn($p) => str_contains($p->grade ?? '', 'certifié'))),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CRÉER
    // ═══════════════════════════════════════════════════════════════════════════

    public function create(): void
    {
        $this->requirePermission('enseignants.create');

        $this->render('professeurs/create', [
            'title'  => 'Nouvel enseignant',
            'grades' => ProfesseurModel::GRADES,
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('enseignants.create');
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $errors = $this->validateProf($data);

        if (!empty($data['email']) && $this->profModel->emailExists($data['email'])) {
            $errors['email'][] = 'Cet email est déjà utilisé par un autre enseignant.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/professeurs/create');
            return;
        }

        $photoPath = $this->handlePhoto(null);
        $data['photo'] = $photoPath;

        $id = $this->profModel->insert($this->toDbData($data));
        Session::flash('success', 'Enseignant créé avec succès.');
        $this->redirect(BASE_URL . '/professeurs/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DÉTAIL
    // ═══════════════════════════════════════════════════════════════════════════

    public function show(string $id): void
    {
        $currentUser = Session::getUser();
        $hasViewPerm = in_array('enseignants.view', $currentUser['permissions'] ?? [], true);

        // Un enseignant peut consulter sa propre fiche
        $isOwnProfile = false;
        if ($currentUser['role'] === 'enseignant') {
            $ownProf = $this->profModel->findByUserId((int)$currentUser['id']);
            if ($ownProf && $ownProf->id === (int)$id) {
                $isOwnProfile = true;
            }
        }

        if (!$hasViewPerm && !$isOwnProfile) {
            Session::flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL . '/dashboard');
            return;
        }

        $prof = $this->profModel->findWithDetails((int)$id);
        if (!$prof) {
            Session::flash('error', 'Enseignant introuvable.');
            $this->redirect(BASE_URL . '/professeurs');
            return;
        }

        $annee          = date('Y') . '-' . (date('Y') + 1);
        $enseignements  = $this->ensModel->findByProfesseurId((int)$id, $annee);
        $historique     = $this->ensModel->findHistoriqueByProfesseurId((int)$id);
        $classes        = $this->classeModel->findForSelect();
        $matieres       = $this->matiereModel->findForSelect();

        $this->render('professeurs/show', [
            'title'        => $prof->prenom . ' ' . $prof->nom,
            'prof'         => $prof,
            'enseignements'=> $enseignements,
            'historique'   => $historique,
            'classes'      => $classes,
            'matieres'     => $matieres,
            'annee'        => $annee,
            'activeTab'    => $this->request->get('tab', 'infos'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MODIFIER
    // ═══════════════════════════════════════════════════════════════════════════

    public function edit(string $id): void
    {
        $this->requirePermission('enseignants.edit');

        $prof = $this->profModel->findById((int)$id);
        if (!$prof) {
            Session::flash('error', 'Enseignant introuvable.');
            $this->redirect(BASE_URL . '/professeurs');
            return;
        }

        $this->render('professeurs/edit', [
            'title'  => 'Modifier — ' . $prof->prenom . ' ' . $prof->nom,
            'prof'   => $prof,
            'grades' => ProfesseurModel::GRADES,
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('enseignants.edit');
        $this->verifyCsrf();

        $prof = $this->profModel->findById((int)$id);
        if (!$prof) {
            $this->redirect(BASE_URL . '/professeurs');
            return;
        }

        $data   = $this->collectFormData();
        $errors = $this->validateProf($data);

        if (!empty($data['email']) && $this->profModel->emailExists($data['email'], (int)$id)) {
            $errors['email'][] = 'Cet email est déjà utilisé par un autre enseignant.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/professeurs/' . $id . '/edit');
            return;
        }

        $photoPath = $this->handlePhoto($prof->photo ?? null);
        $data['photo'] = $photoPath;

        $this->profModel->update((int)$id, $this->toDbData($data));
        Session::flash('success', 'Enseignant modifié avec succès.');
        $this->redirect(BASE_URL . '/professeurs/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SUPPRIMER
    // ═══════════════════════════════════════════════════════════════════════════

    public function delete(string $id): void
    {
        $this->requirePermission('enseignants.delete');
        $this->verifyCsrf();

        $prof = $this->profModel->findById((int)$id);
        if (!$prof) {
            $this->redirect(BASE_URL . '/professeurs');
            return;
        }

        $nbEns = count($this->ensModel->findByProfesseurId((int)$id));
        if ($nbEns > 0) {
            Session::flash('error', "Impossible de supprimer : {$nbEns} enseignement(s) lié(s). Retirez-les d'abord.");
            $this->redirect(BASE_URL . '/professeurs/' . $id);
            return;
        }

        if (!empty($prof->photo) && file_exists(ROOT_PATH . '/public/' . $prof->photo)) {
            unlink(ROOT_PATH . '/public/' . $prof->photo);
        }

        $this->profModel->delete((int)$id);
        Session::flash('success', 'Enseignant supprimé.');
        $this->redirect(BASE_URL . '/professeurs');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AFFECTATION ENSEIGNEMENTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function affecterEnseignement(string $id): void
    {
        $this->requirePermission('enseignants.edit');
        $this->verifyCsrf();

        $profId   = (int)$id;
        $matId    = (int)$this->request->post('matiere_id', 0);
        $classeId = (int)$this->request->post('classe_id', 0);
        $annee    = trim($this->request->post('annee_scolaire', date('Y') . '-' . (date('Y') + 1)));

        if ($profId && $matId && $classeId && $annee) {
            if ($this->ensModel->exists($profId, $matId, $classeId, $annee)) {
                Session::flash('error', 'Cet enseignement existe déjà pour cette classe et cette année.');
            } else {
                $this->ensModel->insert([
                    'professeur_id'  => $profId,
                    'matiere_id'     => $matId,
                    'classe_id'      => $classeId,
                    'annee_scolaire' => $annee,
                ]);
                Session::flash('success', 'Enseignement affecté avec succès.');
            }
        }

        $this->redirect(BASE_URL . '/professeurs/' . $id . '?tab=enseignements');
    }

    public function retirerEnseignement(string $id): void
    {
        $this->requirePermission('enseignants.edit');
        $this->verifyCsrf();

        $ensId  = (int)$this->request->post('enseignement_id', 0);
        $profId = (int)$id;

        $ens = $this->ensModel->findById($ensId);
        if ($ens && $ens->professeur_id == $profId) {
            $this->ensModel->delete($ensId);
            Session::flash('success', 'Enseignement retiré.');
        }

        $this->redirect(BASE_URL . '/professeurs/' . $id . '?tab=enseignements');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════════════════════

    private function collectFilters(): array
    {
        return [
            'q'     => trim($this->request->get('q', '')),
            'grade' => $this->request->get('grade', ''),
            'actif' => $this->request->get('actif', ''),
        ];
    }

    private function collectFormData(): array
    {
        return [
            'nom'             => strtoupper(trim($this->request->post('nom', ''))),
            'prenom'          => ucwords(mb_strtolower(trim($this->request->post('prenom', '')))),
            'specialite'      => trim($this->request->post('specialite', '')),
            'grade'           => trim($this->request->post('grade', '')) ?: null,
            'telephone'       => trim($this->request->post('telephone', '')) ?: null,
            'email'           => strtolower(trim($this->request->post('email', ''))) ?: null,
            'adresse'         => trim($this->request->post('adresse', '')) ?: null,
            'date_recrutement'=> trim($this->request->post('date_recrutement', '')) ?: null,
            'actif'           => (int)$this->request->post('actif', 1),
            'photo'           => null,
        ];
    }

    private function toDbData(array $data): array
    {
        return [
            'nom'             => $data['nom'],
            'prenom'          => $data['prenom'],
            'specialite'      => $data['specialite'],
            'grade'           => $data['grade'],
            'telephone'       => $data['telephone'],
            'email'           => $data['email'],
            'adresse'         => $data['adresse'],
            'date_recrutement'=> $data['date_recrutement'],
            'actif'           => $data['actif'],
            'photo'           => $data['photo'],
        ];
    }

    private function validateProf(array $data): array
    {
        $errors = [];

        if (empty($data['nom'])) {
            $errors['nom'][] = 'Le nom est obligatoire.';
        }

        if (empty($data['prenom'])) {
            $errors['prenom'][] = 'Le prénom est obligatoire.';
        }

        if (empty($data['specialite'])) {
            $errors['specialite'][] = 'La spécialité est obligatoire.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = "L'adresse email n'est pas valide.";
        }

        if (!empty($data['date_recrutement'])) {
            $d = \DateTime::createFromFormat('Y-m-d', $data['date_recrutement']);
            if (!$d) {
                $errors['date_recrutement'][] = 'Date invalide (format attendu : AAAA-MM-JJ).';
            }
        }

        return $errors;
    }

    private function handlePhoto(?string $existingPath): ?string
    {
        if (empty($_FILES['photo']['tmp_name'])) {
            return $existingPath;
        }

        $file     = $_FILES['photo'];
        $maxSize  = 3 * 1024 * 1024;
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mimeType = mime_content_type($file['tmp_name']);

        if ($file['size'] > $maxSize || !in_array($mimeType, $allowed, true)) {
            return $existingPath;
        }

        if (!is_dir($this->photoDir)) {
            mkdir($this->photoDir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'prof_' . uniqid() . '.' . strtolower($ext);
        $dest     = $this->photoDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            if ($existingPath && file_exists(ROOT_PATH . '/public/' . $existingPath)) {
                unlink(ROOT_PATH . '/public/' . $existingPath);
            }
            return 'uploads/professeurs/' . $filename;
        }

        return $existingPath;
    }
}
