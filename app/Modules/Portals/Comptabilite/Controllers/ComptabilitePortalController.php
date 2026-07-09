<?php
declare(strict_types=1);

namespace App\Modules\Portals\Comptabilite\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\Database;
use Core\EventDispatcher;

class ComptabilitePortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'comptabilite'; }

    /** GET /v2/portals/comptabilite */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('comptabilite', $etab, $userId, $perms);
        EventDispatcher::dispatch(new DashboardViewed('comptabilite', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::Comptabilite/dashboard', [
            'dashboard'    => $dashboard,
            'kpi'          => $this->getDailyKpi($etab),
            'pageTitle'    => 'Tableau de bord — Comptabilité',
        ]);
    }

    /** GET /v2/portals/comptabilite/factures */
    public function factures(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.invoice.view');
        $etab    = $this->getEtablissementId();
        $page    = max(1, (int)($this->request->get('page') ?? 1));
        $statut  = $this->request->get('statut') ?? '';
        $factures = $this->getFactures($etab, $page, $statut);

        $this->portalRender('Portals::Comptabilite/factures', [
            'factures'  => $factures,
            'page'      => $page,
            'statut'    => $statut,
            'pageTitle' => 'Factures',
        ]);
    }

    /** GET /v2/portals/comptabilite/paiements */
    public function paiements(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.payment.view');
        $etab     = $this->getEtablissementId();
        $page     = max(1, (int)($this->request->get('page') ?? 1));
        $paiements = $this->getPaiements($etab, $page);

        $this->portalRender('Portals::Comptabilite/paiements', [
            'paiements' => $paiements,
            'page'      => $page,
            'pageTitle' => 'Paiements',
        ]);
    }

    /** GET /v2/portals/comptabilite/caisse */
    public function caisse(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.caisse.view');
        $etab    = $this->getEtablissementId();
        $journal = $this->getCaisseJournal($etab);

        $this->portalRender('Portals::Comptabilite/caisse', [
            'journal'   => $journal,
            'pageTitle' => 'Caisse',
        ]);
    }

    /** GET /v2/portals/comptabilite/impayes */
    public function impayes(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.invoice.view');
        $etab    = $this->getEtablissementId();
        $impayes = $this->getImpayes($etab);

        $this->portalRender('Portals::Comptabilite/impayes', [
            'impayes'   => $impayes,
            'pageTitle' => 'Impayés',
        ]);
    }

    /** GET /v2/portals/comptabilite/rapports */
    public function rapports(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.report.view');
        $etab  = $this->getEtablissementId();
        $mois  = $this->request->get('mois') ?? date('Y-m');
        $stats = $this->getMonthlyStats($etab, $mois);

        $this->portalRender('Portals::Comptabilite/rapports', [
            'stats'     => $stats,
            'mois'      => $mois,
            'pageTitle' => 'Rapports financiers',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getDailyKpi(int $etab): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');
            $month = date('Y-m');

            $encaisse = $pdo->prepare(
                'SELECT COALESCE(SUM(montant),0) FROM finance_paiements
                 WHERE etablissement_id=? AND DATE(date_paiement)=? AND deleted_at IS NULL'
            );
            $encaisse->execute([$etab, $today]);
            $encaisseJour = (float)$encaisse->fetchColumn();

            $attente = $pdo->prepare(
                'SELECT COUNT(*), COALESCE(SUM(montant_total - montant_paye),0)
                 FROM finance_factures
                 WHERE etablissement_id=? AND statut IN ("emise","partielle") AND deleted_at IS NULL'
            );
            $attente->execute([$etab]);
            [$nbImpayes, $montantImpayes] = $attente->fetch(\PDO::FETCH_NUM);

            $mensuel = $pdo->prepare(
                'SELECT COALESCE(SUM(montant),0) FROM finance_paiements
                 WHERE etablissement_id=? AND DATE_FORMAT(date_paiement,"%Y-%m")=? AND deleted_at IS NULL'
            );
            $mensuel->execute([$etab, $month]);
            $recettesMois = (float)$mensuel->fetchColumn();

            return [
                'encaisse_jour'   => $encaisseJour,
                'impayes_nb'      => (int)$nbImpayes,
                'impayes_montant' => (float)$montantImpayes,
                'recettes_mois'   => $recettesMois,
            ];
        } catch (\Throwable) {
            return ['encaisse_jour' => 0, 'impayes_nb' => 0, 'impayes_montant' => 0, 'recettes_mois' => 0];
        }
    }

    private function getFactures(int $etab, int $page, string $statut): array
    {
        try {
            $perPage = 25;
            $offset  = ($page - 1) * $perPage;
            $where   = $statut !== '' ? "AND f.statut = ?" : '';
            $params  = $statut !== '' ? [$etab, $statut, $perPage, $offset] : [$etab, $perPage, $offset];
            $pdo     = Database::getInstance()->getConnection();
            $stmt    = $pdo->prepare(
                "SELECT f.*, CONCAT(u.prenom,' ',u.nom) AS eleve_nom
                 FROM finance_factures f
                 LEFT JOIN eleves e ON e.id = f.eleve_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE f.etablissement_id = ? $where AND f.deleted_at IS NULL
                 ORDER BY f.date_emission DESC LIMIT ? OFFSET ?"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getPaiements(int $etab, int $page): array
    {
        try {
            $perPage = 25;
            $offset  = ($page - 1) * $perPage;
            $pdo     = Database::getInstance()->getConnection();
            $stmt    = $pdo->prepare(
                'SELECT p.*, f.numero AS facture_num,
                        CONCAT(u.prenom," ",u.nom) AS eleve_nom
                 FROM finance_paiements p
                 JOIN finance_factures f ON f.id = p.facture_id
                 LEFT JOIN eleves e ON e.id = f.eleve_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE p.etablissement_id = ? AND p.deleted_at IS NULL
                 ORDER BY p.date_paiement DESC LIMIT ? OFFSET ?'
            );
            $stmt->execute([$etab, $perPage, $offset]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getCaisseJournal(int $etab): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT * FROM finance_caisse_mouvements
                 WHERE etablissement_id = ? AND deleted_at IS NULL
                 ORDER BY created_at DESC LIMIT 50'
            );
            $stmt->execute([$etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getImpayes(int $etab): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT f.*, CONCAT(u.prenom," ",u.nom) AS eleve_nom,
                        (f.montant_total - COALESCE(f.montant_paye,0)) AS reste_a_payer
                 FROM finance_factures f
                 LEFT JOIN eleves e ON e.id = f.eleve_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE f.etablissement_id = ? AND f.statut IN ("emise","partielle")
                   AND f.deleted_at IS NULL
                 ORDER BY f.date_echeance ASC LIMIT 100'
            );
            $stmt->execute([$etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMonthlyStats(int $etab, string $mois): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(montant),0) AS total,
                        COUNT(*) AS nb,
                        mode_paiement
                 FROM finance_paiements
                 WHERE etablissement_id = ? AND DATE_FORMAT(date_paiement,"%Y-%m") = ?
                   AND deleted_at IS NULL
                 GROUP BY mode_paiement'
            );
            $stmt->execute([$etab, $mois]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
