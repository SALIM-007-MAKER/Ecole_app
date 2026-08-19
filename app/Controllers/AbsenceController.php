<?php

namespace App\Controllers;

use Core\Controller;
use Core\EventDispatcher;
use Core\Session;
use App\Events\AbsenceCreee;
use App\Models\AbsenceModel;
use App\Models\JustificationModel;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;
use App\Models\CreneauModel;

class AbsenceController extends Controller
{
    private AbsenceModel       $absModel;
    private JustificationModel $justModel;
    private ClasseModel        $classeModel;
    private EleveModel         $eleveModel;
    private CreneauModel       $creneauModel;

    public function __construct()
    {
        parent::__construct();
        $this->absModel     = new AbsenceModel();
        $this->justModel    = new JustificationModel();
        $this->classeModel  = new ClasseModel();
        $this->eleveModel   = new EleveModel();
        $this->creneauModel = new CreneauModel();
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        if (!$this->can('absences.view') && !$this->can('absences.view_own')) {
            Session::flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL . '/dashboard');
            return;
        }

        if ($user['role'] === 'parent') {
            // Redirige vers la liste filtrée des absences des enfants
            $this->redirect(BASE_URL . '/absences/liste');
            return;
        }
        if ($user['role'] === 'eleve') {
            $this->redirect(BASE_URL . '/absences/liste');
            return;
        }

        $stats   = $this->absModel->getStatsGlobales();
        $alertes = $this->absModel->getAlertes(3, $this->getClassesIds());
        $topAbs  = $this->absModel->getTopAbsents(5, $this->getClassesIds());
        $tendance= $this->absModel->getTendanceHebdo();
        $classes = $this->getClassesForUser();

