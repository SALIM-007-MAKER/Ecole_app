<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\EleveModel;
use App\Models\NoteModel;
use App\Models\PeriodeModel;
use App\Models\AbsenceModel;
use App\Models\NotificationModel;
use App\Models\AnnonceModel;

class ParentController extends Controller
{
    private EleveModel        $eleveModel;
    private NoteModel         $noteModel;
    private PeriodeModel      $periodeModel;
    private NotificationModel $notifModel;
    private AnnonceModel      $annonceModel;

    public function __construct()
    {
        parent::__construct();
        $this->eleveModel   = new EleveModel();
        $this->noteModel    = new NoteModel();
        $this->periodeModel = new PeriodeModel();
        $this->notifModel   = new NotificationModel();
        $this->annonceModel = new AnnonceModel();
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

        $periodes  = $this->periodeModel->findAll('id');
        $eleveId   = (int)$this->request->get('eleve_id', $enfants[0]->id);
        $periodeId = (int)$this->request->get('periode_id', $periodes ? $periodes[0]->id : 0);

        $enfant = $this->resolveEnfant((int)$eleveId, $enfants);
        $data   = null;
        if ($enfant && $periodeId) {
            $data = $this->noteModel->getBulletinData(
                (int)$enfant->id,
                (int)$enfant->classe_id,
                $periodeId
            );
        }

        $this->render('parent/notes', [
            'title'     => 'Notes de mes enfants',
            'enfants'   => $enfants,
            'enfant'    => $enfant,
            'periodes'  => $periodes,
            'periodeId' => $periodeId,
            'data'      => $data,
        ]);
    }

    // ─── Bulletin ─────────────────────────────────────────────────────────────

