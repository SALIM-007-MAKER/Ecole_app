<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\EleveModel;
use App\Models\NotificationModel;
use App\Models\AnnonceModel;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\VieScolaire\EmploisDuTemps\Repositories\TimetableRepository;

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
            // vs_absences n'a pas de colonne matiere_id (une absence est par
            // journée, pas par matière) — pas de jointure possible.
            $stmt = \Core\Database::getInstance()->getConnection()->prepare(
                "SELECT date_absence, type, NULL AS matiere_nom
                 FROM vs_absences
                 WHERE eleve_id = :eleve_id AND deleted_at IS NULL AND type = 'absence'
                 ORDER BY date_absence DESC LIMIT 5"
            );
            $stmt->execute([':eleve_id' => $eleve->id]);
            $absencesRecentes = $stmt->fetchAll(\PDO::FETCH_OBJ);
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
        $jours    = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $repo     = new TimetableRepository();

        $creneaux = array_map(function (array $p): object {
            $p['is_pause'] = (bool)preg_match('/pause|récréation|recreation/i', $p['libelle']);
            $p['heure_debut'] = substr($p['heure_debut'], 0, 5);
            $p['heure_fin']   = substr($p['heure_fin'], 0, 5);
            return (object)$p;
        }, $repo->findAllPlages());

        $seances = [];
        if ($eleve && $eleve->classe_id) {
            $edt = $repo->findEdtByClasse((int)$eleve->classe_id, $annee, null, 'standard');
            if ($edt !== null && $edt['statut'] === 'publie') {
                $seances = array_map(function (array $s): object {
                    $s['jour_semaine']   = $s['jour'];
                    $s['creneau_id']     = $s['plage_id'];
                    $s['couleur']        = $s['matiere_couleur'];
                    $s['enseignant_nom'] = trim($s['enseignant_prenom'] . ' ' . $s['enseignant_nom']);
                    return (object)$s;
                }, $repo->findCreneauxByEdt((int)$edt['id']));
            }
        }

        $this->render('eleve/emploi_du_temps', [
            'title'    => 'Mon emploi du temps',
            'eleve'    => $eleve,
            'creneaux' => $creneaux,
            'jours'    => $jours,
            'seances'  => $seances,
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

        $repo = new TimetableRepository();
        $edt  = $repo->findEdtByClasse($classeId, $annee, null, 'standard');
        if ($edt === null || $edt['statut'] !== 'publie') {
            return [];
        }

        $creneaux = array_filter($repo->findCreneauxByEdt((int)$edt['id']), fn(array $c) => (int)$c['jour'] === $dow);

        return array_map(function (array $c): object {
            $c['matiere_nom']   = $c['matiere_nom'];
            $c['prof_fullname'] = trim($c['enseignant_prenom'] . ' ' . $c['enseignant_nom']);
            $c['salle_nom']     = $c['salle_nom'] ?? null;
            $c['heure_debut']   = substr($c['plage_debut'], 0, 5);
            $c['heure_fin']     = substr($c['plage_fin'], 0, 5);
            $c['creneau_nom']   = $c['plage_libelle'];
            $c['couleur']       = $c['matiere_couleur'];
            return (object)$c;
        }, array_values($creneaux));
    }

}