        $this->render('absences/index', [
            'title'   => 'Absences — Tableau de bord',
            'stats'   => $stats,
            'alertes' => $alertes,
            'topAbs'  => $topAbs,
            'tendance'=> $tendance,
            'classes' => $classes,
        ]);
    }

    // ─── Pointage journalier ─────────────────────────────────────────────────

    public function pointage(): void
    {
        $this->requirePermission('absences.create');

        $classes = $this->getClassesForUser();
        $classeId= (int)$this->request->get('classe_id', 0);
        $date    = $this->request->get('date', date('Y-m-d'));
        $session = $this->request->get('session', 'matin');

        if (!in_array($session, array_keys(AbsenceModel::SESSIONS), true)) {
            $session = 'matin';
        }

        $grille = [];
        $classe = null;
        if ($classeId > 0) {
            $classe = $this->classeModel->findById($classeId);
            if ($classe) {
                $grille = $this->absModel->findForPointage($classeId, $date, $session);
            }
        }

        $this->render('absences/pointage', [
            'title'   => 'Pointage journalier',
            'classes' => $classes,
            'classeId'=> $classeId,
            'classe'  => $classe,
            'date'    => $date,
            'session' => $session,
            'sessions'=> AbsenceModel::SESSIONS,
            'grille'  => $grille,
        ]);
    }

    public function storePointage(): void
    {
        $this->requirePermission('absences.create');
        $this->verifyCsrf();

        $classeId = (int)$this->request->post('classe_id', 0);
        $date     = $this->request->post('date', date('Y-m-d'));
        $session  = $this->request->post('session', 'matin');
        $statuts  = $this->request->post('statuts', []);
        $durees   = $this->request->post('durees', []);
        $motifs   = $this->request->post('motifs', []);

        if (!$classeId || !$date || !is_array($statuts)) {
            Session::flash('error', 'Données de pointage invalides.');
            $this->redirect(BASE_URL . '/absences/pointage');
            return;
        }

        $user = $this->currentUser();
        $this->absModel->storePointage(
            $classeId, $date, $session,
            $statuts, $durees, $motifs,
            (int)$user['id']
        );

        // Événement par élève — AuditHandler + NotificationHandler (si absent/retard)
        foreach ($statuts as $eleveId => $statut) {
            EventDispatcher::dispatch(new AbsenceCreee(
                eleveId:    (int)$eleveId,
                date:       $date,
                statut:     $statut,
                session:    $session,
                classeId:   $classeId,
                saisieParId:(int)$user['id'],
                motif:      $motifs[$eleveId] ?? '',
            ));
        }

        Session::flash('success', 'Pointage enregistré avec succès.');
        $this->redirect(
            BASE_URL . '/absences/pointage?classe_id=' . $classeId
            . '&date=' . urlencode($date) . '&session=' . $session
        );
    }

    // ─── Liste filtrée ───────────────────────────────────────────────────────

    public function liste(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        if (!$this->can('absences.view') && !$this->can('absences.view_own')) {
            Session::flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL . '/dashboard');
            return;
        }

        $filters = [
            'q'           => $this->request->get('q', ''),
            'classe_id'   => $this->request->get('classe_id', ''),
            'eleve_id'    => $this->request->get('eleve_id', ''),
            'type'        => $this->request->get('type', ''),
            'statut_justif'=> $this->request->get('statut_justif', ''),
            'date_debut'  => $this->request->get('date_debut', ''),
            'date_fin'    => $this->request->get('date_fin', ''),
        ];

        // Parent : restreindre à ses enfants
        if ($user['role'] === 'parent') {
            $absences = $this->absModel->findForParent((int)$user['id']);
            $this->render('absences/liste', [
                'title'      => 'Absences de mes enfants',
                'result'     => ['items' => $absences, 'total' => count($absences), 'pages' => 1, 'currentPage' => 1],
                'filters'    => $filters,
                'classes'    => [],
                'types'      => AbsenceModel::TYPES,
                'statuts'    => AbsenceModel::STATUTS_JUSTIF,
                'isParent'   => true,
            ]);
            return;
        }

        // Élève : voir ses propres absences (recherche par email)
        if ($user['role'] === 'eleve') {
            $eleve = !empty($user['email'])
                ? $this->eleveModel->findOneBy('email', $user['email'])
                : false;
            $absences = $eleve ? $this->absModel->findForEleve((int)$eleve->id) : [];
            $this->render('absences/liste', [
                'title'   => 'Mes absences',
                'result'  => ['items' => $absences, 'total' => count($absences), 'pages' => 1, 'currentPage' => 1],
                'filters' => $filters,
                'classes' => [],
                'types'   => AbsenceModel::TYPES,
                'statuts' => AbsenceModel::STATUTS_JUSTIF,
                'isEleve' => true,
            ]);
            return;
        }

        // Restriction enseignant : ses classes seulement
        $classesIds = $this->getClassesIds();
        if ($classesIds) {
            $filters['classes_ids'] = $classesIds;
        }

        $page   = max(1, (int)$this->request->get('page', 1));
        $result = $this->absModel->paginateFiltered($page, 25, $filters);

        $this->render('absences/liste', [
            'title'   => 'Liste des absences',
            'result'  => $result,
            'filters' => $filters,
            'classes' => $this->getClassesForUser(),
            'types'   => AbsenceModel::TYPES,
            'statuts' => AbsenceModel::STATUTS_JUSTIF,
        ]);
    }

    // ─── Détail d'une absence ────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        if (!$this->can('absences.view') && !$this->can('absences.view_own')) {
            Session::flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL . '/dashboard');
            return;
        }

        $absence = $this->absModel->findWithDetails((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/absences/liste');
            return;
        }

        // Parent : vérifier que l'élève lui appartient
        if ($user['role'] === 'parent') {
            $eleve = $this->eleveModel->findById($absence->eleve_id);
            if (!$eleve || (int)$eleve->parent_id !== (int)$user['id']) {
                Session::flash('error', 'Accès non autorisé.');
                $this->redirect(BASE_URL . '/absences/liste');
                return;
            }
        }

        $justification = $this->justModel->findByAbsence((int)$id);

        $this->render('absences/show', [
            'title'        => 'Détail de l\'absence',
            'absence'      => $absence,
            'justification'=> $justification,
            'sessions'     => AbsenceModel::SESSIONS,
            'statuts'      => AbsenceModel::STATUTS_JUSTIF,
            'canEdit'      => $this->can('absences.edit'),
            'canJustify'   => ($user['role'] === 'parent' || $this->can('absences.edit')),
            'canValider'   => $this->can('absences.edit'),
        ]);
    }

    // ─── Création manuelle ───────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('absences.create');
        $classes = $this->getClassesForUser();

        $this->render('absences/create', [
            'title'   => 'Ajouter une absence',
            'classes' => $classes,
            'creneaux'=> $this->creneauModel->findCours(),
            'types'   => AbsenceModel::TYPES,
            'old'     => Session::getFlash('old') ?? [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('absences.create');
        $this->verifyCsrf();

        // "session_mode" = 'journee' → une seule ligne (session='journee',
        // sans créneau précis). "session_mode" = 'creneaux' → une ligne
        // indépendante PAR créneau coché dans creneau_ids[] (permet de
        // saisir en une fois "absent Cours 1 et 2, présent Cours 3" plutôt
        // que de n'autoriser qu'une seule absence par demi-journée). Dans ce
        // second cas, la session matin/après-midi de chaque ligne est
        // dérivée de l'heure du créneau correspondant.
        $mode = $this->request->post('session_mode', 'journee');

        $sessions = [];
        if ($mode === 'creneaux') {
            $creneauIds = array_map('intval', (array)$this->request->post('creneau_ids', []));
            $creneauIds = array_values(array_unique(array_filter($creneauIds, fn(int $id) => $id > 0)));
            foreach ($creneauIds as $cid) {
                $creneau = $this->creneauModel->findById($cid);
                if ($creneau) {
                    $sessions[] = [
                        'session'    => $creneau->heure_debut < '12:00:00' ? 'matin' : 'apres_midi',
                        'creneau_id' => $cid,
                    ];
                }
            }
        } else {
            $sessions[] = ['session' => 'journee', 'creneau_id' => null];
        }

        $data = [
            'eleve_id'     => (int)$this->request->post('eleve_id', 0),
            'classe_id'    => (int)$this->request->post('classe_id', 0),
            'date_absence' => $this->request->post('date_absence', ''),
            'session_mode' => $mode,
            'creneau_ids'  => $mode === 'creneaux' ? array_column($sessions, 'creneau_id') : [],
            'type'         => $this->request->post('type', 'absence'),
            'duree_retard' => $this->request->post('duree_retard') ?: null,
            'motif'        => trim($this->request->post('motif', '')),
        ];

        if (!$data['eleve_id'] || !$data['date_absence'] || empty($sessions)) {
            Session::flash('error', empty($sessions)
                ? 'Sélectionnez au moins un créneau, ou "Journée complète".'
                : 'Élève et date obligatoires.');
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/absences/create');
            return;
        }

        try {
            $user = $this->currentUser();
            foreach ($sessions as $s) {
                $this->absModel->storeManuelle(
                    $data['eleve_id'], $data['classe_id'], $data['date_absence'],
                    $s['session'], $s['creneau_id'], $data['type'],
                    $data['duree_retard'] ? (int)$data['duree_retard'] : null,
                    $data['motif'] ?: null,
                    (int)$user['id']
                );
            }

            // Une absence saisie manuellement doit notifier le parent au même
            // titre qu'une absence saisie via le pointage journalier — sans
            // quoi ce second point d'entrée reste muet (bug relevé à l'audit).
            EventDispatcher::dispatch(new AbsenceCreee(
                eleveId:    $data['eleve_id'],
                date:       $data['date_absence'],
                statut:     $data['type'] === 'retard' ? 'retard' : 'absent',
                session:    $sessions[0]['session'],
                classeId:   $data['classe_id'],
                saisieParId:(int)$user['id'],
                motif:      $data['motif'] ?: '',
            ));

            Session::flash('success', count($sessions) > 1
                ? count($sessions) . ' absences enregistrées.'
                : 'Absence enregistrée.');
            $this->redirect(BASE_URL . '/absences/liste');
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/absences/create');
        }
    }

    // ─── Suppression ─────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->requirePermission('absences.edit');
        $this->verifyCsrf();

        $absence = $this->absModel->findById((int)$id);
        if ($absence) {
            $this->absModel->delete((int)$id);
            Session::flash('success', 'Absence supprimée.');
        } else {
            Session::flash('error', 'Absence introuvable.');
        }
        $this->redirect(BASE_URL . '/absences/liste');
    }

    // ─── Justification (soumission par parent) ───────────────────────────────

    public function storeJustification(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $absence = $this->absModel->findWithDetails((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/absences/liste');
            return;
        }

        $user = $this->currentUser();

        // Parent : vérifier appartenance
        if ($user['role'] === 'parent') {
            $eleve = $this->eleveModel->findById($absence->eleve_id);
            if (!$eleve || (int)$eleve->parent_id !== (int)$user['id']) {
                Session::flash('error', 'Accès non autorisé.');
                $this->redirect(BASE_URL . '/absences/liste');
                return;
            }
        } elseif (!$this->can('absences.edit')) {
            Session::flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL . '/absences/liste');
            return;
        }

        $motif = trim($this->request->post('motif', ''));
        if (!$motif) {
            Session::flash('error', 'Le motif de justification est obligatoire.');
            $this->redirect(BASE_URL . '/absences/' . $id);
            return;
        }

        // Gestion du document uploadé
        $docPath = null;
        if (!empty($_FILES['document']['name'])) {
            $docPath = $this->handleUpload($_FILES['document']);
            if ($docPath === false) {
                Session::flash('error', 'Fichier invalide (PDF, JPG ou PNG, max 5 Mo).');
                $this->redirect(BASE_URL . '/absences/' . $id);
                return;
            }
        }

        $this->justModel->upsert((int)$id, (int)$user['id'], $motif, $docPath);
        $this->absModel->updateStatutJustif((int)$id, 'en_attente');

        Session::flash('success', 'Justification soumise. Elle sera examinée par l\'administration.');
        $this->redirect(BASE_URL . '/absences/' . $id);
    }

    // ─── Validation justification (direction) ────────────────────────────────

    public function validerJustification(string $id): void
    {
        $this->requirePermission('absences.edit');
        $this->verifyCsrf();

        $absence = $this->absModel->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/absences/liste');
            return;
        }

        $decision     = $this->request->post('decision', '');
        $commentaire  = trim($this->request->post('commentaire', ''));
        $justif       = $this->justModel->findByAbsence((int)$id);

        if (!$justif) {
            Session::flash('error', 'Aucune justification à valider.');
            $this->redirect(BASE_URL . '/absences/' . $id);
            return;
        }

        if ($decision === 'accepter') {
            $this->justModel->valider($justif->id, 'acceptee', $commentaire ?: null, (int)$this->currentUser()['id']);
            $this->absModel->updateStatutJustif((int)$id, 'justifiee');
            $this->notifierDecisionJustification($absence, true);
            Session::flash('success', 'Justification acceptée.');
        } elseif ($decision === 'refuser') {
            $this->justModel->valider($justif->id, 'refusee', $commentaire ?: null, (int)$this->currentUser()['id']);
            $this->absModel->updateStatutJustif((int)$id, 'refusee');
            $this->notifierDecisionJustification($absence, false);
            Session::flash('success', 'Justification refusée.');
        } else {
            Session::flash('error', 'Décision invalide.');
        }

        $this->redirect(BASE_URL . '/absences/' . $id);
    }

    /** Informe le parent de l'issue de sa demande de justification — silencieux jusqu'ici. */
    private function notifierDecisionJustification(object $absence, bool $acceptee): void
    {
        $eleve = $this->eleveModel->findById((int)$absence->eleve_id);
        if (!$eleve || empty($eleve->parent_id)) {
            return;
        }
        $dateF = date('d/m/Y', strtotime($absence->date_absence));
        $titre = $acceptee ? "Justification acceptée — {$dateF}" : "Justification refusée — {$dateF}";
        $msg   = $acceptee
            ? "Votre justification pour l'absence du {$dateF} a été acceptée."
            : "Votre justification pour l'absence du {$dateF} a été refusée.";
        try {
            (new \App\Services\NotificationService())->notify(
                (int)$eleve->parent_id, 'absence', $titre, $msg, BASE_URL . '/parent/absences'
            );
        } catch (\Throwable) {}
    }

    // ─── Statistiques ────────────────────────────────────────────────────────

    public function stats(): void
    {
        $this->requirePermission('absences.view');

        $classeId   = (int)$this->request->get('classe_id', 0);
        $classes    = $this->getClassesForUser();
        $statsGlobal= $this->absModel->getStatsGlobales();
        $statClasses= $this->absModel->getStatsParClasse();
        $tendance   = $this->absModel->getTendanceHebdo();
        $topAbs     = $this->absModel->getTopAbsents(15, $this->getClassesIds());

        $this->render('absences/stats', [
            'title'      => 'Statistiques des absences',
            'classes'    => $classes,
            'classeId'   => $classeId,
            'statsGlobal'=> $statsGlobal,
            'statClasses'=> $statClasses,
            'tendance'   => $tendance,
            'topAbs'     => $topAbs,
        ]);
    }

    // ─── Alertes ─────────────────────────────────────────────────────────────

    public function alertes(): void
    {
        $this->requirePermission('absences.view');

        $seuil   = max(1, (int)$this->request->get('seuil', 3));
        $alertes = $this->absModel->getAlertes($seuil, $this->getClassesIds());

        $this->render('absences/alertes', [
            'title'  => 'Alertes absences',
            'alertes'=> $alertes,
            'seuil'  => $seuil,
            'seuils' => AbsenceModel::SEUILS_ALERTE,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function getClassesForUser(): array
    {
        $user = $this->currentUser();
        if ($user['role'] === 'enseignant') {
            $prof = (new ProfesseurModel())->findByUserId((int)$user['id']);
            if (!$prof) return [];
            $ens  = (new EnseignementModel())->findByProfesseurId($prof->id);
            $ids  = array_unique(array_column($ens, 'classe_id'));
            if (!$ids) return [];
            return array_filter(
                $this->classeModel->findAll('nom ASC'),
                fn($c) => in_array($c->id, $ids, false)
            );
        }
        return $this->classeModel->findAll('niveau ASC, nom ASC');
    }

    private function getClassesIds(): array
    {
        $user = $this->currentUser();
        if ($user['role'] === 'enseignant') {
            $prof = (new ProfesseurModel())->findByUserId((int)$user['id']);
            if (!$prof) return [];
            $ens  = (new EnseignementModel())->findByProfesseurId($prof->id);
            return array_unique(array_column($ens, 'classe_id'));
        }
        return [];
    }

    private function handleUpload(array $file): string|false
    {
        $allowed = ['image/jpeg','image/png','application/pdf'];
        $maxSize = 5 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) return false;
        if ($file['size'] > $maxSize)          return false;
        if (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) return false;

        $dir = ROOT_PATH . '/storage/uploads/justifications';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = 'just_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
        return 'storage/uploads/justifications/' . $name;
    }
}
