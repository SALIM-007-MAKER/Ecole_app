<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\EleveModel;
use App\Models\AbsenceModel;
use App\Models\NotificationModel;
use App\Models\AnnonceModel;
use App\Models\EmploiDuTempsModel;
use App\Models\CreneauModel;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;

class EspaceEleveController extends Controller
{
    private EleveModel                $eleveModel;
    private NotificationModel         $notifModel;
    private AnnonceModel              $annonceModel;
    private PeriodeScolaireRepository $periodeRepo;
    private BulletinRepository        $bulletinRepo;

    public function __construct()
    {
        parent::__construct();
        $this->eleveModel   = new EleveModel();
        $this->notifModel   = new NotificationModel();
        $this->annonceModel = new AnnonceModel();

        $this->periodeRepo  = new PeriodeScolaireRepository();
        $this->bulletinRepo = new BulletinRepository();
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $user  = $this->requireEleve();
        $eleve = $this->getEleve($user);
        if (!$eleve) { $this->renderCompteNonLie(); return; }

        $periodes        = $this->periodeRepo->findForSelect();
        $dernierePeriode = $this->periodeRepo->findActive() ?? (end($periodes) ?: null);
        reset($periodes);

        $moyennes      = [];
        $moyGen        = null;
        $absencesRecentes = [];

        if ($eleve && $dernierePeriode) {
            $bulletin = $this->previewBulletinSafe((int)$eleve->id, (int)$dernierePeriode->id);
            if ($bulletin !== null) {
                $moyennes = $bulletin->lignesMatieres;
                $moyGen   = [
                    'moyenne'  => $bulletin->moyennePeriode,
                    'rang'     => $bulletin->rang,
                    'mention'  => $bulletin->mentionLabel,
                    'total'    => $bulletin->nbEleves,
                ];
            }
        }

        if ($eleve) {
            $absModel = new AbsenceModel();
            $absencesRecentes = $absModel->query(
                "SELECT a.*, m.nom AS matiere_nom
                 FROM `absences` a
                 LEFT JOIN `matieres` m ON m.id = a.matiere_id
                 WHERE a.eleve_id = ?
                 ORDER BY a.date_absence DESC LIMIT 5",
                [(int)$eleve->id]
            );
        }

        $annonces      = $this->annonceModel->findPubliees('eleves');
        $notifications = $this->notifModel->findUnreadForUser((int)$user['id'], 5);

        // EDT du jour
        $edtAujourdhui = [];
        if ($eleve && $eleve->classe_id) {
            $edtAujourdhui = $this->getEdtAujourdHui((int)$eleve->classe_id);
        }

        $this->render('eleve/dashboard', [
            'title'            => 'Mon espace élève',
            'eleve'            => $eleve,
            'periodes'         => $periodes,
            'dernierePeriode'  => $dernierePeriode,
            'moyennes'         => $moyennes,
            'moyGen'           => $moyGen,
            'absencesRecentes' => $absencesRecentes,
            'annonces'         => array_slice($annonces, 0, 5),
            'notifications'    => $notifications,
            'edtAujourdhui'    => $edtAujourdhui,
        ]);
    }

    // ─── Notes ────────────────────────────────────────────────────────────────

    public function notes(): void
    {
        $user     = $this->requireEleve();
        $eleve    = $this->getEleve($user);
        if (!$eleve) { $this->renderCompteNonLie(); return; }
        $periodes = $this->periodeRepo->findForSelect();

        $periodeId = (int)$this->request->get('periode_id', $periodes ? $periodes[0]->id : 0);
        $bulletin  = null;

        if ($eleve && $periodeId) {
            $bulletin = $this->previewBulletinSafe((int)$eleve->id, $periodeId);
        }

        $this->render('eleve/notes', [
            'title'     => 'Mes notes',
            'eleve'     => $eleve,
            'periodes'  => $periodes,
            'periodeId' => $periodeId,
            'bulletin'  => $bulletin,
        ]);
    }

    // ─── Bulletin ─────────────────────────────────────────────────────────────

