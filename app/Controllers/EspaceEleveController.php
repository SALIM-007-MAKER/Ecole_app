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
use App\Models\EmploiDuTempsModel;
use App\Models\CreneauModel;

class EspaceEleveController extends Controller
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
        $user  = $this->requireEleve();
        $eleve = $this->getEleve($user);

        $periodes       = $this->periodeModel->findAll('id');
        $dernierePeriode = end($periodes) ?: null;
        reset($periodes);

        $moyennes      = [];
        $moyGen        = null;
        $absencesRecentes = [];

        if ($eleve && $dernierePeriode) {
            $data = $this->noteModel->getBulletinData(
                (int)$eleve->id,
                (int)$eleve->classe_id,
                (int)$dernierePeriode->id
            );
            $moyennes = $data['matieres'];
            $moyGen   = [
                'moyenne'  => $data['moyenne_generale'],
                'rang'     => $data['rang'],
                'mention'  => $data['mention'],
                'total'    => $data['total_eleves'],
            ];
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
        $periodes = $this->periodeModel->findAll('id');

        $periodeId = (int)$this->request->get('periode_id', $periodes ? $periodes[0]->id : 0);
        $periode   = $periodeId ? $this->periodeModel->findById($periodeId) : null;
        $data      = null;

        if ($eleve && $periodeId) {
            $data = $this->noteModel->getBulletinData(
                (int)$eleve->id,
                (int)$eleve->classe_id,
                $periodeId
            );
        }

        $this->render('eleve/notes', [
            'title'     => 'Mes notes',
            'eleve'     => $eleve,
            'periodes'  => $periodes,
            'periode'   => $periode,
            'periodeId' => $periodeId,
            'data'      => $data,
        ]);
    }

    // ─── Bulletin ─────────────────────────────────────────────────────────────

    public function bulletin(): void
    {
        $user     = $this->requireEleve();
        $eleve    = $this->getEleve($user);
        $periodes = $this->periodeModel->findAll('id');

        $periodeId = (int)$this->request->get('periode_id', $periodes ? $periodes[0]->id : 0);
        $periode   = $periodeId ? $this->periodeModel->findById($periodeId) : null;
        $data      = null;

        if ($eleve && $periodeId) {
            $data = $this->noteModel->getBulletinData(
                (int)$eleve->id,
                (int)$eleve->classe_id,
                $periodeId
            );
        }

        $this->render('eleve/bulletin', [
            'title'     => 'Mon bulletin',
            'eleve'     => $eleve,
            'periodes'  => $periodes,
            'periode'   => $periode,
            'periodeId' => $periodeId,
            'data'      => $data,
        ]);
    }

    // ─── Emploi du temps ──────────────────────────────────────────────────────

    public function emploiDuTemps(): void
    {
        $user  = $this->requireEleve();
        $eleve = $this->getEleve($user);

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
        $eleve = $this->eleveModel->findByEmail($user['email'] ?? '');
        return $eleve ?: null;
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

