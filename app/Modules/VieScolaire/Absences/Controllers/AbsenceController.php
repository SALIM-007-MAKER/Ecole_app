<?php

namespace App\Modules\VieScolaire\Absences\Controllers;

use App\Models\ClasseModel;
use App\Modules\VieScolaire\Absences\DTO\AbsenceDTO;
use App\Modules\VieScolaire\Absences\DTO\AbsenceFiltersDTO;
use App\Modules\VieScolaire\Absences\DTO\JustificationDTO;
use App\Modules\VieScolaire\Absences\Policies\AbsencePolicy;
use App\Modules\VieScolaire\Absences\Repositories\AbsenceRepository;
use App\Modules\VieScolaire\Absences\Services\AbsenceService;
use App\Shared\Auth\EleveScopeTrait;
use Core\Controller;
use Core\Session;

class AbsenceController extends Controller
{
    use EleveScopeTrait;

    private AbsenceRepository $repo;
    private AbsenceService    $service;
    private AbsencePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new AbsenceRepository();
        $this->service = new AbsenceService();
        $this->policy  = new AbsencePolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('attendance.view');

        $user    = $this->currentUser();
        $filters = AbsenceFiltersDTO::fromRequest($_GET);

        $scope = $this->myEleveIds();
        if ($scope !== null) {
            $filters = $filters->withEleveIds($scope);
        }

        $result  = $this->service->paginate($filters);
        $classes = (new ClasseModel())->findForSelect();

