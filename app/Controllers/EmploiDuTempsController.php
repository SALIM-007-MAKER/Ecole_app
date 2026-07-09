<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\EmploiDuTempsModel;
use App\Models\SalleModel;
use App\Models\CreneauModel;
use App\Models\ClasseModel;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Models\EleveModel;

class EmploiDuTempsController extends Controller
{
    private EmploiDuTempsModel $edtModel;
    private SalleModel         $salleModel;
    private CreneauModel       $creneauModel;
    private ClasseModel        $classeModel;
    private ProfesseurModel    $profModel;
    private MatiereModel       $matiereModel;

    public function __construct()
    {
        parent::__construct();
        $this->edtModel     = new EmploiDuTempsModel();
        $this->salleModel   = new SalleModel();
        $this->creneauModel = new CreneauModel();
        $this->classeModel  = new ClasseModel();
        $this->profModel    = new ProfesseurModel();
        $this->matiereModel = new MatiereModel();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────



    private function anneesOptions(): array
    {
        $base = (int)date('m') >= 9 ? (int)date('Y') : (int)date('Y') - 1;
        $opts = [];
        for ($i = $base - 2; $i <= $base + 1; $i++) {
            $opts[] = "$i-" . ($i + 1);
        }
        return $opts;
    }

    private function getRoleFilters(): array
    {
        $user    = $this->currentUser();
        $filters = [];

        switch ($user['role']) {
            case 'enseignant':
                $prof = $this->profModel->findByUserId($user['id']);
                if ($prof) $filters['professeur_id'] = $prof->id;
                break;

            case 'eleve':
                $eleveModel = new EleveModel();
                $eleve      = $eleveModel->findOneBy('email', $user['email']);
                if ($eleve) {
                    // Try direct classe_id on eleve record first
                    if (!empty($eleve->classe_id)) {
                        $filters['classe_id'] = $eleve->classe_id;
                    } else {
                        // Fallback: lookup via classe_eleves join table
                        $r = $this->edtModel->queryOne(
                            'SELECT classe_id FROM classe_eleves WHERE eleve_id = ? LIMIT 1',
                            [$eleve->id]
                        );
                        if ($r) $filters['classe_id'] = $r->classe_id;
                    }
                }
                break;

            case 'parent':
                $eleveModel = new EleveModel();
                $children   = $eleveModel->findBy('parent_id', $user['id']);
                if (!empty($children)) {
                    $c = $children[0];
                    if (!empty($c->classe_id)) {
                        $filters['classe_id'] = $c->classe_id;
                    } else {
                        $r = $this->edtModel->queryOne(
                            'SELECT classe_id FROM classe_eleves WHERE eleve_id = ? LIMIT 1',
                            [$c->id]
                        );
                        if ($r) $filters['classe_id'] = $r->classe_id;
                    }
                }
                break;
        }
        return $filters;
    }

    private function applyQueryFilters(array $base): array
    {
        if ($this->can('emploi_du_temps.edit')) {
            $classeId = (int)$this->request->get('classe_id', 0);
            $profId   = (int)$this->request->get('prof_id', 0);
            $salleId  = (int)$this->request->get('salle_id', 0);
            if ($classeId) $base['classe_id']     = $classeId;
            if ($profId)   $base['professeur_id'] = $profId;
            if ($salleId)  $base['salle_id']      = $salleId;
        }
        return $base;
    }

    // ─── Vue hebdomadaire (page principale) ──────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('emploi_du_temps.view');

        $annee   = $this->request->get('annee', '') ?: $this->currentAnnee();
        $filters = $this->applyQueryFilters($this->getRoleFilters());

        $creneaux = $this->creneauModel->findAllActifs();
        $grid     = $this->edtModel->getWeekGrid($filters, $annee);
        $stats    = $this->edtModel->getStats($annee);
        $classes  = $this->classeModel->findAll('niveau ASC, nom ASC');
        $profs    = $this->profModel->findAll('nom ASC');
        $salles   = $this->salleModel->findAllActives();

        $this->render('emplois_du_temps/index', [
            'title'         => 'Emploi du Temps',
            'creneaux'      => $creneaux,
            'grid'          => $grid,
            'stats'         => $stats,
            'classes'       => $classes,
            'profs'         => $profs,
            'salles'        => $salles,
            'filters'       => $filters,
            'annee'         => $annee,
            'anneesOptions' => $this->anneesOptions(),
            'jours'         => EmploiDuTempsModel::JOURS,
        ]);
    }

    // ─── Vue mensuelle ────────────────────────────────────────────────────────