    public function bulletin(): void
    {
        $user    = $this->requireParent();
        $enfants = $this->eleveModel->findByParent((int)$user['id']);
        $periodes = $this->periodeModel->findAll('id');

        $eleveId   = (int)$this->request->get('eleve_id', $enfants[0]->id ?? 0);
        $periodeId = (int)$this->request->get('periode_id', $periodes[0]->id ?? 0);

        $enfant  = $this->resolveEnfant((int)$eleveId, $enfants);
        $periode = $periodeId ? $this->periodeModel->findById($periodeId) : null;
        $data    = null;

        if ($enfant && $periodeId) {
            $data = $this->noteModel->getBulletinData(
                (int)$enfant->id,
                (int)$enfant->classe_id,
                $periodeId
            );
        }

        $this->render('parent/bulletin', [
            'title'     => 'Bulletin scolaire',
            'enfants'   => $enfants,
            'enfant'    => $enfant,
            'periodes'  => $periodes,
            'periode'   => $periode,
            'periodeId' => $periodeId,
            'data'      => $data,
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
            $absences = $absModel->query(
                "SELECT a.*, j.motif, j.valide,
                        CONCAT(u.prenom, ' ', u.nom) AS enseignant_nom,
                        m.nom AS matiere_nom
                 FROM `absences` a
                 LEFT JOIN `justifications` j ON j.absence_id = a.id
                 LEFT JOIN `users` u ON u.id = a.saisie_par
                 LEFT JOIN `matieres` m ON m.id = a.matiere_id
                 WHERE a.eleve_id = ?
                 ORDER BY a.date_absence DESC, a.session",
                [(int)$enfant->id]
            );

            $stats = $absModel->queryOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN type = 'absence' THEN 1 ELSE 0 END) AS absences,
                    SUM(CASE WHEN type = 'retard'  THEN 1 ELSE 0 END) AS retards,
                    SUM(CASE WHEN j.valide = 1 THEN 1 ELSE 0 END) AS justifiees
                 FROM `absences` a
                 LEFT JOIN `justifications` j ON j.absence_id = a.id
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

        $absModel->execute(
            'INSERT INTO `justifications` (`absence_id`, `motif`, `valide`, `created_at`)
             VALUES (?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE `motif` = VALUES(`motif`)',
            [(int)$id, $motif]
        );

        Session::flash('success', 'Justification soumise avec succès.');
        $this->redirect(BASE_URL . '/parent/absences?eleve_id=' . $absence->eleve_id);
    }

    // ─── Paiements ────────────────────────────────────────────────────────────

    public function paiements(): void
    {
        $user    = $this->requireParent();
        $enfants = $this->eleveModel->findByParent((int)$user['id']);

        $eleveId = (int)$this->request->get('eleve_id', $enfants[0]->id ?? 0);
        $annee   = $this->request->get('annee', $this->currentAnnee());
        $enfant  = $this->resolveEnfant((int)$eleveId, $enfants);

        $frais     = [];
        $paiements = [];
        $totaux    = ['total_frais' => 0, 'total_paye' => 0, 'total_reste' => 0];

        if ($enfant) {
            $frais = $this->eleveModel->query(
                "SELECT fe.*, ft.nom AS frais_nom, ft.categorie,
                        COALESCE(SUM(p.montant), 0) AS total_paye,
                        fe.montant - COALESCE(SUM(p.montant), 0) AS reste
                 FROM `frais_eleves` fe
                 JOIN `frais_types` ft ON ft.id = fe.frais_type_id
                 LEFT JOIN `paiements` p ON p.frais_eleve_id = fe.id
                 WHERE fe.eleve_id = ? AND fe.annee_scolaire = ?
                 GROUP BY fe.id
                 ORDER BY fe.date_echeance",
                [(int)$enfant->id, $annee]
            );

            $paiements = $this->eleveModel->query(
                "SELECT p.*, ft.nom AS frais_nom
                 FROM `paiements` p
                 JOIN `frais_eleves` fe ON fe.id = p.frais_eleve_id
                 JOIN `frais_types` ft ON ft.id = fe.frais_type_id
                 WHERE fe.eleve_id = ? AND fe.annee_scolaire = ?
                 ORDER BY p.date_paiement DESC",
                [(int)$enfant->id, $annee]
            );

            foreach ($frais as $f) {
                $totaux['total_frais'] += (float)$f->montant;
                $totaux['total_paye']  += (float)$f->total_paye;
                $totaux['total_reste'] += (float)$f->reste;
            }
        }

        $annees = $this->anneesOptions();

        $this->render('parent/paiements', [
            'title'     => 'Scolarité et paiements',
            'enfants'   => $enfants,
            'enfant'    => $enfant,
            'frais'     => $frais,
            'paiements' => $paiements,
            'totaux'    => $totaux,
            'annee'     => $annee,
            'annees'    => $annees,
        ]);
    }

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
        $r = $this->noteModel->queryOne(
            "SELECT mg.moyenne_generale
             FROM `moyennes_generales` mg
             JOIN `periodes` p ON p.id = mg.periode_id
             WHERE mg.eleve_id = ? AND mg.classe_id = ?
             ORDER BY p.date_debut DESC
             LIMIT 1",
            [$eleveId, $classeId]
        );
        return $r ? (float)$r->moyenne_generale : null;
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

    private function getSoldeImpaye(int $eleveId): float
    {
        $r = $this->eleveModel->queryOne(
            "SELECT COALESCE(SUM(fe.montant - COALESCE(p_sum.total,0)), 0) AS solde
             FROM `frais_eleves` fe
             LEFT JOIN (
                 SELECT frais_eleve_id, SUM(montant) AS total
                 FROM `paiements` GROUP BY frais_eleve_id
             ) p_sum ON p_sum.frais_eleve_id = fe.id
             WHERE fe.eleve_id = ? AND fe.statut != 'paye'",
            [$eleveId]
        );
        return (float)($r?->solde ?? 0);
    }



    private function anneesOptions(): array
    {
        $y   = (int)date('Y');
        $m   = (int)date('m');
        $cur = $m >= 9 ? $y : $y - 1;
        return [
            ($cur - 1) . '-' . $cur,
            $cur . '-' . ($cur + 1),
            ($cur + 1) . '-' . ($cur + 2),
        ];
    }
}