        $this->render('VieScolaire::absences/index', [
            'title'      => 'Absences',
            'result'     => $result,
            'filters'    => $filters,
            'classes'    => $classes,
            'perms'      => $user['permissions'] ?? [],
        ]);
    }

    // ── Fiche ─────────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('attendance.view');

        $absence = $this->service->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }
        $this->assertOwnEleve((int)$absence['eleve_id']);

        $justification = $this->repo->findJustificationByAbsence((int)$id);
        $user          = $this->currentUser();

        $this->render('VieScolaire::absences/show', [
            'title'         => 'Absence — ' . $absence['eleve_prenom'] . ' ' . $absence['eleve_nom'],
            'absence'       => $absence,
            'justification' => $justification,
            'perms'         => $user['permissions'] ?? [],
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('attendance.create');

        $classes = (new ClasseModel())->findForSelect();
        $motifs  = $this->service->motifs();

        $this->render('VieScolaire::absences/create', [
            'title'   => 'Enregistrer une absence',
            'classes' => $classes,
            'motifs'  => $motifs,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('attendance.create');
        $this->verifyCsrf();

        $user = $this->currentUser();
        $dto  = AbsenceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        $classeId      = (int)($_POST['classe_id'] ?? 0);
        $anneeScolaire = $_POST['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);

        if ($classeId <= 0) {
            $errors['classe_id'][] = 'La classe est obligatoire.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/create');
        }

        try {
            $absenceId = $this->service->enregistrer($dto, $classeId, $anneeScolaire, (int)$user['id']);
            Session::flash('success', 'Absence enregistrée avec succès.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/create');
        }
    }

    // ── Pointage groupé (classe entière) ────────────────────────────────────────

    public function pointage(): void
    {
        $this->requirePermission('attendance.create');

        $classes  = (new ClasseModel())->findForSelect();
        $classeId = (int)($_GET['classe_id'] ?? 0);
        $date     = trim((string)($_GET['date'] ?? date('Y-m-d')));
        if (!\DateTime::createFromFormat('Y-m-d', $date)) {
            $date = date('Y-m-d');
        }

        $classe = null;
        $roster = [];
        if ($classeId > 0) {
            $classe = (new ClasseModel())->findById($classeId);
            if ($classe) {
                $roster = $this->repo->findRosterForPointage($classeId, $date);
            }
        }

        $this->render('VieScolaire::absences/pointage', [
            'title'    => 'Pointage journalier',
            'classes'  => $classes,
            'classeId' => $classeId,
            'classe'   => $classe,
            'date'     => $date,
            'roster'   => $roster,
        ]);
    }

    public function storePointage(): void
    {
        $this->requirePermission('attendance.create');
        $this->verifyCsrf();

        $classeId = (int)($_POST['classe_id'] ?? 0);
        $date     = trim((string)($_POST['date'] ?? ''));
        $statuts  = $_POST['statuts'] ?? [];
        $durees   = $_POST['durees']  ?? [];
        $motifs   = $_POST['motifs']  ?? [];

        if ($classeId <= 0 || !\DateTime::createFromFormat('Y-m-d', $date) || !is_array($statuts)) {
            Session::flash('error', 'Données de pointage invalides.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/pointage');
            return;
        }

        $classe = (new ClasseModel())->findById($classeId);
        if (!$classe) {
            Session::flash('error', 'Classe introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/pointage');
            return;
        }
        $anneeScolaire = $classe->annee_scolaire ?? (date('Y') . '-' . (date('Y') + 1));

        $user        = $this->currentUser();
        $userId      = (int)$user['id'];
        $lateService = new \App\Modules\VieScolaire\Retards\Services\LateService();

        $roster      = $this->repo->findRosterForPointage($classeId, $date);
        $rosterByEleve = [];
        foreach ($roster as $row) {
            $rosterByEleve[(int)$row['eleve_id']] = $row;
        }

        $heureArrivee = date('H:i:s');
        $enregistres  = 0;

        foreach ($statuts as $eleveIdStr => $statut) {
            $eleveId = (int)$eleveIdStr;
            $ligne   = $rosterByEleve[$eleveId] ?? null;
            if ($ligne === null || !in_array($statut, ['present', 'absence', 'retard'], true)) {
                continue;
            }

            // Une correction ou une re-saisie du jour archive d'abord
            // l'enregistrement existant (dans le domaine concerné), puis
            // recrée si besoin ci-dessous — évite tout doublon.
            if ($ligne['absence_id']) {
                try { $this->service->archiver((int)$ligne['absence_id'], $userId); } catch (\Throwable) {}
            }
            if ($ligne['retard_id']) {
                try { $lateService->archiver((int)$ligne['retard_id'], $userId); } catch (\Throwable) {}
            }

            if ($statut === 'absence') {
                $dto = AbsenceDTO::fromRequest([
                    'eleve_id'     => $eleveId,
                    'date_absence' => $date,
                    'type'         => 'absence',
                    'heure_debut'  => '',
                    'heure_fin'    => '',
                    'duree_heures' => '',
                    'observation'  => $motifs[$eleveIdStr] ?? '',
                ]);
                try {
                    $this->service->enregistrer($dto, $classeId, $anneeScolaire, $userId);
                    $enregistres++;
                } catch (\InvalidArgumentException) {
                    // Ligne ignorée si invalide — le reste du pointage continue.
                }
            } elseif ($statut === 'retard') {
                $dureeMin = max(1, (int)($durees[$eleveIdStr] ?? 15));
                $dto = new \App\Modules\VieScolaire\Retards\DTO\LateDTO(
                    eleveId:       $eleveId,
                    classeId:      $classeId,
                    anneeScolaire: $anneeScolaire,
                    dateRetard:    $date,
                    heurePrevue:   null,
                    heureArrivee:  $heureArrivee,
                    dureeMinutes:  $dureeMin,
                    observation:   $motifs[$eleveIdStr] ?? null,
                );
                try {
                    $lateService->enregistrerManuellement($dto, $userId);
                    $enregistres++;
                } catch (\InvalidArgumentException) {
                    // Ligne ignorée si invalide — le reste du pointage continue.
                }
            }
            // 'present' : l'archivage ci-dessus suffit, rien à recréer.
        }

        Session::flash('success', "Pointage enregistré — {$enregistres} ligne(s) mise(s) à jour.");
        $this->redirect(
            BASE_URL . '/v2/vie-scolaire/absences/pointage?classe_id=' . $classeId
            . '&date=' . urlencode($date)
        );
    }

    // ── Édition ───────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requirePermission('attendance.update');

        $absence = $this->service->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }

        $classes = (new ClasseModel())->findForSelect();

        $this->render('VieScolaire::absences/edit', [
            'title'   => 'Modifier une absence',
            'absence' => $absence,
            'classes' => $classes,
            'errors'  => Session::getFlash('errors', []),
        ]);
    }

    // ── Justification ─────────────────────────────────────────────────────────

    public function justifier(string $id): void
    {
        $this->requirePermission('attendance.justify');

        $absence = $this->service->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }
        $this->assertOwnEleve((int)$absence['eleve_id']);

        $motifs = $this->service->motifs();

        $this->render('VieScolaire::absences/justifier', [
            'title'   => 'Justifier une absence',
            'absence' => $absence,
            'motifs'  => $motifs,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function storeJustification(string $id): void
    {
        $this->requirePermission('attendance.justify');
        $this->verifyCsrf();

        $absenceId = (int)$id;
        $absence   = $this->service->findById($absenceId);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
        }
        $this->assertOwnEleve((int)$absence['eleve_id']);

        $user      = $this->currentUser();
        $dto       = JustificationDTO::fromRequest($_POST);
        $errors    = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId . '/justifier');
        }

        try {
            $this->service->soumettrJustification($absenceId, $dto, (int)$user['id']);
            Session::flash('success', 'Justification soumise avec succès.');
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $absenceId . '/justifier');
        }
    }

    public function valider(string $id): void
    {
        $this->requirePermission('attendance.validate');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->validerJustification((int)$id, (int)$user['id']);
            Session::flash('success', 'Justification validée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $id);
    }

    public function refuser(string $id): void
    {
        $this->requirePermission('attendance.validate');
        $this->verifyCsrf();

        $user       = $this->currentUser();
        $motifRefus = trim($_POST['motif_refus'] ?? '');

        try {
            $this->service->refuserJustification((int)$id, $motifRefus, (int)$user['id']);
            Session::flash('success', 'Justification refusée.');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/absences/' . $id);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $this->requirePermission('attendance.delete');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', 'Absence archivée.');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/vie-scolaire/absences');
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requirePermission('attendance.view');

        $user          = $this->currentUser();
        $classeId      = (int)($_GET['classe_id'] ?? 0);
        $anneeScolaire = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $classes       = (new ClasseModel())->findForSelect();
        $statsClasse   = $classeId > 0
                             ? $this->service->statistiquesClasse($classeId, $anneeScolaire)
                             : [];

        $this->render('VieScolaire::absences/statistiques', [
            'title'         => 'Statistiques des absences',
            'classes'       => $classes,
            'classeId'      => $classeId,
            'anneeScolaire' => $anneeScolaire,
            'statsClasse'   => $statsClasse,
            'perms'         => $user['permissions'] ?? [],
        ]);
    }
}