    public function bulletin(): void
    {
        $user     = $this->requireEleve();
        $eleve    = $this->getEleve($user);
        if (!$eleve) { $this->renderCompteNonLie(); return; }
        $periodes = $this->periodeRepo->findForSelect();

        $periodeId = (int)$this->request->get('periode_id', $periodes ? $periodes[0]->id : 0);
        $bulletin  = null;

        if ($eleve && $periodeId) {
            $bulletin = $this->previewBulletinSafe((int)$eleve->id, $periodeId);
        }

        $this->render('eleve/bulletin', [
            'title'     => 'Mon bulletin',
            'eleve'     => $eleve,
            'periodes'  => $periodes,
            'periodeId' => $periodeId,
            'bulletin'  => $bulletin,
        ]);
    }

    // ─── Emploi du temps ──────────────────────────────────────────────────────

    public function emploiDuTemps(): void
    {
        $user  = $this->requireEleve();
        $eleve = $this->getEleve($user);
        if (!$eleve) { $this->renderCompteNonLie(); return; }

        $annee    = $this->currentAnnee();
        $creneaux = (new CreneauModel())->findAllActifs();
        $grid     = [];
        $jours    = EmploiDuTempsModel::JOURS;

        if ($eleve && $eleve->classe_id) {
            $edtModel = new EmploiDuTempsModel();
            $grid = $edtModel->getWeekGrid(
                ['classe_id' => $eleve->classe_id],
                $annee
            );
        }

        $this->render('eleve/emploi_du_temps', [
            'title'    => 'Mon emploi du temps',
            'eleve'    => $eleve,
            'creneaux' => $creneaux,
            'grid'     => $grid,
            'jours'    => $jours,
            'annee'    => $annee,
        ]);
    }

    // ─── Profil ───────────────────────────────────────────────────────────────

    public function profil(): void
    {
        $user  = $this->requireEleve();
        $eleve = $this->getEleve($user);
        if (!$eleve) { $this->renderCompteNonLie(); return; }

        $this->render('eleve/profil', [
            'title'  => 'Mon profil',
            'eleve'  => $eleve,
            'user'   => $user,
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    private function requireEleve(): array
    {
        $this->requireAuth();
        $user = $this->currentUser();
        if ($user['role'] !== 'eleve') {
            $this->redirect(BASE_URL . '/dashboard');
            exit;
        }
        return $user;
    }

    private function getEleve(array $user): ?object
    {
        // M007 — lien fiable eleves.user_id → users(id) (remplace le
        // rapprochement par email, non fiable : eleves.email n'est ni
        // UNIQUE ni synchronisé avec users.email).
        $eleve = $this->eleveModel->findByUserId((int)($user['id'] ?? 0));
        return $eleve ?: null;
    }

    /** Compte 'eleve' authentifié mais sans dossier `eleves` lié (eleves.user_id). */
    private function renderCompteNonLie(): void
    {
        $this->render('eleve/compte_non_lie', ['title' => 'Compte non lié']);
    }

    /** Génère un aperçu de bulletin sans persister ; null si aucune donnée exploitable. */
    private function previewBulletinSafe(int $eleveId, int $periodeId): ?BulletinData
    {
        try {
            $etablissementId = (int)($this->currentUser()['etablissement_id'] ?? 1);
            $generator = BulletinEngineFactory::make($etablissementId);
            return $generator->previewBulletin($eleveId, $periodeId);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function getEdtAujourdHui(int $classeId): array
    {
        $annee = $this->currentAnnee();
        $dow   = (int)date('N'); // 1=Lun … 7=Dim
        if ($dow > 6) return [];

        return $this->eleveModel->query(
            "SELECT edt.*,
                    m.nom AS matiere_nom,
                    CONCAT(p.prenom, ' ', p.nom) AS prof_fullname,
                    s.nom AS salle_nom,
                    cr.heure_debut, cr.heure_fin, cr.nom AS creneau_nom,
                    edt.couleur
             FROM `emplois_du_temps` edt
             JOIN `creneaux` cr ON cr.id = edt.creneau_id
             JOIN `matieres` m  ON m.id  = edt.matiere_id
             JOIN `professeurs` p ON p.id = edt.professeur_id
             LEFT JOIN `salles` s ON s.id = edt.salle_id
             WHERE edt.classe_id = ? AND edt.jour_semaine = ?
             AND edt.annee_scolaire = ? AND edt.actif = 1
             ORDER BY cr.heure_debut",
            [$classeId, $dow, $annee]
        );
    }

}

