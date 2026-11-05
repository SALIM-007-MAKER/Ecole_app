<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;
use App\Services\EnseignantPortee;
use App\Services\SeriesLyceeService;

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
        $portee  = EnseignantPortee::classeIds();   // null = pas de restriction (hors enseignant)
        if ($portee !== null) {
            $classes = array_values(array_filter($classes, fn($c) => in_array((int)$c->id, $portee, true)));
        }

        // Filtre par série (lycée) : un code de série, ou « __aucune » pour les classes sans série. Sans effet avant T074.
        $serieDispo = SeriesLyceeService::disponible();
        $seriesPresentes = [];
        $filtreSerie = '';
        if ($serieDispo) {
            $seriesPresentes = array_values(array_unique(array_filter(array_map(fn($c) => $c->serie ?? null, $classes))));
            sort($seriesPresentes);
            $filtreSerie = trim((string)$this->request->get('serie', ''));
            if ($filtreSerie === '__aucune') {
                $classes = array_values(array_filter($classes, fn($c) => ($c->serie ?? null) === null));
            } elseif ($filtreSerie !== '') {
                $classes = array_values(array_filter($classes, fn($c) => ($c->serie ?? null) === $filtreSerie));
            }
        }

        $this->render('classes/index', [
            'title'   => 'Gestion des classes',
            'classes' => $classes,
            'stats'   => [
                'total'          => count($classes),
                'total_eleves'   => array_sum(array_column($classes, 'nb_eleves')),
                'total_ens'      => array_sum(array_column($classes, 'nb_enseignements')),
                'niveaux'        => count(array_unique(array_column($classes, 'niveau'))),
            ],
            'serieDispo'      => $serieDispo,
            'seriesPresentes' => $seriesPresentes,
            'filtreSerie'     => $filtreSerie,
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
            'niveaux' => \App\Services\CyclesActifsService::pour()->niveauxParCycle(),   // cycles activés de l'établissement
            'serieDispo'     => SeriesLyceeService::disponible(),
            'seriesParNiveau' => SeriesLyceeService::disponible() ? SeriesLyceeService::pour()->seriesParNiveau() : [],
            'serieActuelle'  => null,
            'sourcesReprise' => (\App\Services\ProgrammeClasseService::disponible() && $this->can('classes.edit')) ? \App\Services\ProgrammeClasseService::pour()->sourcesReprise() : [],
            'annee'   => \App\Services\AnneeScolaireActive::pour(),   // année active des Paramètres
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

        if (array_key_exists('serie', $data)) {
            $data['serie'] = SeriesLyceeService::normaliser($data['serie']);   // valide : contrôlée par validateClasse
        }

        // Reprise (facultative) du programme d'une classe existante : TOUT est vérifié AVANT de créer la classe (niveau, série, confirmation,
        // droit de modifier un programme) ; en cas de refus, rien n'est créé.
        $sourceId = (int)$this->request->post('reprendre_depuis', 0);
        $svcProg  = null;
        if ($sourceId > 0) {
            try {
                if (!\App\Services\ProgrammeClasseService::disponible()) {
                    throw new \App\Services\ProgrammeException('Le programme par classe n\'est pas activé sur cette installation.');
                }
                if (!$this->can('classes.edit')) {
                    throw new \App\Services\ProgrammeException('Reprendre un programme exige le droit de modifier les classes.');
                }
                if ($this->request->post('confirmer_reprise') !== '1') {
                    throw new \App\Services\ProgrammeException('Confirmez explicitement la reprise du programme pour continuer.');
                }
                $svcProg = \App\Services\ProgrammeClasseService::pour((int)($this->currentUser()['id'] ?? 0));
                $svcProg->verifierSourceReprise((string)$data['niveau'], ($data['serie'] ?? null) ?: null, $sourceId);
            } catch (\App\Services\ProgrammeException $e) {
                Session::flash('errors', ['reprendre_depuis' => [$e->getMessage()]]);
                Session::flash('old', $data);
                $this->redirect(BASE_URL . '/classes/create');
                return;
            }
        }

        $id = $this->classeModel->insert($data);
        if ($svcProg !== null) {
            try {
                $n = $svcProg->reprendreDepuis($sourceId, (int)$id);
                Session::flash('success', "Classe créée avec succès. Programme repris : {$n} matière(s).");
            } catch (\App\Services\ProgrammeException $e) {
                Session::flash('success', 'Classe créée avec succès.');
                Session::flash('error', 'Le programme n\'a pas pu être repris : ' . $e->getMessage());
            }
        } else {
            Session::flash('success', 'Classe créée avec succès.');
        }
        $this->redirect(BASE_URL . '/classes/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DÉTAIL
    // ═══════════════════════════════════════════════════════════════════════════

    public function show(string $id): void
    {
        $this->requirePermission('classes.view');

        $classe = $this->classeModel->findWithDetails((int)$id);
        // Un enseignant ne consulte que ses classes ; message identique à « introuvable » (pas de fuite d'existence).
        if ($classe && !EnseignantPortee::peutVoirClasse((int)$id)) {
            $classe = null;
        }
        if (!$classe) {
            Session::flash('error', 'Classe introuvable.');
            $this->redirect(BASE_URL . '/classes');
            return;
        }

        $eleves     =$this->classeModel->getEleves((int)$id);
        $disponibles = $this->classeModel->getElevesDisponibles((int)$id);
        $enseignements = $this->ensModel->findByClasseId((int)$id);
        $matieres   = $this->matiereModel->findForSelect(true);   // sans la Conduite
        $professeurs = $this->profModel->findForSelect();
        $annee      = date('Y') . '-' . (date('Y') + 1);

        // Programme pédagogique de la classe (onglet « Programme ») : inerte tant que la table classe_matieres n'existe pas (T075).
        $programme = ['dispo' => \App\Services\ProgrammeClasseService::disponible(), 'lignes' => [], 'ajoutables' => [], 'verrou' => null, 'paralleles' => [], 'notes' => false];
        if ($programme['dispo']) {
            $svcProg = \App\Services\ProgrammeClasseService::pour();
            $programme['lignes']     = $svcProg->programme((int)$id);
            $programme['ajoutables'] = $svcProg->ajoutables((int)$id);
            $programme['verrou']     = $svcProg->verrou((int)$id);
            $programme['paralleles'] = $svcProg->paralleles((int)$id);
            $programme['notes']      = $svcProg->notesExistent((int)$id);
            // Historique des coefficients (journal d'audit) : réservé à ceux qui peuvent modifier les classes
            $programme['historique'] = $this->can('classes.edit') ? $svcProg->historique((int)$id) : [];
        }

        $this->render('classes/show', [
            'programme'    => $programme,
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
            // Cycles activés + le niveau actuel de la classe, même si son cycle a été désactivé depuis (rien ne devient impossible à modifier).
            'niveaux' => \App\Services\CyclesActifsService::pour()->niveauxParCycle([(string)$classe->niveau]),
            'serieDispo'      => SeriesLyceeService::disponible(),
            'seriesParNiveau' => SeriesLyceeService::disponible() ? SeriesLyceeService::pour()->seriesParNiveau() : [],
            'serieActuelle'   => $classe->serie ?? null,   // proposée même si elle a été désactivée depuis
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
        $errors = $this->validateClasse($data, (int)$id);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/classes/' . $id . '/edit');
            return;
        }

        if (SeriesLyceeService::disponible()) {
            if (array_key_exists('serie', $data)) {
                $data['serie'] = SeriesLyceeService::normaliser($data['serie']);
            }
            // Passage à un niveau hors lycée : la série n'a plus de sens, elle est retirée. Série non postée : inchangée.
            if (!SeriesLyceeService::estNiveauLycee($data['niveau'])) {
                $data['serie'] = null;
            }
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
            $places    = $this->classeModel->placesRestantes($classeId, $eleveId);   // élèves actifs seulement, hors l'élève concerné

            if ($places !== null && $places <= 0 && !((int)$eleve->classe_id === $classeId)) {
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
            if ($this->matiereModel->estConduite($matId)) {
                Session::flash('error', "La Conduite n'est pas une matière d'enseignement : aucun enseignant ne peut y être affecté.");
            } elseif (!$this->matiereModel->estActive($matId)) {
                Session::flash('error', 'Cette matière est désactivée : réactivez-la avant de l\'affecter à un enseignant.');
            } elseif ($this->ensModel->exists($profId, $matId, $classeId, $annee)) {
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
        $data = [
            'nom'            => strtoupper(trim($this->request->post('nom', ''))),
            'niveau'         => trim($this->request->post('niveau', '')),
            'annee_scolaire' => trim($this->request->post('annee_scolaire', '')),
            'max_eleves'     => (int)$this->request->post('max_eleves', 40),
            'description'    => trim($this->request->post('description', '')) ?: null,
        ];
        // La série n'est lue que si la colonne existe (T074) et seulement si le champ est posté : absent = inchangée.
        $serie = $this->request->post('serie');
        if (SeriesLyceeService::disponible() && $serie !== null) {
            $data['serie'] = is_string($serie) ? trim($serie) : '?';
        }
        return $data;
    }

    private function validateClasse(array $data, ?int $exceptId = null): array
    {
        $errors = [];

        if (empty($data['nom'])) {
            $errors['nom'][] = 'Le nom de la classe est obligatoire.';
        } elseif (strlen($data['nom']) > 10) {
            $errors['nom'][] = 'Le nom ne doit pas dépasser 10 caractères.';
        }

        if (empty($data['niveau'])) {
            $errors['niveau'][] = 'Le niveau est obligatoire.';
        } else {
            // Niveau de la nomenclature ET d'un cycle activé ; à la modification, le niveau actuel reste valide (inchangé).
            $actuel = $exceptId !== null ? ($this->classeModel->findById($exceptId)->niveau ?? null) : null;
            if (!in_array($data['niveau'], ClasseModel::NIVEAUX_ORDRE, true)) {
                $errors['niveau'][] = 'Niveau inconnu.';
            } elseif (!\App\Services\CyclesActifsService::pour()->niveauAutorise($data['niveau'], $actuel)) {
                $errors['niveau'][] = 'Ce niveau appartient à un cycle non activé pour votre établissement (Paramètres → Organisation académique).';
            }
        }

        if (array_key_exists('serie', $data)) {
            $norm = SeriesLyceeService::normaliser($data['serie']);
            if ($norm === false) {
                $errors['serie'][] = 'Série invalide : 1 à 10 caractères (lettres, chiffres, tiret).';
            } elseif (empty($errors['niveau']) && !empty($data['niveau'])) {
                $cur = $exceptId !== null ? $this->classeModel->findById($exceptId) : false;
                $msg = SeriesLyceeService::pour()->erreurSerie($data['niveau'], $norm, $cur ? (string)$cur->niveau : null, $cur ? ($cur->serie ?? null) : null);
                if ($msg !== null) {
                    $errors['serie'][] = $msg;
                }
            }
        }

        if (empty($data['annee_scolaire'])) {
            $errors['annee_scolaire'][] = "L'année scolaire est obligatoire.";
        } elseif (!preg_match('/^\d{4}-\d{4}$/', $data['annee_scolaire'])) {
            $errors['annee_scolaire'][] = "Format invalide. Utilisez YYYY-YYYY (ex: 2024-2025).";
        }

        if ($data['max_eleves'] < 1 || $data['max_eleves'] > 100) {
            $errors['max_eleves'][] = 'La capacité doit être entre 1 et 100.';
        }

        if (empty($errors['nom']) && empty($errors['niveau']) && empty($errors['annee_scolaire'])
            && $this->classeModel->existeDoublon($data['nom'], $data['niveau'], $data['annee_scolaire'], $exceptId)) {
            $errors['nom'][] = 'Une classe « ' . $data['niveau'] . ' ' . $data['nom'] . ' » existe déjà pour l\'année ' . $data['annee_scolaire'] . '.';
        }

        return $errors;
    }
}
