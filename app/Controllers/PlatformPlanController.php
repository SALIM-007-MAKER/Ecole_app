<?php

namespace App\Controllers;

use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformPlanService;
use Core\Session;

class PlatformPlanController extends PlatformController
{
    private PlatformPlanService $plans;

    public function __construct()
    {
        parent::__construct();
        $this->plans = PlatformPlanService::make();
    }

    private function operatorUserId(): int
    {
        return (int)(PlatformAuth::current()['user_id'] ?? 0);
    }

    public function index(): void
    {
        $this->requirePlatformAuth();

        $plans = $this->plans->all();
        foreach ($plans as &$plan) {
            $plan['tenants_count'] = $this->plans->usageCount((int)$plan['id']);
        }
        unset($plan);

        $this->render('platform/plans/index', [
            'title'     => 'Plans SaaS',
            'plans'     => $plans,
            'canManage' => PlatformAuth::hasLevel('admin', 'super_admin'),
            'errors'    => Session::getFlash('errors', []),
        ], 'platform');
    }

    public function store(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        try {
            $this->plans->create($this->request->all(), $this->operatorUserId());
            Session::flash('success', 'Plan créé.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/plans');
    }

    public function update(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $this->plans->update($id, $this->request->all(), $this->operatorUserId());
        Session::flash('success', 'Plan mis à jour.');
        $this->redirect(BASE_URL . '/platform/plans');
    }

    public function toggle(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $active = $this->request->post('active') === '1';
        $this->plans->toggleActive($id, $active, $this->operatorUserId());
        Session::flash('success', $active ? 'Plan réactivé.' : 'Plan désactivé.');
        $this->redirect(BASE_URL . '/platform/plans');
    }
}
