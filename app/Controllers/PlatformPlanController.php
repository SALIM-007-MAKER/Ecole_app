<?php

namespace App\Controllers;

use Core\Logger;
use Core\Platform\PlanAuditException;
use Core\Platform\PlanValidationException;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformPlanService;
use Core\Session;

/**
 * Plans SaaS. Lecture : tout opérateur connecté. Création, modification, archivage et restauration : super_admin uniquement
 * (les conditions commerciales sont sensibles).
 */
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
        return (int)(PlatformAuth::current()['id'] ?? 0);
    }

    /**
     * Erreur technique inattendue (base de données…) : détail journalisé côté serveur, JAMAIS affiché à l'opérateur (un message SQL expose
     * noms de tables et de colonnes). L'opération est déjà annulée par la transaction du service. Renvoie le message affichable.
     */
    private function erreurTechnique(\Throwable $e, string $operation): string
    {
        $reference = bin2hex(random_bytes(4));
        Logger::error(sprintf('[PLATFORM_PLAN] %s a échoué (réf. %s) — %s : %s in %s:%d', $operation, $reference, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
        return "Une erreur technique est survenue : l'opération a été annulée, aucune modification n'a été enregistrée. Référence à communiquer à l'administrateur : {$reference}.";
    }

    /** Champs du formulaire transmis au service (liste blanche : rien d'autre n'est pris en compte). */
    private function donneesFormulaire(bool $creation): array
    {
        $in = $this->request->all();
        $cles = ['nom', 'description', 'max_users', 'max_eleves', 'storage_quota_mb', 'api_calls_per_day', 'prix_mensuel', 'prix_annuel',
                 'devise', 'duree_essai_jours', 'jours_grace', 'ordre', 'visible_catalogue', 'confirmer_conditions'];
        if ($creation) {
            $cles[] = 'code';
        }
        $data = array_intersect_key($in, array_flip($cles));
        // Les cases à cocher non cochées sont absentes de la requête : le marqueur indique que la liste de modules est bien soumise.
        if (isset($in['modules_present'])) {
            $data['modules_present'] = '1';
            $data['modules_inclus'] = is_array($in['modules_inclus'] ?? null) ? $in['modules_inclus'] : [];
        }
        return $data;
    }

    private function vueFormulaire(?array $plan, array $old): void
    {
        $this->render('platform/plans/form', [
            'title'     => $plan === null ? 'Nouveau plan' : 'Modifier le plan « ' . $plan['nom'] . ' »',
            'plan'      => $plan,
            'old'       => $old,
            'usage'     => $plan === null ? 0 : $this->plans->usageCount((int)$plan['id']),
            'modules'   => PlatformPlanService::modulesConnus(),
            'devise'    => PlatformPlanService::deviseReference(),
            'errors'    => Session::getFlash('errors', []),
        ], 'platform');
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
            'canManage' => PlatformAuth::hasLevel('super_admin'),
            'devise'    => PlatformPlanService::deviseReference(),
            'errors'    => Session::getFlash('errors', []),
        ], 'platform');
    }

    public function create(): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->vueFormulaire(null, Session::getFlash('old', []));
    }

    public function edit(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $plan = $this->plans->find($id);
        if ($plan === null) {
            http_response_code(404);
            $this->render('errors/404', [], 'none');
            return;
        }
        $this->vueFormulaire($plan, Session::getFlash('old', []));
    }

    public function store(): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $data = $this->donneesFormulaire(true);
        try {
            $this->plans->create($data, $this->operatorUserId());
            Session::flash('success', 'Plan créé.');
            $this->redirect(BASE_URL . '/platform/plans');
        } catch (PlanValidationException $e) {
            Session::flash('errors', $e->getErrors());
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/platform/plans/create');
        } catch (PlanAuditException $e) {
            // Trace d'audit impossible : le plan n'a pas été créé (transaction annulée). Message métier sans détail technique.
            Session::flash('errors', [$e->getMessage()]);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/platform/plans/create');
        } catch (\PDOException $e) {
            Session::flash('errors', [$this->erreurTechnique($e, 'création de plan')]);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/platform/plans/create');
        }
    }

    public function update(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $data = $this->donneesFormulaire(false);
        try {
            if ($this->plans->find($id) === null) {
                Session::flash('errors', ['Plan introuvable.']);
            } elseif ($this->plans->update($id, $data, $this->operatorUserId())) {
                Session::flash('success', 'Plan mis à jour.');
            } else {
                Session::flash('success', 'Aucune modification à enregistrer.');
            }
            $this->redirect(BASE_URL . '/platform/plans');
        } catch (PlanValidationException $e) {
            Session::flash('errors', $e->getErrors());
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/platform/plans/' . $id . '/modifier');
        } catch (PlanAuditException $e) {
            // Trace d'audit impossible : la modification a été annulée (transaction), le plan est inchangé. Message métier sans détail technique.
            Session::flash('errors', [$e->getMessage()]);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/platform/plans/' . $id . '/modifier');
        } catch (\PDOException $e) {
            Session::flash('errors', [$this->erreurTechnique($e, "modification du plan #{$id}")]);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/platform/plans/' . $id . '/modifier');
        }
    }

    public function toggle(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $active = $this->request->post('active') === '1';
        if ($this->plans->find($id) === null) {
            Session::flash('errors', ['Plan introuvable.']);
        } else {
            try {
                if ($this->plans->toggleActive($id, $active, $this->operatorUserId())) {
                    Session::flash('success', $active ? 'Plan restauré.' : 'Plan archivé. Les établissements déjà sur ce plan le conservent.');
                } else {
                    Session::flash('success', $active ? 'Ce plan est déjà actif : aucune modification.' : 'Ce plan est déjà archivé : aucune modification.');
                }
            } catch (PlanAuditException $e) {
                // Trace d'audit impossible : l'opération a été annulée (transaction), l'état du plan est inchangé. Message métier sans détail technique.
                Session::flash('errors', [$e->getMessage()]);
            } catch (\PDOException $e) {
                Session::flash('errors', [$this->erreurTechnique($e, ($active ? 'restauration' : 'archivage') . " du plan #{$id}")]);
            }
        }
        $this->redirect(BASE_URL . '/platform/plans');
    }
}