    public function mensuel(): void
    {
        $this->requireAuth();
        $this->requirePermission('emploi_du_temps.view');

        $annee   = $this->request->get('annee', '') ?: $this->currentAnnee();
        $month   = max(1, min(12, (int)$this->request->get('mois', (int)date('m'))));
        $year    = (int)$this->request->get('annee_num', date('Y'));
        $filters = $this->applyQueryFilters($this->getRoleFilters());

        $creneaux = $this->creneauModel->findCours();
        $calendar = $this->edtModel->getMonthData($year, $month, $filters, $annee);
        $classes  = $this->classeModel->findAll('niveau ASC, nom ASC');
        $profs    = $this->profModel->findAll('nom ASC');

        $moisNoms = ['','Janvier','Février','Mars','Avril','Mai','Juin',
                     'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

        $this->render('emplois_du_temps/mensuel', [
            'title'         => 'Vue mensuelle — ' . ($moisNoms[$month] ?? '') . ' ' . $year,
            'calendar'      => $calendar,
            'creneaux'      => $creneaux,
            'month'         => $month,
            'year'          => $year,
            'moisNom'       => $moisNoms[$month] ?? '',
            'moisNoms'      => $moisNoms,
            'annee'         => $annee,
            'anneesOptions' => $this->anneesOptions(),
            'filters'       => $filters,
            'classes'       => $classes,
            'profs'         => $profs,
            'jours'         => EmploiDuTempsModel::JOURS,
        ]);
    }

    // ─── Formulaire d'ajout ───────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('emploi_du_temps.create');

        $annee   = $this->request->get('annee', '') ?: $this->currentAnnee();
        $prefill = [
            'classe_id'      => $this->request->get('classe_id', ''),
            'creneau_id'     => $this->request->get('creneau_id', ''),
            'jour_semaine'   => $this->request->get('jour', ''),
            'annee_scolaire' => $annee,
        ];
        $old = array_merge($prefill, Session::getFlash('old') ?? []);

        $this->render('emplois_du_temps/form', [
            'title'         => 'Ajouter une séance',
            'seance'        => null,
            'classes'       => $this->classeModel->findAll('niveau ASC, nom ASC'),
            'salles'        => $this->salleModel->findAllActives(),
            'creneaux'      => $this->creneauModel->findCours(),
            'matieres'      => $this->matiereModel->findAll('nom ASC'),
            'profs'         => $this->profModel->findAll('nom ASC'),
            'old'           => $old,
            'annee'         => $annee,
            'anneesOptions' => $this->anneesOptions(),
            'jours'         => EmploiDuTempsModel::JOURS,
        ]);
    }

    // ─── Sauvegarde (POST /store) ─────────────────────────────────────────────

    public function store(): void
    {
        $this->requirePermission('emploi_du_temps.create');
        $this->verifyCsrf();

        $data = $this->extractData();
        $errors = $this->validateData($data);

        if (empty($errors)) {
            $conflicts = $this->edtModel->checkConflicts(
                $data['classe_id'], $data['professeur_id'], $data['salle_id'],
                $data['creneau_id'], $data['jour_semaine'], $data['annee_scolaire']
            );
            if (!empty($conflicts)) {
                $errors = array_column($conflicts, 'message');
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/emplois-du-temps/create');
            return;
        }

        $data['created_by'] = $this->currentUser()['id'];

        try {
            $this->edtModel->insert($data);
            Session::flash('success', 'Séance ajoutée avec succès.');
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur lors de l\'ajout (conflit non résolu).');
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/emplois-du-temps/create');
            return;
        }

        $this->redirect(BASE_URL . '/emplois-du-temps?annee=' . urlencode($data['annee_scolaire']));
    }

    // ─── Formulaire de modification ───────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');

        $seance = $this->edtModel->findWithDetails($id);
        if (!$seance) {
            Session::flash('error', 'Séance introuvable.');
            $this->redirect(BASE_URL . '/emplois-du-temps');
            return;
        }

        $old = Session::getFlash('old') ?? (array)$seance;

