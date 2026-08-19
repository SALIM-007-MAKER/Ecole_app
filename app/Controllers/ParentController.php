<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\EleveModel;
use App\Models\AbsenceModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\AnnonceModel;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;

class ParentController extends Controller
{
    private EleveModel                $eleveModel;
    private NotificationModel         $notifModel;
    private AnnonceModel              $annonceModel;
    private JustificationModel        $justModel;
    private PeriodeScolaireRepository $periodeRepo;
    private BulletinRepository        $bulletinRepo;

    public function __construct()
    {
        parent::__construct();
        $this->eleveModel   = new EleveModel();
        $this->notifModel   = new NotificationModel();
        $this->annonceModel = new AnnonceModel();
        $this->justModel    = new JustificationModel();

        $this->periodeRepo  = new PeriodeScolaireRepository();
        $this->bulletinRepo = new BulletinRepository();
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $user = $this->requireParent();
        $enfants = $this->eleveModel->findByParent((int)$user['id']);

        // Pour chaque enfant : dernière moyenne, absences du mois, solde impayé
        foreach ($enfants as &$enfant) {
            $enfant->derniere_moyenne = $this->getDerniereMoyenne((int)$enfant->id, (int)$enfant->classe_id);
            $enfant->absences_mois    = $this->countAbsencesMois((int)$enfant->id);
            $enfant->solde_impaye     = $this->getSoldeImpaye((int)$enfant->id);
        }
        unset($enfant);

        $notifications = $this->notifModel->findUnreadForUser((int)$user['id'], 5);
        $annonces      = $this->annonceModel->findPubliees('parents');

        $this->render('parent/dashboard', [
            'title'         => 'Mon espace parent',
            'enfants'       => $enfants,
            'notifications' => $notifications,
            'annonces'      => array_slice($annonces, 0, 5),
        ]);
    }

    // ─── Notes ────────────────────────────────────────────────────────────────

    public function notes(): void
    {
        $user    = $this->requireParent();
        $enfants = $this->eleveModel->findByParent((int)$user['id']);

        if (empty($enfants)) {
            $this->render('parent/notes', [
                'title'   => 'Notes de mes enfants',
                'enfants' => [],
                'data'    => null,
            ]);
            return;
        }

        $periodes  = $this->periodeRepo->findForSelect();
        $eleveId   = (int)$this->request->get('eleve_id', $enfants[0]->id);
        $periodeId = (int)$this->request->get('periode_id', $periodes ? $periodes[0]->id : 0);

        $enfant   = $this->resolveEnfant((int)$eleveId, $enfants);
        $bulletin = null;
        if ($enfant && $periodeId) {
            $bulletin = $this->previewBulletinSafe((int)$enfant->id, $periodeId);
        }

        $this->render('parent/notes', [
            'title'     => 'Notes de mes enfants',
            'enfants'   => $enfants,
            'enfant'    => $enfant,
            'periodes'  => $periodes,
            'periodeId' => $periodeId,
            'bulletin'  => $bulletin,
        ]);
    }

    // ─── Bulletin ─────────────────────────────────────────────────────────────

    public function bulletin(): void
    {
        $user     = $this->requireParent();
        $enfants  = $this->eleveModel->findByParent((int)$user['id']);
        $periodes = $this->periodeRepo->findForSelect();

        $eleveId   = (int)$this->request->get('eleve_id', $enfants[0]->id ?? 0);
        $periodeId = (int)$this->request->get('periode_id', $periodes[0]->id ?? 0);

        $enfant   = $this->resolveEnfant((int)$eleveId, $enfants);
        $bulletin = null;

        if ($enfant && $periodeId) {
            $bulletin = $this->previewBulletinSafe((int)$enfant->id, $periodeId);
        }

        $this->render('parent/bulletin', [
            'title'     => 'Bulletin scolaire',
            'enfants'   => $enfants,
            'enfant'    => $enfant,
            'periodes'  => $periodes,
            'periodeId' => $periodeId,
            'bulletin'  => $bulletin,
        ]);
    }

    // ─── Absences ─────────────────────────────────────────────────────────────

