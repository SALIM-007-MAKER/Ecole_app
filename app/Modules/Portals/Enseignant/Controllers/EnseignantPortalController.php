<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Controllers;

use App\Modules\Portals\Controllers\PortalBaseController;
use App\Modules\Portals\Events\DashboardViewed;
use Core\Database;
use Core\EventDispatcher;

class EnseignantPortalController extends PortalBaseController
{
    protected function getPortalName(): string { return 'enseignant'; }

    /** GET /v2/portals/enseignant */
    public function dashboard(): void
    {
        $this->requirePortalAccess();
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $perms  = $this->getUserPerms();

        $dashboard = $this->dashboardEngine->build('enseignant', $etab, $userId, $perms);
        EventDispatcher::dispatch(new DashboardViewed('enseignant', $userId, $etab, count($dashboard->widgets)));

        if ($this->wantsJson()) {
            $this->portalJson(['dashboard' => $dashboard->toArray()]);
            return;
        }
        $this->portalRender('Portals::Enseignant/dashboard', [
            'dashboard' => $dashboard,
            'pageTitle' => 'Mon espace enseignant',
        ]);
    }

    /** GET /v2/portals/enseignant/mes-classes */
    public function mesClasses(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('academique.notes.manage');
        $etab       = $this->getEtablissementId();
        $userId     = $this->getUserId();
        $classes    = $this->getMyClasses($etab, $userId);

        $this->portalRender('Portals::Enseignant/mes_classes', [
            'classes'   => $classes,
            'pageTitle' => 'Mes classes',
        ]);
    }

    /** GET /v2/portals/enseignant/notes */
    public function notes(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('academique.notes.manage');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $classes = $this->getMyClasses($etab, $userId);

        $this->portalRender('Portals::Enseignant/notes', [
            'classes'   => $classes,
            'pageTitle' => 'Saisie des notes',
        ]);
    }

    /** GET /v2/portals/enseignant/appel */
    public function appel(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('attendance.session.create');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $classes = $this->getMyClasses($etab, $userId);

        $this->portalRender('Portals::Enseignant/appel', [
            'classes'   => $classes,
            'date'      => date('Y-m-d'),
            'pageTitle' => 'Faire l\'appel',
        ]);
    }

    /** GET /v2/portals/enseignant/evaluations */
    public function evaluations(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('academique.evaluations.manage');
        $etab    = $this->getEtablissementId();
        $userId  = $this->getUserId();
        $classes = $this->getMyClasses($etab, $userId);

        $this->portalRender('Portals::Enseignant/evaluations', [
            'classes'   => $classes,
            'pageTitle' => 'Évaluations',
        ]);
    }

    /** GET /v2/portals/enseignant/emploi-du-temps */
    public function emploiDuTemps(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('timetable.view');
        $etab   = $this->getEtablissementId();
        $userId = $this->getUserId();
        $edt    = $this->getMySchedule($etab, $userId);

        $this->portalRender('Portals::Enseignant/emploi_du_temps', [
            'creneaux'  => $edt,
            'today'     => date('N'), // 1=lundi
            'pageTitle' => 'Mon emploi du temps',
        ]);
    }

    /** GET /v2/portals/enseignant/absences */
    public function absences(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('attendance.session.validate');
        $this->portalRender('Portals::Enseignant/absences', ['pageTitle' => 'Absences de mes classes']);
    }

    /** GET /v2/portals/enseignant/bulletins */
    public function bulletins(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('academique.bulletin.view');
        $this->portalRender('Portals::Enseignant/bulletins', ['pageTitle' => 'Bulletins']);
    }

    /** GET /v2/portals/enseignant/documents */
    public function documents(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('document.view');
        $this->redirect(BASE_URL . '/v2/documents');
    }

    /** GET /v2/portals/enseignant/messagerie */
    public function messagerie(): void
    {
        $this->requirePortalAccess();
        $this->requirePermission('communication.view');
        $this->portalRender('Portals::Enseignant/messagerie', ['pageTitle' => 'Messagerie']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getMyClasses(int $etab, int $userId): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT DISTINCT c.id, c.nom, c.niveau, c.section, m.id AS matiere_id, m.nom AS matiere_nom
                 FROM classes c
                 JOIN rh_affectation_matieres am ON am.classe_id = c.id
                 JOIN rh_employes emp ON emp.id = am.employe_id
                 JOIN users u ON u.linked_id = emp.id AND u.role = "enseignant"
                 JOIN matieres m ON m.id = am.matiere_id
                 WHERE c.etablissement_id = :etab AND u.id = :uid AND c.deleted_at IS NULL
                 ORDER BY ' . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ', c.nom'
            );
            $stmt->execute([':etab' => $etab, ':uid' => $userId]);
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
                'SELECT edt.*, cr.jour, cr.heure_debut, cr.heure_fin, cr.salle_id,
                        c.nom AS classe_nom, m.nom AS matiere_nom, s.nom AS salle_nom
                 FROM vs_emplois_du_temps edt
                 JOIN vs_edt_creneaux cr ON cr.emploi_du_temps_id = edt.id
                 JOIN classes c ON c.id = edt.classe_id
                 JOIN matieres m ON m.id = edt.matiere_id
                 LEFT JOIN vs_edt_salles s ON s.id = cr.salle_id
                 WHERE edt.etablissement_id = :etab AND edt.enseignant_user_id = :uid
                   AND edt.deleted_at IS NULL
                 ORDER BY cr.jour, cr.heure_debut'
            );
            $stmt->execute([':etab' => $etab, ':uid' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
