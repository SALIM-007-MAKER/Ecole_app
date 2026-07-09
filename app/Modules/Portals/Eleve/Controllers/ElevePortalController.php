<?php
declare(strict_types=1);

namespace App\Modules\Portals\Eleve\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\Database;
use Core\EventDispatcher;

class ElevePortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'eleve'; }

    /** GET /v2/portals/eleve */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('eleve', $etab, $userId, $perms);
        EventDispatcher::dispatch(new DashboardViewed('eleve', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::Eleve/dashboard', [
            'dashboard' => $dashboard,
            'pageTitle' => 'Mon espace',
        ]);
    }

    /** GET /v2/portals/eleve/notes */
    public function notes(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('notes.view_own');
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $notes  = $this->getMyNotes($etab, $userId);

        $this->portalRender('Portals::Eleve/notes', [
            'notes'     => $notes,
            'pageTitle' => 'Mes notes',
        ]);
    }

    /** GET /v2/portals/eleve/bulletins */
    public function bulletins(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('bulletins.view');
        $etab      = $this->getEtablissementId();
        $userId    = $this->getUserId();
        $bulletins = $this->getMyBulletins($etab, $userId);

        $this->portalRender('Portals::Eleve/bulletins', [
            'bulletins' => $bulletins,
            'pageTitle' => 'Mes bulletins',
        ]);
    }

    /** GET /v2/portals/eleve/absences */
    public function absences(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('absences.view_own');
        $etab     = $this->getEtablissementId();
        $userId   = $this->getUserId();
        $absences = $this->getMyAbsences($etab, $userId);

        $this->portalRender('Portals::Eleve/absences', [
            'absences'  => $absences,
            'pageTitle' => 'Mes absences',
        ]);
    }

    /** GET /v2/portals/eleve/emploi-du-temps */
    public function emploiDuTemps(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('emploi_du_temps.view_own');
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $edt    = $this->getMySchedule($etab, $userId);

        $this->portalRender('Portals::Eleve/emploi_du_temps', [
            'creneaux'  => $edt,
            'today'     => (int)date('N'),
            'pageTitle' => 'Mon emploi du temps',
        ]);
    }

    /** GET /v2/portals/eleve/bibliotheque */
    public function bibliotheque(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('biblio.view');
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $emprunts = $this->getMyLoans($etab, $userId);

        $this->portalRender('Portals::Eleve/bibliotheque', [
            'emprunts'  => $emprunts,
            'pageTitle' => 'Bibliothèque',
        ]);
    }

    /** GET /v2/portals/eleve/messagerie */
    public function messagerie(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('communication.view');
        $this->portalRender('Portals::Eleve/messagerie', ['pageTitle' => 'Messagerie']);
    }

    /** GET /v2/portals/eleve/profil */
    public function profil(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $eleve  = $this->getEleveData($etab, $userId);

        $this->portalRender('Portals::Eleve/profil', [
            'eleve'     => $eleve,
            'pageTitle' => 'Mon profil',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getMyNotes(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT n.*, m.nom AS matiere_nom, e_type.nom AS eval_type,
                        e.date_evaluation, e.titre AS eval_titre
                 FROM notes_v n
                 JOIN evaluations e ON e.id = n.evaluation_id
                 JOIN matieres m ON m.id = e.matiere_id
                 LEFT JOIN evaluations_types e_type ON e_type.id = e.type_id
                 WHERE n.eleve_user_id = :uid AND n.etablissement_id = :etab
                   AND n.deleted_at IS NULL
                 ORDER BY e.date_evaluation DESC LIMIT 30'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMyBulletins(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT b.*, p.nom AS periode_nom, p.annee_scolaire
                 FROM bulletins_v2 b
                 JOIN periodes_scolaires p ON p.id = b.periode_id
                 WHERE b.eleve_user_id = :uid AND b.etablissement_id = :etab
                   AND b.deleted_at IS NULL
                 ORDER BY p.date_debut DESC'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMyAbsences(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT a.*, j.label AS justif_label, j.acceptee
                 FROM vs_absences a
                 LEFT JOIN vs_justifications_absences j ON j.absence_id = a.id
                 WHERE a.eleve_user_id = :uid AND a.etablissement_id = :etab
                   AND a.deleted_at IS NULL
                 ORDER BY a.date_absence DESC LIMIT 50'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMySchedule(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT cr.*, edt.matiere_id, m.nom AS matiere_nom,
                        edt.enseignant_user_id,
                        CONCAT(u.prenom, " ", u.nom) AS enseignant_nom,
                        s.nom AS salle_nom
                 FROM vs_emplois_du_temps edt
                 JOIN vs_edt_creneaux cr ON cr.emploi_du_temps_id = edt.id
                 JOIN classes c ON c.id = edt.classe_id
                 JOIN matieres m ON m.id = edt.matiere_id
                 LEFT JOIN vs_edt_salles s ON s.id = cr.salle_id
                 LEFT JOIN users u ON u.id = edt.enseignant_user_id
                 WHERE edt.etablissement_id = :etab AND c.deleted_at IS NULL
                   AND EXISTS (
                     SELECT 1 FROM eleves el
                     WHERE el.classe_id = c.id AND el.user_id = :uid AND el.deleted_at IS NULL
                   )
                 ORDER BY cr.jour, cr.heure_debut'
            );
            $stmt->execute([':etab' => $etab, ':uid' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMyLoans(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT e.*, b.titre AS livre_titre, b.isbn
                 FROM biblio_emprunts e
                 JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
                 JOIN biblio_livres b ON b.id = ex.livre_id
                 WHERE e.user_id = :uid AND e.etablissement_id = :etab
                   AND e.date_retour IS NULL AND e.deleted_at IS NULL
                 ORDER BY e.date_echeance ASC'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etab]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEleveData(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT e.*, c.nom AS classe_nom, c.niveau
                 FROM eleves e
                 LEFT JOIN classes c ON c.id = e.classe_id
                 WHERE e.user_id = :uid AND e.etablissement_id = :etab
                   AND e.deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etab]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
