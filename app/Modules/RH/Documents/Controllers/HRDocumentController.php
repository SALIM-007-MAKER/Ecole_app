<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Controllers;

use Core\Controller;
use Core\Session;
use Core\Database;
use PDO;
use App\Modules\RH\Documents\DTO\HRDocumentDTO;
use App\Modules\RH\Documents\DTO\HRDocumentFiltersDTO;
use App\Modules\RH\Documents\Models\HRDocumentModel;
use App\Modules\RH\Documents\Policies\HRDocumentPolicy;
use App\Modules\RH\Documents\Repositories\HRDocumentRepository;
use App\Modules\RH\Documents\Services\HRDocumentService;

class HRDocumentController extends Controller
{
    private HRDocumentService    $service;
    private HRDocumentRepository $repo;
    private HRDocumentPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new HRDocumentService();
        $this->repo    = new HRDocumentRepository();
        $this->policy  = new HRDocumentPolicy();
    }

    private function currentUser(): array { return Session::getUser() ?? []; }
    private function userId(): int        { return (int)($this->currentUser()['id'] ?? 0); }
    private function userName(): string
    {
        $u = $this->currentUser();
        return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: 'Système';
    }

    // GET /v2/rh/documents
    public function index(): void
    {
        $this->requirePermission('hr_document.view');
        $filters = HRDocumentFiltersDTO::fromRequest($_GET);
        if (!$this->policy->canViewSecret($this->currentUser())) {
            $filters = new HRDocumentFiltersDTO(
                q: $filters->q, type: $filters->type, statut: $filters->statut,
                employeId: $filters->employeId, confidentialite: $filters->confidentialite,
                expirationAvant: $filters->expirationAvant, includeArchive: $filters->includeArchive,
                page: $filters->page, perPage: $filters->perPage, excludeSecret: true,
            );
        }
        $total   = $this->repo->count($filters);
        $docs    = $this->repo->findAll($filters);
        $stats   = $this->repo->statistiques();

        $this->render('RH::documents/index', [
            'docs'    => $docs,
            'filters' => $filters,
            'stats'   => $stats,
            'total'   => $total,
            'pages'   => (int)ceil($total / $filters->perPage),
            'model'   => HRDocumentModel::class,
            'policy'  => $this->policy,
            'user'    => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/documents/create
    public function create(): void
    {
        $this->requirePermission('hr_document.create');
        $employes = $this->loadEmployes();
        $preEmp   = (int)($_GET['employe_id'] ?? 0);

        $this->render('RH::documents/create', [
            'employes' => $employes,
            'preEmp'   => $preEmp,
            'model'    => HRDocumentModel::class,
            'old'      => [],
            'errors'   => [],
        ]);
    }

    // POST /v2/rh/documents
    public function store(): void
    {
        $this->requirePermission('hr_document.create');
        $this->verifyCsrf();

        $dto    = HRDocumentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $this->render('RH::documents/create', [
                'employes' => $this->loadEmployes(),
                'preEmp'   => $dto->employeId,
                'model'    => HRDocumentModel::class,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        try {
            $id = $this->service->creerDocument($dto, $this->userId(), $this->userName());
            Session::flash('success', 'Document enregistré (v1).');
            $this->redirect('/v2/rh/documents/' . $id);
        } catch (\Exception $e) {
            $this->render('RH::documents/create', [
                'employes' => $this->loadEmployes(),
                'preEmp'   => $dto->employeId,
                'model'    => HRDocumentModel::class,
                'old'      => $_POST,
                'errors'   => ['global' => $e->getMessage()],
            ]);
        }
    }

    // GET /v2/rh/documents/{id}
    public function show(int $id): void
    {
        $this->requirePermission('hr_document.view');
        $doc = $this->requireDoc($id);
        if ($doc['confidentialite'] === 'secret' && !$this->policy->canViewSecret($this->currentUser())) {
            Session::flash('error', 'Accès refusé — document confidentiel.');
            $this->redirect('/v2/rh/documents');
        }
        $versions  = $this->repo->findVersions($id);
        $historique= $this->repo->findHistorique($id);

        $this->render('RH::documents/show', [
            'doc'        => $doc,
            'versions'   => $versions,
            'historique' => $historique,
            'model'      => HRDocumentModel::class,
            'policy'     => $this->policy,
            'user'       => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/documents/{id}/edit
    public function edit(int $id): void
    {
        $this->requirePermission('hr_document.update');
        $doc      = $this->requireDoc($id);
        $employes = $this->loadEmployes();

        if ($doc['statut'] === 'archive') {
            Session::flash('error', 'Un document archivé ne peut pas être modifié.');
            $this->redirect('/v2/rh/documents/' . $id);
        }

        $this->render('RH::documents/edit', [
            'doc'      => $doc,
            'employes' => $employes,
            'model'    => HRDocumentModel::class,
            'old'      => [],
            'errors'   => [],
        ]);
    }

    // POST /v2/rh/documents/{id}
    public function update(int $id): void
    {
        $this->requirePermission('hr_document.update');
        $this->verifyCsrf();

        $doc = $this->requireDoc($id);
        // Preserve immutable fields
        $_POST['type']       = $doc['type'];
        $_POST['employe_id'] = $doc['employe_id'];

        $dto    = HRDocumentDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if ($errors) {
            $this->render('RH::documents/edit', [
                'doc'      => $doc,
                'employes' => $this->loadEmployes(),
                'model'    => HRDocumentModel::class,
                'old'      => $_POST,
                'errors'   => $errors,
            ]);
            return;
        }

        try {
            $this->service->mettreAJour($id, $dto, $this->userId(), $this->userName());
            Session::flash('success', 'Document mis à jour — nouvelle version créée.');
            $this->redirect('/v2/rh/documents/' . $id);
        } catch (\Exception $e) {
            $this->render('RH::documents/edit', [
                'doc'      => $doc,
                'employes' => $this->loadEmployes(),
                'model'    => HRDocumentModel::class,
                'old'      => $_POST,
                'errors'   => ['global' => $e->getMessage()],
            ]);
        }
    }

    // POST /v2/rh/documents/{id}/archiver
    public function archiver(int $id): void
    {
        $this->requirePermission('hr_document.archive');
        $this->verifyCsrf();
        try {
            $notes = trim($_POST['notes'] ?? '') ?: null;
            $this->service->archiverDocument($id, $this->userId(), $this->userName(), $notes);
            Session::flash('success', 'Document archivé.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/documents/' . $id);
    }

    // POST /v2/rh/documents/{id}/restaurer
    public function restaurer(int $id): void
    {
        $this->requirePermission('hr_document.archive');
        $this->verifyCsrf();
        try {
            $this->service->restaurerDocument($id, $this->userId(), $this->userName());
            Session::flash('success', 'Document restauré.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/rh/documents/' . $id);
    }

    // GET /v2/rh/documents/expirations
    public function expirations(): void
    {
        $this->requirePermission('hr_document.view');
        $jours         = max(1, (int)($_GET['jours'] ?? 60));
        $canViewSecret = $this->policy->canViewSecret($this->currentUser());
        $docs          = $this->repo->findExpiring($jours, $canViewSecret);

        $this->render('RH::documents/expirations', [
            'docs'   => $docs,
            'jours'  => $jours,
            'model'  => HRDocumentModel::class,
            'policy' => $this->policy,
            'user'   => $this->currentUser(),
        ]);
    }

    // GET /v2/rh/documents/export
    public function export(): void
    {
        $this->requirePermission('hr_document.export');
        $filters = HRDocumentFiltersDTO::fromRequest(array_merge($_GET, ['per_page' => 9999]));
        if (!$this->policy->canViewSecret($this->currentUser())) {
            $filters = new HRDocumentFiltersDTO(
                q: $filters->q, type: $filters->type, statut: $filters->statut,
                employeId: $filters->employeId, confidentialite: $filters->confidentialite,
                expirationAvant: $filters->expirationAvant, includeArchive: $filters->includeArchive,
                page: $filters->page, perPage: $filters->perPage, excludeSecret: true,
            );
        }
        $docs    = $this->repo->findAll($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="documents_rh_' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Type','Titre','Employé','Statut','Confidentialité','Version','Émission','Expiration','Référence'], ';');
        foreach ($docs as $d) {
            fputcsv($out, [
                $d['id'],
                HRDocumentModel::typeLabel($d['type']),
                $d['titre'],
                $d['employe_nom'] ?? '',
                $d['statut'],
                $d['confidentialite'],
                $d['version_courante'],
                $d['date_emission'],
                $d['date_expiration'] ?? '',
                $d['reference_externe'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireDoc(int $id): array
    {
        $doc = $this->repo->findById($id);
        if (!$doc) {
            Session::flash('error', 'Document introuvable.');
            $this->redirect('/v2/rh/documents');
        }
        return $doc;
    }

    private function loadEmployes(): array
    {
        $pdo = Database::getInstance()->getConnection();
        return $pdo->query(
            'SELECT id, CONCAT(prenom,\' \',nom) AS nom_complet, matricule
             FROM rh_employes WHERE actif = 1 AND deleted_at IS NULL ORDER BY nom, prenom'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
