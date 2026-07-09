<?php
declare(strict_types=1);

namespace App\Modules\Portals\RH\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\Database;
use Core\EventDispatcher;

class RHPortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'rh'; }

    /** GET /v2/portals/rh */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('rh', $etab, $userId, $perms);
        EventDispatcher::dispatch(new DashboardViewed('rh', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::RH/dashboard', [
            'dashboard' => $dashboard,
            'kpi'       => $this->getRhKpi($etab),
            'pageTitle' => 'Tableau de bord — RH',
        ]);
    }

    /** GET /v2/portals/rh/presences */
    public function presences(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('rh.presence.view');
        $etab  = $this->getEtablissementId();
        $date  = $this->request->get('date') ?? date('Y-m-d');
        $data  = $this->getPresencesJour($etab, $date);

        $this->portalRender('Portals::RH/presences', [
            'presences' => $data,
            'date'      => $date,
            'pageTitle' => 'Présences du personnel',
        ]);
    }

    /** GET /v2/portals/rh/conges */
    public function conges(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('leave.manage');
        $etab    = $this->getEtablissementId();
        $statut  = $this->request->get('statut') ?? 'en_attente';
        $conges  = $this->getConges($etab, $statut);

        $this->portalRender('Portals::RH/conges', [
            'conges'    => $conges,
            'statut'    => $statut,
            'pageTitle' => 'Gestion des congés',
        ]);
    }

    /** GET /v2/portals/rh/contrats */
    public function contrats(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('contract.view');
        $etab     = $this->getEtablissementId();
        $expiring = $this->getContratsExpiration($etab);

        $this->portalRender('Portals::RH/contrats', [
            'contrats'  => $expiring,
            'pageTitle' => 'Contrats',
        ]);
    }

    /** GET /v2/portals/rh/formations */
    public function formations(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('training.view');
        $etab      = $this->getEtablissementId();
        $sessions  = $this->getFormationsEnCours($etab);

        $this->portalRender('Portals::RH/formations', [
            'sessions'  => $sessions,
            'pageTitle' => 'Formations',
        ]);
    }

    /** GET /v2/portals/rh/evaluations */
    public function evaluations(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('evaluation.manage');
        $etab  = $this->getEtablissementId();
        $evals = $this->getEvaluationsRH($etab);

        $this->portalRender('Portals::RH/evaluations', [
            'evaluations' => $evals,
            'pageTitle'   => 'Évaluations RH',
        ]);
    }

    /** GET /v2/portals/rh/documents */
    public function documents(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('hr_document.view');
        $etab  = $this->getEtablissementId();
        $docs  = $this->getRhDocuments($etab);

        $this->portalRender('Portals::RH/documents', [
            'documents' => $docs,
            'pageTitle' => 'Documents RH',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getRhKpi(int $etab): array
    {
        try {
            $pdo   = Database::getInstance()->getConnection();
            $today = date('Y-m-d');

            $effectif = $pdo->prepare(
                'SELECT COUNT(*) FROM rh_employes WHERE etablissement_id=? AND statut="actif" AND deleted_at IS NULL'
            );
            $effectif->execute([$etab]);
            $nbEmployes = (int)$effectif->fetchColumn();

            $presJour = $pdo->prepare(
                'SELECT COUNT(*) FROM rh_presences WHERE etablissement_id=? AND date_presence=? AND statut="present" AND deleted_at IS NULL'
            );
            $presJour->execute([$etab, $today]);
            $nbPresents = (int)$presJour->fetchColumn();

            $congesAtt = $pdo->prepare(
                'SELECT COUNT(*) FROM rh_conges WHERE etablissement_id=? AND statut="en_attente" AND deleted_at IS NULL'
            );
            $congesAtt->execute([$etab]);
            $nbCongesAttente = (int)$congesAtt->fetchColumn();

            $expirant = $pdo->prepare(
                'SELECT COUNT(*) FROM rh_contrats WHERE etablissement_id=? AND statut="actif"
                 AND date_fin BETWEEN ? AND DATE_ADD(?,INTERVAL 30 DAY) AND deleted_at IS NULL'
            );
            $expirant->execute([$etab, $today, $today]);
            $nbExpirant = (int)$expirant->fetchColumn();

            return [
                'nb_employes'       => $nbEmployes,
                'nb_presents'       => $nbPresents,
                'taux_presence'     => $nbEmployes > 0 ? round($nbPresents / $nbEmployes * 100, 1) : 0,
                'conges_en_attente' => $nbCongesAttente,
                'contrats_expirant' => $nbExpirant,
            ];
        } catch (\Throwable) {
            return ['nb_employes' => 0, 'nb_presents' => 0, 'taux_presence' => 0, 'conges_en_attente' => 0, 'contrats_expirant' => 0];
        }
    }

    private function getPresencesJour(int $etab, string $date): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT p.*, CONCAT(u.prenom," ",u.nom) AS employe_nom, emp.poste
                 FROM rh_presences p
                 JOIN rh_employes emp ON emp.id = p.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE p.etablissement_id = ? AND p.date_presence = ? AND p.deleted_at IS NULL
                 ORDER BY u.nom, u.prenom'
            );
            $stmt->execute([$etab, $date]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getConges(int $etab, string $statut): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT c.*, CONCAT(u.prenom," ",u.nom) AS employe_nom, emp.poste
                 FROM rh_conges c
                 JOIN rh_employes emp ON emp.id = c.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE c.etablissement_id = ? AND c.statut = ? AND c.deleted_at IS NULL
                 ORDER BY c.date_debut ASC'
            );
            $stmt->execute([$etab, $statut]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getContratsExpiration(int $etab): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT c.*, CONCAT(u.prenom," ",u.nom) AS employe_nom, emp.poste,
                        DATEDIFF(c.date_fin, CURDATE()) AS jours_restants
                 FROM rh_contrats c
                 JOIN rh_employes emp ON emp.id = c.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE c.etablissement_id = ? AND c.statut = "actif"
                   AND c.date_fin IS NOT NULL AND c.date_fin <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                   AND c.deleted_at IS NULL
                 ORDER BY c.date_fin ASC'
            );
            $stmt->execute([$etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getFormationsEnCours(int $etab): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT s.*, f.titre AS formation_titre, f.categorie,
                        COUNT(i.id) AS nb_inscrits
                 FROM rh_formations_sessions s
                 JOIN rh_formations_catalogue f ON f.id = s.catalogue_id
                 LEFT JOIN rh_formations_inscriptions i ON i.session_id = s.id AND i.deleted_at IS NULL
                 WHERE s.etablissement_id = ? AND s.statut IN ("planifiee","en_cours") AND s.deleted_at IS NULL
                 GROUP BY s.id
                 ORDER BY s.date_debut ASC'
            );
            $stmt->execute([$etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEvaluationsRH(int $etab): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT ev.*, CONCAT(u.prenom," ",u.nom) AS employe_nom
                 FROM rh_evaluations ev
                 JOIN rh_employes emp ON emp.id = ev.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE ev.etablissement_id = ? AND ev.statut IN ("planifiee","en_cours") AND ev.deleted_at IS NULL
                 ORDER BY ev.date_prevue ASC LIMIT 30'
            );
            $stmt->execute([$etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getRhDocuments(int $etab): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT d.*, CONCAT(u.prenom," ",u.nom) AS employe_nom
                 FROM rh_documents d
                 JOIN rh_employes emp ON emp.id = d.employe_id
                 LEFT JOIN users u ON u.id = emp.user_id
                 WHERE d.etablissement_id = ? AND d.deleted_at IS NULL
                 ORDER BY d.created_at DESC LIMIT 50'
            );
            $stmt->execute([$etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
