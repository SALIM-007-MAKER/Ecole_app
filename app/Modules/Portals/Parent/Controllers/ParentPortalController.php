<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\Database;
use Core\EventDispatcher;

class ParentPortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'parent'; }

    /** GET /v2/portals/parent */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('parent', $etab, $userId, $perms);
        EventDispatcher::dispatch(new DashboardViewed('parent', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::Parent/dashboard', [
            'dashboard' => $dashboard,
            'enfants'   => $this->getMesEnfants($etab, $userId),
            'pageTitle' => 'Mon espace parent',
        ]);
    }

    /** GET /v2/portals/parent/enfants */
    public function enfants(): void
    {
        $this->requirePortalAccess();
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $enfants = $this->getMesEnfants($etab, $userId);

        $this->portalRender('Portals::Parent/enfants', [
            'enfants'   => $enfants,
            'pageTitle' => 'Mes enfants',
        ]);
    }

    /** GET /v2/portals/parent/notes */
    public function notes(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('notes.view_own');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $enfants = $this->getMesEnfants($etab, $userId);
        $notes   = $this->getEnfantsNotes($etab, $enfants);

        $this->portalRender('Portals::Parent/notes', [
            'enfants'   => $enfants,
            'notes'     => $notes,
            'pageTitle' => 'Notes de mes enfants',
        ]);
    }

    /** GET /v2/portals/parent/absences */
    public function absences(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('absences.view_own');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $enfants = $this->getMesEnfants($etab, $userId);
        $absences = $this->getEnfantsAbsences($etab, $enfants);

        $this->portalRender('Portals::Parent/absences', [
            'enfants'   => $enfants,
            'absences'  => $absences,
            'pageTitle' => 'Absences de mes enfants',
        ]);
    }

    /** GET /v2/portals/parent/paiements */
    public function paiements(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('finance.invoice.view');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $enfants = $this->getMesEnfants($etab, $userId);
        $factures = $this->getEnfantsFactures($etab, $enfants);

        $this->portalRender('Portals::Parent/paiements', [
            'enfants'   => $enfants,
            'factures'  => $factures,
            'pageTitle' => 'Paiements & factures',
        ]);
    }

    /** GET /v2/portals/parent/messagerie */
    public function messagerie(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('communication.view');
        $this->portalRender('Portals::Parent/messagerie', ['pageTitle' => 'Messagerie']);
    }

    /** GET /v2/portals/parent/documents */
    public function documents(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('document.view');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $docs    = $this->getEnfantsDocuments($etab, $userId);

        $this->portalRender('Portals::Parent/documents', [
            'documents' => $docs,
            'pageTitle' => 'Documents',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getMesEnfants(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT e.*, c.nom AS classe_nom, c.niveau,
                        u.nom AS user_nom, u.prenom AS user_prenom
                 FROM famille_eleve fe
                 JOIN famille_membres fm ON fm.famille_id = fe.famille_id
                 JOIN eleves e ON e.id = fe.eleve_id
                 LEFT JOIN classes c ON c.id = e.classe_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE fm.user_id = :uid AND e.etablissement_id = :etab
                   AND e.deleted_at IS NULL AND fe.deleted_at IS NULL
                 ORDER BY u.nom, u.prenom'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEnfantsNotes(int $etab, array $enfants): array
    {
        if (empty($enfants)) return [];
        try {
            $eleveUserIds = array_filter(array_column($enfants, 'user_id'));
            if (empty($eleveUserIds)) return [];
            $placeholders = implode(',', array_fill(0, count($eleveUserIds), '?'));
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                "SELECT n.*, m.nom AS matiere_nom, e.date_evaluation, e.titre AS eval_titre
                 FROM notes_v n
                 JOIN evaluations e ON e.id = n.evaluation_id
                 JOIN matieres m ON m.id = e.matiere_id
                 WHERE n.eleve_user_id IN ($placeholders) AND n.etablissement_id = ?
                   AND n.deleted_at IS NULL
                 ORDER BY n.eleve_user_id, e.date_evaluation DESC"
            );
            $stmt->execute([...$eleveUserIds, $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEnfantsAbsences(int $etab, array $enfants): array
    {
        if (empty($enfants)) return [];
        try {
            $eleveUserIds = array_filter(array_column($enfants, 'user_id'));
            if (empty($eleveUserIds)) return [];
            $placeholders = implode(',', array_fill(0, count($eleveUserIds), '?'));
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                "SELECT a.*, j.label AS justif_label, j.acceptee
                 FROM vs_absences a
                 LEFT JOIN vs_justifications_absences j ON j.absence_id = a.id
                 WHERE a.eleve_user_id IN ($placeholders) AND a.etablissement_id = ?
                   AND a.deleted_at IS NULL
                 ORDER BY a.eleve_user_id, a.date_absence DESC LIMIT 100"
            );
            $stmt->execute([...$eleveUserIds, $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEnfantsFactures(int $etab, array $enfants): array
    {
        if (empty($enfants)) return [];
        try {
            $eleveIds = array_filter(array_column($enfants, 'id'));
            if (empty($eleveIds)) return [];
            $placeholders = implode(',', array_fill(0, count($eleveIds), '?'));
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                "SELECT f.*, fp.statut AS paiement_statut, fp.montant_paye
                 FROM finance_factures f
                 LEFT JOIN finance_paiements fp ON fp.facture_id = f.id AND fp.deleted_at IS NULL
                 WHERE f.eleve_id IN ($placeholders) AND f.etablissement_id = ?
                   AND f.deleted_at IS NULL
                 ORDER BY f.date_emission DESC LIMIT 50"
            );
            $stmt->execute([...$eleveIds, $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEnfantsDocuments(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT d.*
                 FROM doc_documents d
                 WHERE d.etablissement_id = :etab AND d.deleted_at IS NULL
                   AND (d.shared_with_parents = 1 OR d.owner_user_id = :uid)
                 ORDER BY d.created_at DESC LIMIT 30'
            );
            $stmt->execute([':etab' => $etab, ':uid' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
