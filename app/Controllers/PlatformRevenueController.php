<?php

namespace App\Controllers;

use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformController;
use Core\Platform\PlatformPaymentService;
use Core\Platform\PlatformRevenueService;
use Core\Database;

/**
 * Revenus de la plateforme (encaissements manuels). super_admin uniquement. Totaux toujours PAR DEVISE (aucune conversion).
 * L'export CSV est lui-même inscrit au journal d'audit global avant d'être servi.
 */
class PlatformRevenueController extends PlatformController
{
    private function periode(): array
    {
        return PlatformRevenueService::periode((string)$this->request->get('du', ''), (string)$this->request->get('au', ''));
    }

    public function index(): void
    {
        $this->requirePlatformLevel('super_admin');
        $p = $this->periode();
        $actif = PlatformPaymentService::disponible(Database::getInstance()->getConnection());
        $svc = PlatformRevenueService::make();

        $this->render('platform/revenus/index', [
            'title'      => 'Revenus',
            'actif'      => $actif,
            'periode'    => $p,
            'synthese'   => $actif ? $svc->synthese($p['du'], $p['au']) : null,
            'paiements'  => $actif ? $svc->paiements($p['du'], $p['au']) : [],
            'modes'      => PlatformPaymentService::MODES,
        ], 'platform');
    }

    public function export(): void
    {
        $this->requirePlatformLevel('super_admin');
        $p = $this->periode();
        if (!PlatformPaymentService::disponible(Database::getInstance()->getConnection())) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Indisponible'], 'none');
            return;
        }
        PlatformAuditService::make()->record('revenue.exported', ['entity_type' => 'platform_payments', 'apres' => ['du' => $p['du'], 'au' => $p['au'], 'lignes_max' => PlatformRevenueService::EXPORT_MAX]]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="revenus-plateforme-' . $p['du'] . '_' . $p['au'] . '.csv"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM UTF-8 : accents corrects dans Excel
        fputcsv($out, ['reference', 'date_paiement', 'etablissement', 'plan', 'cycle', 'montant', 'devise', 'mode', 'reference_externe', 'statut', 'motif_annulation', 'note'], ';');
        foreach (PlatformRevenueService::make()->iterer($p['du'], $p['au']) as $l) {
            $l['mode'] = PlatformPaymentService::MODES[$l['mode']] ?? $l['mode'];
            fputcsv($out, array_map([PlatformAuditController::class, 'neutraliserFormule'], array_values($l)), ';');
        }
        fclose($out);
        exit;
    }
}
