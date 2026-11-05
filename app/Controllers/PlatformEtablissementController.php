<?php

namespace App\Controllers;

use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformPlanService;
use Core\Platform\PlatformSubscriptionService;
use Core\Platform\SubscriptionException;
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
        return (int)(PlatformAuth::current()['id'] ?? 0);
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
            $this->render('errors/404', [], 'none');
            return;
        }

        $abonnement = null;
        $historique = [];
        $pdo = \Core\Database::getInstance()->getConnection();
        if (PlatformSubscriptionService::disponible($pdo)) {
            $svc = new PlatformSubscriptionService($pdo);
            $courant = $svc->courant($id);
            $abonnement = $courant !== null ? ['ligne' => $courant, 'etat' => $svc->etat($courant)] : null;
            $historique = $svc->historique($id);
        }

        // Paiements manuels : données financières, visibles et saisissables par le super_admin uniquement.
        $paiements = ['actif' => false];
        if (PlatformAuth::hasLevel('super_admin') && \Core\Platform\PlatformPaymentService::disponible($pdo)) {
            $payeur = new \Core\Platform\PlatformPaymentService($pdo);
            $paiements = ['actif' => true, 'liste' => $payeur->liste($id), 'situation' => $payeur->situationParPeriode($id)];
        }

        $this->render('platform/etablissements/show', [
            'title'     => $etab['nom'],
            'paiements' => $paiements,
            'etab'      => $etab,
            'abonnement' => $abonnement,
            'historique' => $historique,
            'abonnementsActifs' => PlatformSubscriptionService::disponible($pdo),
            'canSubscribe' => PlatformAuth::hasLevel('super_admin'),
            'plans'     => $this->plans->all(false),
            'canManage' => PlatformAuth::hasLevel('admin', 'super_admin'),
        ], 'platform');
    }

    public function create(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');

        $peutChoisirPlan = PlatformAuth::hasLevel('super_admin');
        $this->render('platform/etablissements/create', [
            'title' => 'Nouvel établissement',
            'peutChoisirPlan' => $peutChoisirPlan,
            'plans' => $peutChoisirPlan ? $this->plans->all(false) : [],
            'errors' => Session::getFlash('errors', []),
        ], 'platform');
    }

    public function store(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        // Rattacher un plan crée un abonnement (essai) : opération commerciale réservée au super_admin.
        if ($this->request->post('plan_id') && !PlatformAuth::hasLevel('super_admin')) {
            Session::flash('errors', ['Seul un super_admin peut rattacher un plan (création d\'un abonnement). Créez l\'établissement sans plan.']);
            $this->redirect(BASE_URL . '/platform/etablissements/create');
        }
        try {
            $donnees = [
                'slug'      => $this->request->post('slug', ''),
                'nom'       => $this->request->post('nom', ''),
                'nom_court' => $this->request->post('nom_court', ''),
                'pays'      => $this->request->post('pays', 'DZ'),
                'plan_id'   => $this->request->post('plan_id') ?: null,
            ];
            // Formulaire actuel : cycles cochés ou type hors cycle (type déduit côté serveur, tout champ `type` ignoré).
            // Client historique qui n'envoie que `type` : ancien comportement conservé.
            $cycles = $this->request->post('cycles');
            $horsCycle = $this->request->post('type_hors_cycle');
            if ($cycles === null && $horsCycle === null) {
                $donnees['type'] = $this->request->post('type', '');
            } else {
                $donnees['cycles'] = $cycles ?? [];
                $donnees['type_hors_cycle'] = $horsCycle ?? '';
            }
            $id = $this->etabs->create($donnees, $this->operatorUserId());

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
        try {
            $this->etabs->activate($id, $this->operatorUserId());
            Session::flash('success', 'Établissement activé.');
        } catch (SubscriptionException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
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
        try {
            $this->etabs->restore($id, $this->operatorUserId());
            Session::flash('success', 'Établissement restauré (actif).');
        } catch (SubscriptionException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
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
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $planId = (int)$this->request->post('plan_id', 0);
        try {
            if ($planId > 0 && $this->etabs->assignPlan($id, $planId, $this->operatorUserId())) {
                Session::flash('success', 'Plan assigné — quotas mis à jour.');
            } else {
                Session::flash('errors', ['Plan invalide ou introuvable.']);
            }
        } catch (SubscriptionException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id);
    }
}
