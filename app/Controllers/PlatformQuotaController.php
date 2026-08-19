<?php

namespace App\Controllers;

use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;
use Core\Session;
use Core\Tenant\TenantQuotaService;

/**
 * Consultation des quotas et de la consommation de ressources —
 * Administration de la plateforme (T026). Anciennement `QuotaController`
 * (self-service établissement) ; déplacé ici — voir
 * SAAS_CONFIGURATION_METIER_REPORT.md.
 */
class PlatformQuotaController extends PlatformController
{
    private TenantQuotaService $quotas;
    private PlatformEtablissementService $etabs;

    public function __construct()
    {
        parent::__construct();
        $this->quotas = TenantQuotaService::make();
        $this->etabs  = PlatformEtablissementService::make();
    }

    public function index(int $id): void
    {
        $this->requirePlatformAuth();

        $etab = $this->etabs->find($id);
        if ($etab === null) {
            http_response_code(404);
            $this->render('errors/404', [], 'none');
            return;
        }

        $this->render('platform/etablissements/quotas', [
            'title'   => 'Quotas — ' . $etab['nom'],
            'etab'    => $etab,
            'limits'  => $this->quotas->getLimits($id),
            'usage'   => $this->quotas->getUsage($id),
            'alerts'  => $this->quotas->alerts($id),
            'history' => $this->quotas->history($id, 20),
        ], 'platform');
    }

    public function api(int $id): void
    {
        $this->requirePlatformAuth();

        $this->json([
            'limits'  => $this->quotas->getLimits($id),
            'usage'   => $this->quotas->getUsage($id),
            'alerts'  => $this->quotas->alerts($id),
            'history' => $this->quotas->history($id, 20),
        ]);
    }

    public function recalculate(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $this->quotas->recalculate($id);

        \Core\Logger::security('QUOTA_RECALCULATED', "etablissement_id={$id} par opérateur plateforme");
        Session::flash('success', 'Usage recalculé.');
        $this->redirect(BASE_URL . '/platform/etablissements/' . $id . '/quotas');
    }
}
