<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use Core\Tenant\TenantQuotaService;

/**
 * Consultation des quotas et de la consommation de ressources par
 * établissement (Phase 14.8). Lecture seule — les limites elles-mêmes sont
 * fixées au niveau plateforme (etablissements.storage_quota_mb / max_*),
 * dont l'administration est prévue Phase 14.10 (Super Admin SaaS).
 *
 * L'établissement cible est TOUJOURS celui de l'utilisateur connecté,
 * jamais un identifiant fourni par la requête.
 */
class QuotaController extends Controller
{
    private TenantQuotaService $quotas;

    public function __construct()
    {
        parent::__construct();
        $this->quotas = TenantQuotaService::make();
    }

    private function currentEtablissementId(): int
    {
        $user = Session::getUser();
        $etabId = $user['etablissement_id'] ?? null;
        if ($etabId === null) {
            $config = require ROOT_PATH . '/config/tenant.php';
            return (int)($config['default_id'] ?? 1);
        }
        return (int)$etabId;
    }

    public function index(): void
    {
        $this->requirePermission('quota.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/quotas', [
            'title'   => 'Stockage & Quotas',
            'limits'  => $this->quotas->getLimits($etabId),
            'usage'   => $this->quotas->getUsage($etabId),
            'alerts'  => $this->quotas->alerts($etabId),
            'history' => $this->quotas->history($etabId, 20),
        ]);
    }

    /** API JSON — consommation actuelle, quotas, alertes, historique. Réservée aux utilisateurs autorisés (quota.view). */
    public function api(): void
    {
        $this->requirePermission('quota.view');

        $etabId = $this->currentEtablissementId();

        $this->json([
            'limits'  => $this->quotas->getLimits($etabId),
            'usage'   => $this->quotas->getUsage($etabId),
            'alerts'  => $this->quotas->alerts($etabId),
            'history' => $this->quotas->history($etabId, 20),
        ]);
    }

    public function recalculate(): void
    {
        $this->requirePermission('quota.view');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $this->quotas->recalculate($etabId);

        \Core\Logger::security('QUOTA_RECALCULATED', "etablissement_id={$etabId} par user_id=" . (Session::getUser()['id'] ?? '?'));
        Session::flash('success', 'Usage recalculé.');
        $this->redirect(BASE_URL . '/parametres/quotas');
    }
}
