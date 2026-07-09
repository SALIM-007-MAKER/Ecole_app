<?php

namespace App\Modules\Scolarite\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\ClasseModel;
use App\Models\UserModel;
use App\Modules\Scolarite\DTO\EleveDTO;
use App\Modules\Scolarite\DTO\EleveFiltersDTO;
use App\Modules\Scolarite\Policies\ElevePolicy;
use App\Modules\Scolarite\Repositories\EleveRepository;
use App\Modules\Scolarite\Services\EleveService;

class EleveController extends Controller
{
    private EleveRepository $repo;
    private EleveService    $service;
    private ElevePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new EleveRepository();
        $this->service = new EleveService();
        $this->policy  = new ElevePolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('eleves.view');

        $filters    = EleveFiltersDTO::fromRequest($_GET);
        $pagination = $this->repo->paginate($filters->toArray(), $filters->page, $filters->perPage);
        $stats      = $this->repo->countStats();
        $classes    = (new ClasseModel())->findForSelect();
        $user       = $this->currentUser();

        $this->render('Scolarite::eleves/index', [
            'title'      => 'Élèves',
            'pagination' => $pagination,
            'stats'      => $stats,
            'filters'    => $filters->toArray() + ['actif' => $filters->actif],
            'classes'    => $classes,
            'perPage'    => $filters->perPage,
            'perms'      => $user['permissions'] ?? [],
        ]);
    }

    // ── Fiche élève ───────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();

        $eleveId = (int)$id;
        $eleve   = $this->repo->findWithDetails($eleveId);

        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/eleves');
        }

        $user = $this->currentUser();
        if (!$this->policy->authorize($user, 'view', $eleve) &&
            !$this->policy->authorize($user, 'view.own', $eleve)) {
            $this->requirePermission('eleves.view');
        }

        $this->render('Scolarite::eleves/show', [
            'title' => 'Fiche — ' . $eleve->nom . ' ' . $eleve->prenom,
            'eleve' => $eleve,
            'perms' => $user['permissions'] ?? [],
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('eleves.create');

        $this->render('Scolarite::eleves/form', [
            'title'     => 'Nouvel élève',
            'eleve'     => null,
            'classes'   => (new ClasseModel())->findForSelect(),
            'parents'   => (new UserModel())->findAllWithRole('parent'),
            'matricule' => $this->service->genererMatricule(),
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('eleves.create');
        $this->verifyCsrf();

        $dto    = EleveDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!$this->service->matriculeUnique($dto->matricule)) {
            $errors['matricule'][] = 'Ce matricule est déjà utilisé.';
        }

        if (!empty($_FILES['photo']['name'])) {
            $check = (new \App\Services\UploadService())->validate($_FILES['photo'], 'photo_eleve');
            if (!$check['ok']) {
                $errors['photo'] = $check['errors'];
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/scolarite/eleves/create');
        }

        $user    = $this->currentUser();
        $eleveId = $this->service->creer($dto, $_FILES['photo'] ?? null, (int)$user['id']);

        Session::flash('success', 'Élève créé avec succès.');
        $this->redirect(BASE_URL . '/v2/scolarite/eleves/' . $eleveId);
    }

    // ── Édition ───────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requirePermission('eleves.update');

        $eleveId = (int)$id;
        $eleve   = $this->repo->findWithDetails($eleveId);

        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/eleves');
        }

        $this->render('Scolarite::eleves/form', [
            'title'   => 'Modifier — ' . $eleve->nom . ' ' . $eleve->prenom,
            'eleve'   => $eleve,
            'classes' => (new ClasseModel())->findForSelect(),
            'parents' => (new UserModel())->findAllWithRole('parent'),
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('eleves.update');
        $this->verifyCsrf();

        $eleveId = (int)$id;
        $dto     = EleveDTO::fromRequest($_POST);
        $errors  = $dto->validate();

        if (!$this->service->matriculeUnique($dto->matricule, $eleveId)) {
            $errors['matricule'][] = 'Ce matricule est déjà utilisé.';
        }

        if (!empty($_FILES['photo']['name'])) {
            $check = (new \App\Services\UploadService())->validate($_FILES['photo'], 'photo_eleve');
            if (!$check['ok']) {
                $errors['photo'] = $check['errors'];
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/scolarite/eleves/' . $eleveId . '/edit');
        }

        $user = $this->currentUser();
        $this->service->modifier($eleveId, $dto, $_FILES['photo'] ?? null, (int)$user['id']);

        Session::flash('success', 'Fiche mise à jour.');
        $this->redirect(BASE_URL . '/v2/scolarite/eleves/' . $eleveId);
    }

    // ── Archivage / Suppression ───────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $this->requirePermission('eleves.delete');
        $this->verifyCsrf();

        $eleveId = (int)$id;
        $motif   = trim($_POST['motif'] ?? '');
        $action  = $_POST['action'] ?? 'archive';

        $user = $this->currentUser();

        if ($action === 'delete') {
            $this->requirePermission('eleves.delete');
            $this->service->supprimer($eleveId, (int)$user['id']);
            Session::flash('success', 'Élève supprimé définitivement.');
        } else {
            $this->service->archiver($eleveId, (int)$user['id'], $motif);
            Session::flash('success', 'Élève archivé.');
        }

        $this->redirect(BASE_URL . '/v2/scolarite/eleves');
    }

    // ── Import CSV ────────────────────────────────────────────────────────────

    public function showImport(): void
    {
        $this->requirePermission('eleves.create');

        $this->render('Scolarite::eleves/import', [
            'title'   => 'Importer des élèves',
            'classes' => (new ClasseModel())->findAll(),
        ]);
    }

    public function import(): void
    {
        $this->requirePermission('eleves.create');
        $this->verifyCsrf();

        $file = $_FILES['csv_file'] ?? null;

        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Veuillez sélectionner un fichier CSV.');
            $this->redirect(BASE_URL . '/v2/scolarite/eleves/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Session::flash('error', 'Impossible de lire le fichier CSV.');
            $this->redirect(BASE_URL . '/v2/scolarite/eleves/import');
        }

        // BOM detection
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Sniff delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        // Read header
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            Session::flash('error', 'Fichier CSV vide ou invalide.');
            $this->redirect(BASE_URL . '/v2/scolarite/eleves/import');
        }

        // Normalize header
        $header = array_map(fn($h) => mb_strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }
        fclose($handle);

        // Build classe map
        $classeMap = [];
        foreach ((new ClasseModel())->findAll() as $c) {
            $classeMap[$c->nom] = $c->id;
            $classeMap[$c->niveau . ' - ' . $c->nom] = $c->id;
        }

        $user   = $this->currentUser();
        $result = $this->service->importerCsv($rows, $classeMap, (int)$user['id']);

        $this->render('Scolarite::eleves/import', [
            'title'   => 'Importer des élèves',
            'classes' => (new ClasseModel())->findAll(),
            'result'  => $result,
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requirePermission('eleves.view');

        $filters = EleveFiltersDTO::fromRequest($_GET);
        $eleves  = $this->repo->findAllFiltered($filters->toArray());
        $classes = (new ClasseModel())->findForSelect();

        $this->render('Scolarite::eleves/export_pdf', [
            'title'   => 'Liste des élèves',
            'eleves'  => $eleves,
            'classes' => $classes,
            'filters' => $filters->toArray(),
        ], 'none');
    }

    public function exportExcel(): void
    {
        $this->requirePermission('eleves.view');

        $filters = EleveFiltersDTO::fromRequest($_GET);
        $eleves  = $this->repo->findAllFiltered($filters->toArray());

        $filename = 'eleves_' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // BOM UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Matricule', 'Nom', 'Prénom', 'Sexe', 'Date naissance', 'Classe', 'Téléphone', 'Email', 'Statut'], ';');

        foreach ($eleves as $e) {
            fputcsv($out, [
                $e->matricule,
                $e->nom,
                $e->prenom,
                $e->sexe === 'M' ? 'Masculin' : 'Féminin',
                $e->date_naissance ? date('d/m/Y', strtotime($e->date_naissance)) : '',
                $e->classe_nom ?? '',
                $e->telephone ?? '',
                $e->email ?? '',
                $e->actif ? 'Actif' : 'Inactif',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
