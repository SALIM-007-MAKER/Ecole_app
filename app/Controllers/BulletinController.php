<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\DTO\RankingResultDTO;
use App\Modules\Academique\Repositories\EvaluationRepository;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\Services\RankingEngine;

/**
 * Bulletins — entièrement migré vers le moteur Académique V2. Les quatre
 * actions (index/classe/classement/eleve) délèguent tout calcul à
 * RankingEngine/BulletinGenerator (via BulletinEngineFactory) ; aucune ne
 * touche plus les tables V1 `notes`/`controles`/`periodes`.
 *
 * NoteModel et PeriodeModel (V1) ont été retirés des dépendances de ce
 * contrôleur — plus aucune action ici n'en a besoin. Ces classes restent
 * utilisées ailleurs (App\Controllers\NoteController V1, non migré) et ne
 * sont donc pas supprimées du projet.
 */
class BulletinController extends Controller
{
    private ClasseModel          $classeModel;
    private EleveModel           $eleveModel;
    private EvaluationRepository $evalRepo;
    private RankingRepository    $rankingRepo;
    private RankingEngine        $rankingEngine;

    public function __construct()
    {
        parent::__construct();
        $this->classeModel = new ClasseModel();
        $this->eleveModel  = new EleveModel();
        $this->evalRepo    = new EvaluationRepository();
        $this->rankingRepo = new RankingRepository();

        // Même moteur, même configuration (note_passage/seuil_rattrapage par
        // établissement) que celui qui génère les bulletins imprimés — voir
        // BulletinGenerator::getRankingEngine(). Un "rang" affiché ici est
        // garanti identique à celui du bulletin officiel.
        $etablissementId     = (int)($this->currentUser()['etablissement_id'] ?? 1);
        $this->rankingEngine = BulletinEngineFactory::make($etablissementId)->getRankingEngine();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SÉLECTION CLASSE / PÉRIODE
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('bulletins.view');

        $this->render('bulletins/index', [
            'title'    => 'Bulletins de notes',
            'classes'  => $this->classeModel->findAll('niveau'),
            'periodes' => $this->evalRepo->listPeriodesForSelect(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RÉSULTATS D'UNE CLASSE — RankingEngine::classementClasse()/classementMatiere()
    // ═══════════════════════════════════════════════════════════════════════════

    public function classe(): void
    {
        $this->requirePermission('bulletins.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);

        if (!$classeId || !$periodeId) {
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $classe  = $this->classeModel->findById($classeId);
        $periode = $this->trouverPeriode($periodeId);

        $matieres   = [];
        $grille     = [];
        $eleveInfos = [];

        if ($classe && $periode) {
            $classement = $this->rankingEngine->classementClasse($classeId, $periodeId);
            $eleveInfos = $this->buildEleveInfos($classement);

            foreach ($this->matieresDeLaClasse($classeId, $periodeId) as $matiereId => $data) {
                $matieres[] = (object)[
                    'id'          => $matiereId,
                    'nom'         => $data['nom'],
                    'coefficient' => $data['coefficient'],
                ];

                $classementMatiere = $this->rankingEngine->classementMatiere($matiereId, $periodeId, $classeId);
                foreach ($classementMatiere->rankings as $entry) {
                    $moy = $entry['moyenne'];
                    $grille[(int)$entry['eleve_id']][$matiereId] = $moy->isEmpty() ? null : $moy->getValue();
                }
            }
        }

        $this->render('bulletins/classe', [
            'title'      => 'Résultats — ' . ($classe ? $classe->niveau . ' ' . $classe->nom : '') . ' — ' . ($periode->nom ?? ''),
            'classe'     => $classe,
            'periode'    => $periode,
            'matieres'   => $matieres,
            'grille'     => $grille,
            'eleveInfos' => $eleveInfos,
            'classes'    => $this->classeModel->findAll('niveau'),
            'periodes'   => $this->evalRepo->listPeriodesForSelect(),
            'filters'    => compact('classeId', 'periodeId'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CLASSEMENT — RankingEngine::classementClasse()
    // ═══════════════════════════════════════════════════════════════════════════

    public function classement(): void
    {
        $this->requirePermission('bulletins.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);

        if (!$classeId || !$periodeId) {
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $classe     = $this->classeModel->findById($classeId);
        $periode    = $this->trouverPeriode($periodeId);
        $classement = [];

        if ($classe && $periode) {
            $result     = $this->rankingEngine->classementClasse($classeId, $periodeId);
            $classement = $this->buildClassementLegacyShape($result, $classeId);
        }

        $this->render('bulletins/classement', [
            'title'      => 'Classement — ' . ($classe ? $classe->niveau . ' ' . $classe->nom : ''),
            'classe'     => $classe,
            'periode'    => $periode,
            'classement' => $classement,
            'classes'    => $this->classeModel->findAll('niveau'),
            'periodes'   => $this->evalRepo->listPeriodesForSelect(),
            'filters'    => compact('classeId', 'periodeId'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BULLETIN INDIVIDUEL — BulletinEngineFactory / BulletinGenerator
    //
    // `periode_id` (GET) désigne un id `periodes_scolaires` (V2). Un
    // periode_id absent ou invalide retombe silencieusement sur le semestre
    // actif de l'établissement plutôt que d'échouer.
    // ═══════════════════════════════════════════════════════════════════════════

    public function eleve(string $id): void
    {
        $this->requirePermission('bulletins.view');

        $eleveId = (int)$id;
        $eleve   = $this->eleveModel->findById($eleveId);
        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $classeId = (int)$this->request->get('classe_id', 0) ?: (int)$eleve->classe_id;
        $classe   = $this->classeModel->findById($classeId);
        if (!$classe) {
            Session::flash('error', 'Classe introuvable.');
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $periodeRepo = new PeriodeScolaireRepository();
        $periodes    = $periodeRepo->findForSelect();

        $requestedPeriodeId = (int)$this->request->get('periode_id', 0);
        $periode = null;
        foreach ($periodes as $p) {
            if ((int)$p->id === $requestedPeriodeId) { $periode = $p; break; }
        }
        if (!$periode) {
            $periode = $periodeRepo->findActive() ?? ($periodes[0] ?? null);
        }

        if (!$periode) {
            Session::flash('error', "Aucune période scolaire n'est configurée pour l'établissement.");
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $periodeId = (int)$periode->id;
        $semestresDisponibles = $periodeRepo->findForSelect($periode->annee_scolaire);

        $bulletin = $this->previewBulletinSafe($eleveId, $periodeId);

        $this->render('bulletins/eleve', [
            'title'                => 'Bulletin — ' . $eleve->prenom . ' ' . $eleve->nom,
            'eleve'                => $eleve,
            'classe'               => $classe,
            'periode'              => $periode,
            'bulletin'             => $bulletin,
            'semestresDisponibles' => $semestresDisponibles,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Aides privées
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Aperçu de bulletin (lecture seule, non persisté) — délègue tout le
     * calcul à BulletinEngineFactory::make()->previewBulletin(). Voir
     * EspaceEleveController::previewBulletinSafe() / ParentController::
     * previewBulletinSafe() (même garantie : aucun recalcul manuel, absorbe
     * la RuntimeException d'un élève/période sans notes publiées).
     */
    private function previewBulletinSafe(int $eleveId, int $periodeId): ?BulletinData
    {
        try {
            $etablissementId = (int)($this->currentUser()['etablissement_id'] ?? 1);
            return BulletinEngineFactory::make($etablissementId)->previewBulletin($eleveId, $periodeId);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** Résout un periode_id (GET) en objet periodes_scolaires, ou null. */
    private function trouverPeriode(int $periodeId): ?object
    {
        foreach ($this->evalRepo->listPeriodesForSelect() as $p) {
            if ((int)$p->id === $periodeId) return $p;
        }
        return null;
    }

    /**
     * Matières distinctes ayant au moins une évaluation publiée/verrouillée
     * pour cette classe et cette période — mêmes lignes brutes que
     * RankingEngine::classementClasse() interroge en interne
     * (RankingRepository::notesParClasseEtPeriode(), notes_v2/evaluations
     * uniquement) ; ne fait qu'extraire (matiere_id, nom, coefficient) pour
     * savoir sur quelles matières boucler classementMatiere() — même
     * technique que Academique\Controllers\ResultatsController::moyennes().
     *
     * @return array matiereId => {nom: string, coefficient: float}
     */
    private function matieresDeLaClasse(int $classeId, int $periodeId): array
    {
        $rows     = $this->rankingRepo->notesParClasseEtPeriode($classeId, $periodeId);
        $matieres = [];
        foreach ($rows as $row) {
            $mid = (int)$row->matiere_id;
            if (!isset($matieres[$mid])) {
                $matieres[$mid] = ['nom' => $row->matiere_nom, 'coefficient' => (float)$row->coeff_matiere];
            }
        }
        return $matieres;
    }

    /**
     * Transpose RankingResultDTO->rankings (eleve_id, moyenne: AverageValue,
     * mention_label, ...) vers le format attendu par bulletins/classe.php
     * (indexé par eleve_id, clés 'moyenne_generale'/'mention' en valeurs
     * simples) — pure mise en forme, aucun recalcul.
     *
     * @return array eleveId => {nom, prenom, moyenne_generale: ?float, rang: ?int, mention: ?string}
     */
    private function buildEleveInfos(RankingResultDTO $result): array
    {
        $infos = [];
        foreach ($result->rankings as $entry) {
            $moy = $entry['moyenne'];
            $infos[(int)$entry['eleve_id']] = [
                'nom'              => $entry['nom'],
                'prenom'           => $entry['prenom'],
                'moyenne_generale' => $moy->isEmpty() ? null : $moy->getValue(),
                'rang'             => $entry['rang'],
                'mention'          => $entry['mention_label'],
            ];
        }
        return $infos;
    }

    /**
     * Transpose RankingResultDTO->rankings vers le tableau plat d'objets
     * {id, nom, prenom, matricule, photo, moyenne_generale, rang, mention}
     * attendu par bulletins/classement.php (même forme que produisait
     * autrefois NoteModel::getClassementClasse()). La photo n'est pas
     * portée par RankingEngine (moteur de calcul, pas de données de profil)
     * — récupérée en un seul aller-retour sur `eleves` (table partagée,
     * pas une table V1 du moteur notes).
     */
    private function buildClassementLegacyShape(RankingResultDTO $result, int $classeId): array
    {
        $photos = [];
        foreach ($this->eleveModel->findByClasse($classeId) as $e) {
            $photos[(int)$e->id] = $e->photo ?? null;
        }

        $classement = [];
        foreach ($result->rankings as $entry) {
            $eid = (int)$entry['eleve_id'];
            $moy = $entry['moyenne'];
            $classement[] = (object)[
                'id'               => $eid,
                'nom'              => $entry['nom'],
                'prenom'           => $entry['prenom'],
                'matricule'        => $entry['matricule'],
                'photo'            => $photos[$eid] ?? null,
                'moyenne_generale' => $moy->isEmpty() ? null : $moy->getValue(),
                'rang'             => $entry['rang'],
                'mention'          => $entry['mention_label'],
            ];
        }
        return $classement;
    }
}