        $this->render('emplois_du_temps/form', [
            'title'         => 'Modifier la séance',
            'seance'        => $seance,
            'classes'       => $this->classeModel->findAll('niveau ASC, nom ASC'),
            'salles'        => $this->salleModel->findAllActives(),
            'creneaux'      => $this->creneauModel->findCours(),
            'matieres'      => $this->matiereModel->findAll('nom ASC'),
            'profs'         => $this->profModel->findAll('nom ASC'),
            'old'           => $old,
            'annee'         => $seance->annee_scolaire,
            'anneesOptions' => $this->anneesOptions(),
            'jours'         => EmploiDuTempsModel::JOURS,
        ]);
    }

    // ─── Mise à jour ──────────────────────────────────────────────────────────

    public function update(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');
        $this->verifyCsrf();

        $seance = $this->edtModel->findById($id);
        if (!$seance) {
            Session::flash('error', 'Séance introuvable.');
            $this->redirect(BASE_URL . '/emplois-du-temps');
            return;
        }

        $data   = $this->extractData();
        $errors = $this->validateData($data);

        if (empty($errors)) {
            $conflicts = $this->edtModel->checkConflicts(
                $data['classe_id'], $data['professeur_id'], $data['salle_id'],
                $data['creneau_id'], $data['jour_semaine'], $data['annee_scolaire'], $id
            );
            if (!empty($conflicts)) {
                $errors = array_column($conflicts, 'message');
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/emplois-du-temps/' . $id . '/edit');
            return;
        }

        try {
            $this->edtModel->update($id, $data);
            Session::flash('success', 'Séance mise à jour.');
        } catch (\Exception $e) {
            Session::flash('error', 'Erreur lors de la mise à jour (conflit).');
        }

        $this->redirect(BASE_URL . '/emplois-du-temps?annee=' . urlencode($data['annee_scolaire']));
    }

    // ─── Suppression ──────────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $this->requirePermission('emploi_du_temps.edit');
        $this->verifyCsrf();

        $seance = $this->edtModel->findById($id);
        if (!$seance) {
            Session::flash('error', 'Séance introuvable.');
        } else {
            $this->edtModel->delete($id);
            Session::flash('success', 'Séance supprimée.');
        }

        $redirect = $this->request->post('redirect', BASE_URL . '/emplois-du-temps');
        $this->redirect($redirect);
    }

    // ─── Version imprimable ───────────────────────────────────────────────────

    public function print(): void
    {
        $this->requireAuth();
        $this->requirePermission('emploi_du_temps.view');

        $annee   = $this->request->get('annee', '') ?: $this->currentAnnee();
        $filters = $this->applyQueryFilters($this->getRoleFilters());

        $creneaux = $this->creneauModel->findAllActifs();
        $grid     = $this->edtModel->getWeekGrid($filters, $annee);

        $label = $annee;
        if (!empty($filters['classe_id'])) {
            $cl = $this->classeModel->findById((int)$filters['classe_id']);
            if ($cl) $label .= ' — ' . $cl->niveau . ' ' . $cl->nom;
        }
        if (!empty($filters['professeur_id'])) {
            $pr = $this->profModel->findById((int)$filters['professeur_id']);
            if ($pr) $label .= ' — ' . $pr->prenom . ' ' . $pr->nom;
        }

        $this->render('emplois_du_temps/print', [
            'title'    => 'Emploi du temps',
            'creneaux' => $creneaux,
            'grid'     => $grid,
            'jours'    => EmploiDuTempsModel::JOURS,
            'label'    => $label,
            'annee'    => $annee,
        ], 'print');
    }

    // ─── AJAX : vérification des conflits ────────────────────────────────────

    public function checkConflicts(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $classeId  = (int)$this->request->get('classe_id', 0);
        $profId    = (int)$this->request->get('prof_id', 0);
        $salleId   = (int)$this->request->get('salle_id', 0) ?: null;
        $creneauId = (int)$this->request->get('creneau_id', 0);
        $jour      = (int)$this->request->get('jour', 0);
        $annee     = trim($this->request->get('annee', ''));
        $exceptId  = (int)$this->request->get('except_id', 0) ?: null;

        if (!$classeId || !$profId || !$creneauId || !$jour || !$annee) {
            echo json_encode(['conflicts' => []]);
            exit;
        }

        $conflicts = $this->edtModel->checkConflicts(
            $classeId, $profId, $salleId, $creneauId, $jour, $annee, $exceptId
        );
        echo json_encode(['conflicts' => $conflicts]);
        exit;
    }

    // ─── Helpers privés ──────────────────────────────────────────────────────

    private function extractData(): array
    {
        return [
            'classe_id'      => (int)$this->request->post('classe_id', 0),
            'matiere_id'     => (int)$this->request->post('matiere_id', 0),
            'professeur_id'  => (int)$this->request->post('professeur_id', 0),
            'salle_id'       => ($sid = $this->request->post('salle_id', '')) !== '' ? (int)$sid : null,
            'creneau_id'     => (int)$this->request->post('creneau_id', 0),
            'jour_semaine'   => (int)$this->request->post('jour_semaine', 0),
            'annee_scolaire' => trim($this->request->post('annee_scolaire', '') ?: $this->currentAnnee()),
            'notes'          => trim($this->request->post('notes', '')),
        ];
    }

    private function validateData(array $data): array
    {
        $errors = [];
        if (!$data['classe_id'])                          $errors[] = 'Classe requise.';
        if (!$data['matiere_id'])                         $errors[] = 'Matière requise.';
        if (!$data['professeur_id'])                      $errors[] = 'Enseignant requis.';
        if (!$data['creneau_id'])                         $errors[] = 'Créneau horaire requis.';
        if ($data['jour_semaine'] < 1 || $data['jour_semaine'] > 6) $errors[] = 'Jour invalide.';
        return $errors;
    }
}
