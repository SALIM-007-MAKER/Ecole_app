<?php

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\DTO\PeriodeScolaireDTO;
use App\Modules\Academique\DTO\PeriodeScolaireFiltersDTO;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Policies\PeriodePolicy;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\Academique\Services\PeriodeScolaireService;
use Core\Controller;
use Core\Session;

class PeriodeController extends Controller
{
    private PeriodeScolaireRepository $repo;
    private PeriodeScolaireService    $service;
    private PeriodePolicy             $policy;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new PeriodeScolaireRepository();
        $this->service = new PeriodeScolaireService();
        $this->policy  = new PeriodePolicy();
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('academique.periodes.view');

        $filters    = PeriodeScolaireFiltersDTO::fromRequest($_GET);
        $pagination = $this->repo->paginate($filters->toArray(), $filters->page, $filters->perPage);
        $stats      = $this->repo->countStats();
        $annees     = $this->repo->listAnneesScolaires();
        $user       = $this->currentUser();

        $this->render('Academique::periodes/index', [
            'title'      => 'Périodes Scolaires',
            'pagination' => $pagination,
            'stats'      => $stats,
            'filters'    => $filters,
            'annees'     => $annees,
            'types'      => PeriodeScolaireModel::TYPE_LABELS,
            'statuts'    => PeriodeScolaireModel::STATUT_LABELS,
            'perms'      => $user['permissions'] ?? [],
            'policy'     => $this->policy,
            'user'       => $user,
        ]);
    }

    // ─── Détail ──────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requirePermission('academique.periodes.view');

        $periodeId = (int)$id;
        $periode   = $this->repo->findWithStats($periodeId);

        if (!$periode) {
            Session::flash('error', 'Période introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/periodes');
        }

        $user = $this->currentUser();

        $this->render('Academique::periodes/show', [
            'title'   => 'Période — ' . $periode->nom,
            'periode' => $periode,
            'types'   => PeriodeScolaireModel::TYPE_LABELS,
            'statuts' => PeriodeScolaireModel::STATUT_LABELS,
            'colors'  => PeriodeScolaireModel::STATUT_COLORS,
            'perms'   => $user['permissions'] ?? [],
            'policy'  => $this->policy,
            'user'    => $user,
        ]);
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('academique.periodes.manage');

        $this->render('Academique::periodes/form', [
            'title'   => 'Nouvelle période scolaire',
            'periode' => null,
            'types'   => PeriodeScolaireModel::TYPE_LABELS,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('academique.periodes.manage');
        $this->verifyCsrf();

        $dto    = PeriodeScolaireDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/periodes/create');
        }

        try {
            $user      = $this->currentUser();
            $periodeId = $this->service->creer($dto, (int)$user['id']);
            Session::flash('success', "Période « {$dto->nom} » créée avec succès.");
            $this->redirect(BASE_URL . '/v2/academique/periodes/' . $periodeId);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['global' => [$e->getMessage()]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/periodes/create');
        }
    }

    // ─── Édition ─────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $periodeId = (int)$id;
        $periode   = $this->repo->findWithStats($periodeId);

        if (!$periode) {
            Session::flash('error', 'Période introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/periodes');
        }

        $user = $this->currentUser();
        if (!$this->policy->canUpdate($user, $periode)) {
            $this->requirePermission('academique.periodes.manage');
        }

        $this->render('Academique::periodes/form', [
            'title'   => 'Modifier — ' . $periode->nom,
            'periode' => $periode,
            'types'   => PeriodeScolaireModel::TYPE_LABELS,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $periodeId = (int)$id;
        $periode   = $this->repo->findWithStats($periodeId);

        if (!$periode) {
            Session::flash('error', 'Période introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/periodes');
        }

        $user = $this->currentUser();
        if (!$this->policy->canUpdate($user, $periode)) {
            $this->requirePermission('academique.periodes.manage');
        }

        $this->verifyCsrf();

        $dto    = PeriodeScolaireDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/periodes/' . $periodeId . '/edit');
        }

        $isAdmin = in_array('academique.periodes.admin', $user['permissions'] ?? [], true);

        try {
            $this->service->modifier($periodeId, $dto, (int)$user['id'], $isAdmin);
            Session::flash('success', "Période mise à jour.");
            $this->redirect(BASE_URL . '/v2/academique/periodes/' . $periodeId);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['global' => [$e->getMessage()]]);
            Session::flash('old', $_POST);
            $this->redirect(BASE_URL . '/v2/academique/periodes/' . $periodeId . '/edit');
        }
    }

    // ─── Actions de cycle de vie ─────────────────────────────────────────────

    public function activer(string $id): void
    {
        $this->requirePermission('academique.periodes.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->activer((int)$id, (int)$user['id']);
            Session::flash('success', "Période activée. Elle est maintenant la période active de son année scolaire.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/periodes/' . $id);
    }

    public function fermer(string $id): void
    {
        $this->requirePermission('academique.periodes.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->fermer((int)$id, (int)$user['id']);
            Session::flash('success', "Période fermée. La saisie de notes est désormais bloquée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/periodes/' . $id);
    }

    public function verrouiller(string $id): void
    {
        $this->requirePermission('academique.periodes.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->verrouiller((int)$id, (int)$user['id']);
            Session::flash('success', "Période verrouillée. Aucune modification ne sera possible sans déverrouillage.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/periodes/' . $id);
    }

    public function deverrouiller(string $id): void
    {
        $this->requirePermission('academique.periodes.admin');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->deverrouiller((int)$id, (int)$user['id']);
            Session::flash('success', "Période déverrouillée (action administrateur enregistrée).");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/periodes/' . $id);
    }

    public function archiver(string $id): void
    {
        $this->requirePermission('academique.periodes.manage');
        $this->verifyCsrf();

        try {
            $user = $this->currentUser();
            $this->service->archiver((int)$id, (int)$user['id']);
            Session::flash('success', "Période archivée.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/v2/academique/periodes');
    }
}
