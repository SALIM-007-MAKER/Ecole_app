<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;
use App\Modules\Academique\Services\ConduiteProtectionStatus;

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

        $data     = $this->collectFormData();
        $conduite = !empty($data['est_conduite']);
        $errors   = [];

        if ($conduite) {
            if (!$this->can('academique.conduite.manage')) {
                $errors['est_conduite'][] = 'Créer la matière de Conduite est réservé aux profils autorisés à gérer la Conduite.';
            } elseif ($this->matiereModel->findConduite()) {
                $errors['est_conduite'][] = 'Cet établissement possède déjà une matière de Conduite.';
            }
            // Conduite : coefficient 1 et 0 heure imposés (jamais saisis)
            $data['coefficient']    = '1';
            $data['volume_horaire'] = 0;
        }

        $errors = array_merge($errors, $this->validateMatiere($data, $conduite));

        if ($this->matiereModel->nomExists($data['nom'])) {
            $errors['nom'][] = 'Une matière avec ce nom existe déjà.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/matieres/create');
            return;
        }

        $id = $this->matiereModel->insert($this->toDbData($data) + ['est_conduite' => $conduite ? 1 : null]);
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

        // État de la protection SQL de la Conduite : calculé à chaque affichage, jamais stocké ni supposé
        $protection = null;
        if ((int)($matiere->est_conduite ?? 0) === 1 && $this->can('academique.conduite.manage')) {
            try {
                $protection = ConduiteProtectionStatus::check(\Core\Database::getInstance()->getConnection());
                if (!$protection['ok']) {
                    \Core\Logger::error('CRITIQUE — ' . $protection['message']);
                }
            } catch (\Throwable $e) {
                \Core\Logger::error('Contrôle de la protection de la Conduite impossible : ' . $e->getMessage());
            }
        }

        $this->render('matieres/show', [
            'title'        => $matiere->nom,
            'matiere'      => $matiere,
            'enseignements'=> $enseignements,
            'protection'   => $protection,
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
        if ((int)($matiere->est_conduite ?? 0) === 1) {
            $this->requirePermission('academique.conduite.manage');
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

        $data     = $this->collectFormData();
        $conduite = (int)($matiere->est_conduite ?? 0) === 1;
        if ($conduite) {
            // Matière de Conduite : modifiable seulement par academique.conduite.manage ; coefficient 1 et 0 heure
            // imposés, marqueur jamais modifiable ici (libellé, catégorie, responsable, description restent libres).
            $this->requirePermission('academique.conduite.manage');
            $data['coefficient']    = '1';
            $data['volume_horaire'] = 0;
        }
        $errors = $this->validateMatiere($data, $conduite);

        if ($this->matiereModel->nomExists($data['nom'], (int)$id)) {
            $errors['nom'][] = 'Une autre matière avec ce nom existe déjà.';
        }

        // Coefficient par défaut : s'il change alors que des classes à bulletins publiés/archivés en dépendent (pas de ligne de programme),
        // leurs classements en direct divergeraient des bulletins figés → confirmation explicite exigée (aucun bulletin n'est jamais recalculé).
        $coefChange = !$conduite && empty($errors['coefficient'])
            && abs((float)str_replace(',', '.', (string)$data['coefficient']) - (float)$matiere->coefficient) >= 0.0001;
        $figees = $coefChange ? $this->matiereModel->classesFigeesDependantDuDefaut((int)$id) : [];
        if ($figees !== [] && $this->request->post('confirmer_defaut') !== '1') {
            $liste = implode(', ', array_map(fn($c) => $c['niveau'] . ' ' . $c['nom'], $figees));
            $errors['confirmer_defaut'][] = 'Ce coefficient par défaut est utilisé en direct par des classes dont les bulletins sont publiés ou archivés (' . $liste . '). '
                . 'Les bulletins déjà publiés ne seront pas modifiés, mais les classements et analyses en direct de ces classes changeront. Cochez la case de confirmation pour continuer.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/matieres/' . $id . '/edit');
            return;
        }

        // Traçabilité : nom, nom d'affichage, coefficient et volume par défaut (avant → après), écrite DANS la transaction de la modification
        $suivi  = ['nom', 'nom_affichage', 'coefficient', 'volume_horaire'];
        $avant  = [];
        foreach ($suivi as $c) { $avant[$c] = (string)($matiere->$c ?? ''); }
        $nouveau = $this->toDbData($data);
        $apres = [];
        foreach ($suivi as $c) { $apres[$c] = (string)($nouveau[$c] ?? $avant[$c]); }
        if ($figees !== []) {
            $apres['defaut_confirme_classes_figees'] = implode(', ', array_map(fn($c) => $c['niveau'] . ' ' . $c['nom'], $figees));
        }
        $pdo = \Core\Database::getInstance()->getConnection();
        try {
            $pdo->beginTransaction();
            $this->matiereModel->update((int)$id, $nouveau);
            (new \App\Services\AuditService())->logUpdateStrict($pdo, (int)($this->currentUser()['id'] ?? 0), 'matieres', 'matiere', (int)$id, $avant, $apres);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Session::flash('error', $e instanceof \App\Services\AuditIndisponibleException
                ? 'Le journal d\'audit est indisponible : la modification a été annulée, rien n\'a été écrit.'
                : 'La modification a échoué.');
            $this->redirect(BASE_URL . '/matieres/' . $id . '/edit');
            return;
        }
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

        if ((int)($matiere->est_conduite ?? 0) === 1) {
            Session::flash('error', 'La matière de Conduite ne peut pas être supprimée.');
            $this->redirect(BASE_URL . '/matieres/' . $id);
            return;
        }

        // Une matière déjà utilisée (enseignements, évaluations, notes/moyennes, programmes de classe) ne se supprime JAMAIS physiquement :
        // on la désactive. Les clés étrangères en cascade effaceraient sinon des notes et des moyennes.
        $usages = $this->matiereModel->usages((int)$id);
        if (!empty($usages)) {
            Session::flash('error', 'Impossible de supprimer : cette matière est déjà utilisée (' . implode(', ', $usages) . '). Désactivez-la à la place : ses données sont conservées.');
            $this->redirect(BASE_URL . '/matieres/' . $id);
            return;
        }

        // Une matière au programme d'une classe ne se supprime pas en silence (clé étrangère RESTRICT) : message explicite.
        if (\App\Services\ProgrammeClasseService::disponible()) {
            $stmtProg = \Core\Database::getInstance()->getConnection()->prepare('SELECT COUNT(*) FROM classe_matieres WHERE matiere_id = ?');
            $stmtProg->execute([(int)$id]);
            $nbProg = (int)$stmtProg->fetchColumn();
            if ($nbProg > 0) {
                Session::flash('error', 'Impossible de supprimer : cette matière figure au programme de ' . $nbProg . ' classe(s). Retirez-la d\'abord de ces programmes.');
                $this->redirect(BASE_URL . '/matieres/' . $id);
                return;
            }
        }

        $this->matiereModel->delete((int)$id);
        Session::flash('success', 'Matière supprimée.');
        $this->redirect(BASE_URL . '/matieres');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DÉSACTIVER / RÉACTIVER
    // ═══════════════════════════════════════════════════════════════════════════

    public function desactiver(string $id): void
    {
        $this->changerEtat((int)$id, 0);
    }

    public function reactiver(string $id): void
    {
        $this->changerEtat((int)$id, 1);
    }

    /**
     * Désactive ou réactive une matière. Désactivée : plus proposée pour de NOUVELLES affectations (enseignements, programmes, évaluations) ;
     * affectations, notes, évaluations et bulletins existants restent intacts. Réversible. La Conduite n'est pas concernée.
     */
    private function changerEtat(int $id, int $actif): void
    {
        $this->requirePermission('matieres.edit');
        $this->verifyCsrf();

        $matiere = $this->matiereModel->findById($id);
        if (!$matiere) {
            $this->redirect(BASE_URL . '/matieres');
            return;
        }
        if (!\App\Services\ProgrammeSql::colonneMatiere('actif')) {
            Session::flash('error', 'La désactivation des matières n\'est pas encore activée sur cette installation (migration T076).');
        } elseif ((int)($matiere->est_conduite ?? 0) === 1) {
            Session::flash('error', 'La matière de Conduite ne peut pas être désactivée.');
        } elseif ((int)($matiere->actif ?? 1) === $actif) {
            Session::flash('success', $actif ? 'Cette matière est déjà active.' : 'Cette matière est déjà désactivée.');
        } else {
            $pdo = \Core\Database::getInstance()->getConnection();
            try {
                $pdo->beginTransaction();
                $this->matiereModel->update($id, ['actif' => $actif]);
                (new \App\Services\AuditService())->logStrict($pdo, (int)($this->currentUser()['id'] ?? 0), $actif ? 'matiere_reactivee' : 'matiere_desactivee', 'matieres', 'matiere', $id, ['actif' => (int)($matiere->actif ?? 1)], ['actif' => $actif]);
                $pdo->commit();
                Session::flash('success', $actif ? 'Matière réactivée.' : 'Matière désactivée : ses données sont conservées, elle n\'est plus proposée pour de nouvelles affectations.');
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                Session::flash('error', 'Le journal d\'audit est indisponible ou l\'opération a échoué : rien n\'a été modifié.');
            }
        }
        $this->redirect(BASE_URL . '/matieres/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════════════════════

    private function collectFormData(): array
    {
        return [
            'nom'            => trim($this->request->post('nom', '')),
            'nom_affichage'  => trim((string)$this->request->post('nom_affichage', '')) ?: null,
            'coefficient'    => $this->request->post('coefficient', '2'),
            'volume_horaire' => (int)$this->request->post('volume_horaire', 2),
            'responsable_id' => $this->request->post('responsable_id', '') ?: null,
            'categorie'      => $this->request->post('categorie', 'autre'),
            'description'    => trim($this->request->post('description', '')) ?: null,
            'est_conduite'   => $this->request->post('est_conduite', '') ? 1 : 0,
        ];
    }

    private function toDbData(array $data): array
    {
        $db = [
            'nom'            => $data['nom'],
            'coefficient'    => (float)str_replace(',', '.', $data['coefficient']),
            'volume_horaire' => $data['volume_horaire'],
            'responsable_id' => $data['responsable_id'] ? (int)$data['responsable_id'] : null,
            'categorie'      => $data['categorie'],
            'description'    => $data['description'],
        ];
        // Nom d'affichage propre à l'établissement (migration T076) : écrit seulement si la colonne existe
        if (\App\Services\ProgrammeSql::colonneMatiere('nom_affichage')) {
            $db['nom_affichage'] = $data['nom_affichage'] ?? null;
        }
        return $db;
    }

    private function validateMatiere(array $data, bool $conduite = false): array
    {
        $errors = [];

        if (empty($data['nom'])) {
            $errors['nom'][] = 'Le nom de la matière est obligatoire.';
        } elseif (strlen($data['nom']) > 100) {
            $errors['nom'][] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        if (!empty($data['nom_affichage']) && mb_strlen($data['nom_affichage']) > 100) {
            $errors['nom_affichage'][] = 'Le nom d\'affichage ne doit pas dépasser 100 caractères.';
        }

        $coef = (float)str_replace(',', '.', $data['coefficient']);
        if ($coef < 0.5 || $coef > 20) {
            $errors['coefficient'][] = 'Le coefficient doit être entre 0.5 et 20.';
        }

        if ($conduite) {
            if ($data['volume_horaire'] !== 0) {
                $errors['volume_horaire'][] = 'La Conduite n\'a pas d\'heures de cours (0 h/semaine).';
            }
        } elseif ($data['volume_horaire'] < 1 || $data['volume_horaire'] > 30) {
            $errors['volume_horaire'][] = 'Le volume horaire doit être entre 1 et 30 h/semaine.';
        }

        return $errors;
    }
}