    public function absences(): void
    {
        $user    = $this->requireParent();
        $enfants = $this->eleveModel->findByParent((int)$user['id']);

        $eleveId = (int)$this->request->get('eleve_id', $enfants[0]->id ?? 0);
        $enfant  = $this->resolveEnfant((int)$eleveId, $enfants);

        $absences      = [];
        $stats         = null;
        $absModel      = new AbsenceModel();

        if ($enfant) {
            // Note : `absences` n'a pas de colonne matiere_id (une absence est
            // par journée/session, pas par matière) — pas de jointure possible
            // vers `matieres`, la vue affiche donc "-" pour cette colonne.
            $absences = $absModel->query(
                "SELECT a.*, j.motif, j.statut AS justification_statut,
                        (a.statut_justif = 'justifiee') AS justifiee,
                        CONCAT(u.prenom, ' ', u.nom) AS enseignant_nom
                 FROM `absences` a
                 LEFT JOIN `justifications` j ON j.absence_id = a.id
                 LEFT JOIN `users` u ON u.id = a.signale_par
                 WHERE a.eleve_id = ?
                 ORDER BY a.date_absence DESC, a.session",
                [(int)$enfant->id]
            );

            $stats = $absModel->queryOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN type = 'absence' THEN 1 ELSE 0 END) AS absences,
                    SUM(CASE WHEN type = 'retard'  THEN 1 ELSE 0 END) AS retards,
                    SUM(CASE WHEN statut_justif = 'justifiee' THEN 1 ELSE 0 END) AS justifiees
                 FROM `absences` a
                 WHERE a.eleve_id = ?",
                [(int)$enfant->id]
            );
        }

        $this->render('parent/absences', [
            'title'    => 'Absences de mes enfants',
            'enfants'  => $enfants,
            'enfant'   => $enfant,
            'absences' => $absences,
            'stats'    => $stats,
        ]);
    }

    // ─── Justifier une absence ────────────────────────────────────────────────

    public function justifier(string $id): void
    {
        $this->requireParent();
        $this->verifyCsrf();

        $motif = trim($this->request->post('motif', ''));
        if (empty($motif)) {
            Session::flash('error', 'Le motif est obligatoire.');
            $this->redirect(BASE_URL . '/parent/absences');
            return;
        }

        $absModel = new AbsenceModel();
        $absence  = $absModel->findById((int)$id);
        if (!$absence) {
            Session::flash('error', 'Absence introuvable.');
            $this->redirect(BASE_URL . '/parent/absences');
            return;
        }

        // Vérifier que l'absence appartient bien à un enfant du parent
        $user    = $this->currentUser();
        $enfants = $this->eleveModel->findByParent((int)$user['id']);
        $ids     = array_column($enfants, 'id');
        if (!in_array($absence->eleve_id, $ids, false)) {
            Session::flash('error', 'Action non autorisée.');
            $this->redirect(BASE_URL . '/parent/absences');
            return;
        }

        $this->justModel->upsert((int)$id, (int)$user['id'], $motif, null);
        $absModel->updateStatutJustif((int)$id, 'en_attente');

        Session::flash('success', 'Justification soumise avec succès.');
        $this->redirect(BASE_URL . '/parent/absences?eleve_id=' . $absence->eleve_id);
    }

    // ─── Paiements ────────────────────────────────────────────────────────────
    // Migré vers Finance V2 : voir FactureController::mesPaiements()
    // (GET /v2/finance/mes-paiements), source exclusive finance_factures/
    // finance_paiements. Ancienne route /parent/paiements supprimée.

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function requireParent(): array
    {
        $this->requireAuth();
        $user = $this->currentUser();
        if ($user['role'] !== 'parent') {
            $this->redirect(BASE_URL . '/dashboard');
            exit;
        }
        return $user;
    }

    private function resolveEnfant(int $eleveId, array $enfants): ?object
    {
        foreach ($enfants as $e) {
            if ((int)$e->id === $eleveId) return $e;
        }
        return $enfants[0] ?? null;
    }

    private function getDerniereMoyenne(int $eleveId, int $classeId): ?float
    {
        return $this->bulletinRepo->derniereMoyenne($eleveId);
    }

    /** Génère un aperçu de bulletin sans persister ; null si aucune donnée exploitable. */
    private function previewBulletinSafe(int $eleveId, int $periodeId): ?\App\Modules\Academique\DTO\BulletinData
    {
        try {
            $etablissementId = (int)($this->currentUser()['etablissement_id'] ?? 1);
            $generator = BulletinEngineFactory::make($etablissementId);
            return $generator->previewBulletin($eleveId, $periodeId);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function countAbsencesMois(int $eleveId): int
    {
        $r = $this->eleveModel->queryOne(
            "SELECT COUNT(*) AS n FROM `absences`
             WHERE `eleve_id` = ?
             AND `date_absence` >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$eleveId]
        );
        return (int)($r?->n ?? 0);
    }

    /**
     * Source V2 exclusive : finance_factures (créances non soldées).
     * Migré depuis frais_eleves/paiements (V1) — ces tables sont figées
     * depuis la bascule Finance V1→V2 et ne reflètent plus les frais réels.
     */
    private function getSoldeImpaye(int $eleveId): float
    {
        $r = $this->eleveModel->queryOne(
            "SELECT COALESCE(SUM(montant_total - montant_paye), 0) AS solde
             FROM `finance_factures`
             WHERE eleve_id = ? AND statut IN ('emise','partiellement_payee','en_retard')",
            [$eleveId]
        );
        return (float)($r?->solde ?? 0);
    }
}
