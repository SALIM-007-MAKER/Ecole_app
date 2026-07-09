<?php

namespace App\Modules\Scolarite\Controllers;

use Core\Controller;
use Core\Session;
use App\Modules\Scolarite\DTO\FamilleDTO;
use App\Modules\Scolarite\DTO\FamilleFiltersDTO;
use App\Modules\Scolarite\Models\FamilleModel;
use App\Modules\Scolarite\Repositories\FamilleRepository;
use App\Modules\Scolarite\Services\FamilleService;
use App\Modules\Scolarite\Policies\FamillePolicy;

class FamilleController extends Controller
{
    private FamilleRepository $repo;
    private FamilleService    $service;
    private FamillePolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new FamilleRepository();
        $this->service = new FamilleService($this->repo);
        $this->policy  = new FamillePolicy();
    }

    // ─── Liste ────────────────────────────────────────────────────────────────

    /** GET /v2/scolarite/familles */
    public function index(): void
    {
        $this->requirePermission('familles.view');

        $filters = FamilleFiltersDTO::fromRequest($this->request->get());
        $result  = $this->repo->paginate($filters->q, $filters->actif, $filters->page, $filters->perPage);
        $stats   = $this->repo->countStats();

        $this->render('Scolarite::familles/index', [
            'title'   => 'Familles & Responsables',
            'result'  => $result,
            'filters' => $filters,
            'stats'   => $stats,
        ]);
    }

    // ─── Détail ───────────────────────────────────────────────────────────────

    /** GET /v2/scolarite/familles/{id} */
    public function show(string $id): void
    {
        $this->requirePermission('familles.view');

        $famille = $this->repo->findWithDetails((int)$id);
        if (!$famille) {
            Session::flash('error', 'Famille introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles');
            return;
        }

        $user = $this->currentUser();
        if (!$this->policy->canView($user, $famille)) {
            $this->redirect(BASE_URL . '/403');
            return;
        }

        $elevesDisponibles = $this->policy->canLinkEleve($user)
            ? $this->repo->findElevesDisponibles((int)$id)
            : [];

        $this->render('Scolarite::familles/show', [
            'title'             => 'Famille ' . htmlspecialchars($famille->nom),
            'famille'           => $famille,
            'elevesDisponibles' => $elevesDisponibles,
            'liens'             => FamilleModel::LIENS_LABELS,
            'canManage'         => $this->policy->canManage($user),
            'canLinkEleve'      => $this->policy->canLinkEleve($user),
        ]);
    }

    // ─── Formulaire création ──────────────────────────────────────────────────

    /** GET /v2/scolarite/familles/create */
    public function create(): void
    {
        $this->requirePermission('familles.manage');

        $this->render('Scolarite::familles/form', [
            'title'   => 'Nouvelle famille',
            'famille' => null,
            'isEdit'  => false,
            'liens'   => FamilleModel::LIENS_LABELS,
            'errors'  => [],
            'old'     => [],
        ]);
    }

    // ─── Enregistrer ──────────────────────────────────────────────────────────

    /** POST /v2/scolarite/familles */
    public function store(): void
    {
        $this->requirePermission('familles.manage');
        $this->verifyCsrf();

        $dto    = FamilleDTO::fromRequest($this->request->post());
        $errors = $dto->validate();

        if ($errors) {
            $this->render('Scolarite::familles/form', [
                'title'   => 'Nouvelle famille',
                'famille' => null,
                'isEdit'  => false,
                'liens'   => FamilleModel::LIENS_LABELS,
                'errors'  => $errors,
                'old'     => $this->request->post(),
            ]);
            return;
        }

        try {
            $user = $this->currentUser();
            $familleId = $this->service->creer($dto, (int)$user['id']);
            Session::flash('success', 'Famille créée avec succès.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $familleId);
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/scolarite/familles/create');
        }
    }

    // ─── Formulaire édition ───────────────────────────────────────────────────

    /** GET /v2/scolarite/familles/{id}/edit */
    public function edit(string $id): void
    {
        $this->requirePermission('familles.manage');

        $model   = new FamilleModel();
        $famille = $model->findById((int)$id);
        if (!$famille) {
            Session::flash('error', 'Famille introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles');
            return;
        }

        $this->render('Scolarite::familles/form', [
            'title'   => 'Modifier — ' . htmlspecialchars($famille->nom),
            'famille' => $famille,
            'isEdit'  => true,
            'liens'   => FamilleModel::LIENS_LABELS,
            'errors'  => [],
            'old'     => [],
        ]);
    }

    // ─── Mettre à jour ────────────────────────────────────────────────────────

    /** POST /v2/scolarite/familles/{id} */
    public function update(string $id): void
    {
        $this->requirePermission('familles.manage');
        $this->verifyCsrf();

        $model   = new FamilleModel();
        $famille = $model->findById((int)$id);
        if (!$famille) {
            Session::flash('error', 'Famille introuvable.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles');
            return;
        }

        $dto    = FamilleDTO::fromRequest($this->request->post());
        $errors = $dto->validate();

        if ($errors) {
            $this->render('Scolarite::familles/form', [
                'title'   => 'Modifier — ' . htmlspecialchars($famille->nom),
                'famille' => $famille,
                'isEdit'  => true,
                'liens'   => FamilleModel::LIENS_LABELS,
                'errors'  => $errors,
                'old'     => $this->request->post(),
            ]);
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->modifier((int)$id, $dto, (int)$user['id']);
            Session::flash('success', 'Famille mise à jour.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $id);
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $id . '/edit');
        }
    }

    // ─── Rattacher un élève ───────────────────────────────────────────────────

    /** POST /v2/scolarite/familles/{id}/rattacher-eleve */
    public function rattacherEleve(string $id): void
    {
        $this->requirePermission('familles.manage');
        $this->verifyCsrf();

        $data   = $this->request->post();
        $eleveId              = (int)($data['eleve_id'] ?? 0);
        $lienParente          = trim($data['lien_parente'] ?? 'autre');
        $estContactPrincipal  = !empty($data['est_contact_principal']);
        $estContactUrgence    = !empty($data['est_contact_urgence']);

        if ($eleveId <= 0) {
            Session::flash('error', 'Élève invalide.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $id);
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->lierEleve(
                (int)$id, $eleveId, $lienParente,
                $estContactPrincipal, $estContactUrgence,
                (int)$user['id']
            );
            Session::flash('success', 'Élève rattaché à la famille.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $id);
    }

    // ─── Détacher un élève ────────────────────────────────────────────────────

    /** POST /v2/scolarite/familles/{id}/detacher-eleve */
    public function detacherEleve(string $id): void
    {
        $this->requirePermission('familles.manage');
        $this->verifyCsrf();

        $eleveId = (int)($this->request->post('eleve_id') ?? 0);
        if ($eleveId <= 0) {
            Session::flash('error', 'Élève invalide.');
            $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $id);
            return;
        }

        try {
            $user = $this->currentUser();
            $this->service->delierEleve((int)$id, $eleveId, (int)$user['id']);
            Session::flash('success', 'Élève retiré de la famille.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/scolarite/familles/' . $id);
    }

    // ─── Archiver ─────────────────────────────────────────────────────────────

    /** POST /v2/scolarite/familles/{id}/archiver */
    public function archiver(string $id): void
    {
        $this->requirePermission('familles.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', 'Famille archivée.');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/scolarite/familles');
    }
}
