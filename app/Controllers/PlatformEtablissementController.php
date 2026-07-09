<?php

namespace App\Controllers;

use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformPlanService;
use Core\Session;

class PlatformEtablissementController extends PlatformController
{
    private PlatformEtablissementService $etabs;
    private PlatformPlanService $plans;

    public function __construct()
    {
        parent::__construct();
        $this->etabs = PlatformEtablissementService::make();
        $this->plans = PlatformPlanService::make();
    }

    private function operatorUserId(): int
    {
        return (int)(PlatformAuth::current()['user_id'] ?? 0);
    }

    public function index(): void
    {
        $this->requirePlatformAuth();

        $filters = [
            'search'  => trim($this->request->get('q', '')),
            'statut'  => $this->request->get('statut', ''),
            'plan_id' => $this->request->get('plan_id', ''),
        ];

        $this->render('platform/etablissements/index', [
            'title'        => 'Établissements',
            'etablissements' => $this->etabs->search(array_filter($filters)),
            'filters'      => $filters,
            'plans'        => $this->plans->all(),
            'canManage'    => PlatformAuth::hasLevel('admin', 'super_admin'),
        ], 'platform');
    }

    public function show(int $id): void
    {
        $this->requirePlatformAuth();

        $etab = $this->etabs->find($id);
        if ($etab === null) {
            http_response_code(404);
            $this->render('errors/404', [], 'main');
            return;
        }

        $this->render('platform/etablissements/show', [
            'title'     => $etab['nom'],
            'etab'      => $etab,
            'plans'     => $this->plans->all(),
            'canManage' => PlatformAuth::hasLevel('admin', 'super_admin'),
        ], 'platform');
    }

    public function create(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');

        $this->render('platform/etablissements/create', [
            'title' => 'Nouvel établissement',
            'plans' => $this->plans->all(),
            'errors' => Session::getFlash('errors', []),
        ], 'platform');
    }

    public function store(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        try {
            $id = $this->etabs->create([
                'slug'      => $this->request->post('slug', ''),
                'nom'       => $this->request->post('nom', ''),
                'nom_court' => $this->request->post('nom_court', ''),
                'type'      => $this->request->post('type', ''),
                'pays'      => $this->request->post('pays', 'DZ'),
                'plan_id'   => $this->request->post('plan_id') ?: null,
            ], $this->operatorUserId());

            Session::flash('success', 'Établissement créé.');
            $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
        } catch (\InvalidArgumentException $e) {
            Session::flash('errors', [$e->getMessage()]);
            $this->redirect(BASE_URL . '/platform/etablissements/create');
        }
    }

    public function activate(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();
        $this->etabs->activate($id, $this->operatorUserId());
        Session::flash('success', 'Établissement activé.');
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }

    public function suspend(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();
        $reason = trim($this->request->post('reason', ''));
        $this->etabs->suspend($id, $this->operatorUserId(), $reason ?: null);
        Session::flash('success', 'Établissement suspendu.');
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }

    public function archive(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();
        $this->etabs->archive($id, $this->operatorUserId());
        Session::flash('success', 'Établissement archivé.');
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }

    public function restore(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();
        $this->etabs->restore($id, $this->operatorUserId());
        Session::flash('success', 'Établissement restauré (actif).');
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }

    public function destroy(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();
        $this->etabs->softDelete($id, $this->operatorUserId());
        Session::flash('success', 'Établissement supprimé (logiquement).');
        $this->redirect(BASE_URL . '/platform/etablissements');
    }

    public function assignPlan(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $planId = (int)$this->request->post('plan_id', 0);
        if ($planId > 0 && $this->etabs->assignPlan($id, $planId, $this->operatorUserId())) {
            Session::flash('success', 'Plan assigné — quotas mis à jour.');
        } else {
            Session::flash('errors', ['Plan invalide ou introuvable.']);
        }
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }
}
