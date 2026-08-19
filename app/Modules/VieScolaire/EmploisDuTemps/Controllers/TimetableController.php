<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Controllers;

use App\Modules\VieScolaire\EmploisDuTemps\DTO\CreneauDTO;
use App\Modules\VieScolaire\EmploisDuTemps\DTO\RemplacementDTO;
use App\Modules\VieScolaire\EmploisDuTemps\DTO\TimetableDTO;
use App\Modules\VieScolaire\EmploisDuTemps\DTO\TimetableFiltersDTO;
use App\Modules\VieScolaire\EmploisDuTemps\Policies\TimetablePolicy;
use App\Modules\VieScolaire\EmploisDuTemps\Services\TimetableService;
use Core\Controller;
use Core\Database;

class TimetableController extends Controller
{
    private TimetableService $service;
    private TimetablePolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TimetableService();
        $this->policy  = new TimetablePolicy();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.view');

        $filters = TimetableFiltersDTO::fromRequest($_GET);
        $data    = $this->service->paginateEdts($filters);
        $db      = Database::getInstance()->getConnection();

        $classes = $db->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
        $annees  = $db->query("SELECT DISTINCT annee_scolaire FROM vs_emplois_du_temps ORDER BY annee_scolaire DESC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::emplois_du_temps/index', array_merge($data, [
            'user'    => $user,
            'classes' => $classes,
            'annees'  => $annees,
            'filters' => $filters,
        ]));
    }

    // ── Création d'un EDT ─────────────────────────────────────────────────────

    public function create(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.create');

        $db      = Database::getInstance()->getConnection();
        $classes = $db->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
        $periodes = $db->query("SELECT id, nom FROM periodes_scolaires ORDER BY date_debut")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::emplois_du_temps/create', [
            'user'    => $user,
            'classes' => $classes,
            'periodes'=> $periodes,
        ]);
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.create');
        $this->verifyCsrf();

        $dto    = TimetableDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect('/v2/vie-scolaire/emplois-du-temps/create');
            exit;
        }

        try {
            $edt = $this->service->findOrCreateEdt($dto, $user['id']);
            \Core\Session::flash('success', 'Emploi du temps créé.');
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edt['id']}");
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/vie-scolaire/emplois-du-temps/create');
        }
        exit;
    }

    // ── Affichage (grille visuelle) ───────────────────────────────────────────

    public function show(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.view');

        $edt = $this->service->findEdt($id);
        if ($edt === null) {
            http_response_code(404);
            $this->render('VieScolaire::emplois_du_temps/index', ['error' => 'Emploi du temps introuvable.', 'user' => $user]);
            return;
        }

        $grille   = $this->service->grille($id);
        $versions = $this->service->versions($id);

        $this->render('VieScolaire::emplois_du_temps/show', [
            'user'     => $user,
            'edt'      => $edt,
            'creneaux' => $grille['creneaux'],
            'grille'   => $grille['grille'],
            'plages'   => $grille['plages'],
            'versions' => $versions,
            'policy'   => $this->policy,
        ]);
    }

    // ── Ajout de créneau ─────────────────────────────────────────────────────

    public function ajouterCreneauForm(int $edtId): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.update');

        $edt = $this->service->findEdt($edtId);
        if ($edt === null || !$this->policy->canModifyEdt($user, $edt)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edtId}");
            exit;
        }

        $db       = Database::getInstance()->getConnection();
        $matieres = $db->query("SELECT id, nom, couleur FROM matieres WHERE actif = 1 ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
        $enseignants = $db->query("SELECT u.id, u.nom, u.prenom FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.nom = 'enseignant' ORDER BY u.nom")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::emplois_du_temps/creneaux/ajouter', [
            'user'        => $user,
            'edt'         => $edt,
            'matieres'    => $matieres,
            'enseignants' => $enseignants,
            'plages'      => $this->service->plages(),
            'salles'      => $this->service->salles(),
        ]);
    }

    public function storeCreneau(int $edtId): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $edt = $this->service->findEdt($edtId);
        if ($edt === null || !$this->policy->canModifyEdt($user, $edt)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edtId}");
            exit;
        }

        $dto    = CreneauDTO::fromRequest($_POST + ['emploi_du_temps_id' => $edtId, 'classe_id' => $edt['classe_id'], 'annee_scolaire' => $edt['annee_scolaire']]);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edtId}/creneaux/ajouter");
            exit;
        }

        try {
            $this->service->ajouterCreneau($dto, $user['id']);
            \Core\Session::flash('success', 'Créneau ajouté.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edtId}");
        exit;
    }

    // ── Modification de créneau ───────────────────────────────────────────────

    public function modifierCreneau(int $creneauId): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $db      = Database::getInstance()->getConnection();
        $stmt    = $db->prepare("SELECT * FROM vs_edt_creneaux WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$creneauId]);
        $creneau = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$creneau) {
            \Core\Session::flash('error', 'Créneau introuvable.');
            $this->redirect('/v2/vie-scolaire/emplois-du-temps');
            exit;
        }

        $edt = $this->service->findEdt($creneau['emploi_du_temps_id']);
        if (!$this->policy->canModifyEdt($user, $edt)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edt['id']}");
            exit;
        }

        $dto = CreneauDTO::fromRequest($_POST + [
            'emploi_du_temps_id' => $creneau['emploi_du_temps_id'],
            'classe_id'          => $creneau['classe_id'],
            'annee_scolaire'     => $creneau['annee_scolaire'],
        ]);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edt['id']}");
            exit;
        }

        try {
            $this->service->modifierCreneau($creneauId, $dto, $user['id']);
            \Core\Session::flash('success', 'Créneau modifié.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edt['id']}");
        exit;
    }

    // ── Suppression (soft) de créneau ─────────────────────────────────────────

    public function supprimerCreneau(int $creneauId): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT emploi_du_temps_id FROM vs_edt_creneaux WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$creneauId]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);

        $edtId = $row['emploi_du_temps_id'] ?? 0;

        try {
            $this->service->supprimerCreneau($creneauId, $user['id']);
            \Core\Session::flash('success', 'Créneau supprimé.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$edtId}");
        exit;
    }

    // ── Publication ───────────────────────────────────────────────────────────

    public function publier(int $id): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.publish');
        $this->verifyCsrf();

        $edt = $this->service->findEdt($id);
        if ($edt === null || !$this->policy->canPublishEdt($user, $edt)) {
            \Core\Session::flash('error', 'Action non autorisée.');
            $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$id}");
            exit;
        }

        try {
            $this->service->publierEdt($id, $user['id']);
            \Core\Session::flash('success', 'Emploi du temps publié.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect("/v2/vie-scolaire/emplois-du-temps/{$id}");
        exit;
    }

    // ── Vue enseignant ────────────────────────────────────────────────────────

    public function enseignant(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.view');

        $enseignantId = (int)($_GET['enseignant_id'] ?? $user['id']);
        $annee        = $_GET['annee'] ?? date('Y') . '-' . (date('Y') + 1);

        $data = $this->service->edtParEnseignant($enseignantId, $annee);
        $heures = $this->service->heuresEnseignant($enseignantId, $annee);

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, nom, prenom FROM users WHERE id = ?");
        $stmt->execute([$enseignantId]);
        $enseignant = $stmt->fetch(\PDO::FETCH_ASSOC);

        $enseignants = $db->query("SELECT u.id, u.nom, u.prenom FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.nom = 'enseignant' ORDER BY u.nom")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::emplois_du_temps/enseignant', [
            'user'         => $user,
            'enseignant'   => $enseignant,
            'enseignants'  => $enseignants,
            'creneaux'     => $data['creneaux'],
            'grille'       => $data['grille'],
            'plages'       => $data['plages'],
            'heures'       => $heures,
            'annee'        => $annee,
        ]);
    }

    // ── Remplacements ─────────────────────────────────────────────────────────

    public function remplacements(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.view');

        $date         = $_GET['date'] ?? date('Y-m-d');
        $remplacements = $this->service->remplacementsDuJour($date);

        $db   = Database::getInstance()->getConnection();
        $enseignants = $db->query("SELECT u.id, u.nom, u.prenom FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.nom = 'enseignant' ORDER BY u.nom")->fetchAll(\PDO::FETCH_ASSOC);
        $salles = $this->service->salles();

        $creneaux = $db->query("SELECT cr.id, cr.jour, p.libelle AS plage, c.nom AS classe, m.nom AS matiere FROM vs_edt_creneaux cr JOIN vs_edt_plages_horaires p ON p.id = cr.plage_id JOIN classes c ON c.id = cr.classe_id JOIN matieres m ON m.id = cr.matiere_id WHERE cr.deleted_at IS NULL ORDER BY c.nom, cr.jour, p.ordre")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('VieScolaire::emplois_du_temps/remplacements', [
            'user'          => $user,
            'date'          => $date,
            'remplacements' => $remplacements,
            'enseignants'   => $enseignants,
            'salles'        => $salles,
            'creneaux'      => $creneaux,
        ]);
    }

    public function storeRemplacement(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.update');
        $this->verifyCsrf();

        $dto    = RemplacementDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            \Core\Session::flash('error', implode('<br>', $errors));
            $this->redirect('/v2/vie-scolaire/emplois-du-temps/remplacements');
            exit;
        }

        try {
            $this->service->assignerRemplacement($dto, $user['id']);
            \Core\Session::flash('success', 'Remplacement enregistré.');
        } catch (\RuntimeException $e) {
            \Core\Session::flash('error', $e->getMessage());
        }
        $this->redirect('/v2/vie-scolaire/emplois-du-temps/remplacements');
        exit;
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.export');

        $classeId = (int)($_GET['classe_id'] ?? 0);
        $annee    = $_GET['annee'] ?? date('Y') . '-' . (date('Y') + 1);

        if (!$classeId) {
            $this->redirect('/v2/vie-scolaire/emplois-du-temps');
            exit;
        }

        $stats = $this->service->statistiquesClasse($classeId, $annee);

        $jours = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="emploi_du_temps_' . $classeId . '_' . $annee . '.csv"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Matière', 'Enseignant', 'Nb Créneaux'], ';');
        foreach ($stats as $row) {
            fputcsv($out, [
                $row['matiere_nom'],
                $row['enseignant_nom'] . ' ' . $row['enseignant_prenom'],
                $row['nb_creneaux'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $user = $this->requireAuth();
        $this->requirePermission('timetable.view');

        $classeId = (int)($_GET['classe_id'] ?? 0);
        $annee    = $_GET['annee'] ?? date('Y') . '-' . (date('Y') + 1);

        $db      = Database::getInstance()->getConnection();
        $classes = $db->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
        $stats   = $classeId ? $this->service->statistiquesClasse($classeId, $annee) : [];

        $this->render('VieScolaire::emplois_du_temps/statistiques', [
            'user'      => $user,
            'classes'   => $classes,
            'stats'     => $stats,
            'classeId'  => $classeId,
            'annee'     => $annee,
        ]);
    }

}
