<?php

declare(strict_types=1);

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\Events\BulletinAppreciationUpdated;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Policies\BulletinPolicy;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\DTO\BulletinData;
use Core\Controller;
use Core\EventDispatcher;
use Core\QrCode\QrEncoder;
use Core\Session;

/**
 * Bulletin — impression fidèle au bulletin papier (CSP La Persévérance) et
 * vérification publique par QR code. Construit sur le moteur du module
 * Académique V2 (BulletinGenerator/AcademicCalculationService/RankingEngine),
 * natif semestre. Routes déclarées dans config/routes.php (URLs historiques
 * conservées) plutôt que dans app/Modules/Academique/routes.php, mais le
 * module 'academique' est désormais activé (cf. ACADEMIQUE_MODULE_FREEZE.md).
 */
class BulletinController extends Controller
{
    private BulletinRepository $repo;
    private BulletinPolicy $policy;

    public function __construct()
    {
        parent::__construct();

        $this->repo   = new BulletinRepository();
        $this->policy = new BulletinPolicy();
    }

    /**
     * Version imprimable / exportable PDF (impression navigateur) du
     * bulletin d'un élève pour une période — réutilise un bulletin déjà
     * généré (persisté) s'il existe, sinon en génère un nouveau à la volée.
     */
    public function imprimer(string $eleveId, string $periodeId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $eleveId   = (int)$eleveId;
        $periodeId = (int)$periodeId;

        if (!$this->policy->canViewForParent($user, $eleveId)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
            return;
        }

        $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        if ($row) {
            $bulletin = BulletinData::fromArray(json_decode($row['data_json'], true) ?? []);
        } else {
            if (!$this->policy->canGenerate($user)) {
                http_response_code(403);
                $this->render('errors/403', [
                    'title' => 'Aucun bulletin généré pour cet élève sur cette période',
                ], 'none');
                return;
            }
            $generator = BulletinEngineFactory::make((int)$user['etablissement_id']);
            $bulletin  = $generator->genererBulletin($eleveId, $periodeId, (int)$user['id']);
        }

        $periode = (new PeriodeScolaireModel())->findById($periodeId) ?: null;

        // `appreciation_directeur` vit dans une colonne dédiée de bulletins_v2,
        // distincte du snapshot `data_json` (toujours généré à null par
        // BulletinGenerator) — c'est donc la colonne, quand une ligne persistée
        // existe, qui reflète une éventuelle saisie ultérieure de la direction.
        $appreciationDirecteur = $row['appreciation_directeur'] ?? $bulletin->appreciationDirecteur;

        $this->render(
            'Academique::bulletins/imprimer',
            $this->buildViewModel($bulletin, $periode, $appreciationDirecteur, $this->policy->canAddDirecteurAppreciation($user)),
            'none'
        );
    }

    /**
     * Formulaire de saisie/modification de l'appréciation du chef
     * d'établissement — réservé à `academique.bulletin.admin`
     * (BulletinPolicy::canAddDirecteurAppreciation()).
     */
    public function appreciationForm(string $eleveId, string $periodeId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $eleveId   = (int)$eleveId;
        $periodeId = (int)$periodeId;

        if (!$this->policy->canAddDirecteurAppreciation($user)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
            return;
        }

        $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        if (!$row) {
            // Même comportement que imprimer() : génère le bulletin à la volée
            // s'il n'existe pas encore, pour ne pas bloquer la direction.
            $generator = BulletinEngineFactory::make((int)$user['etablissement_id']);
            $bulletin  = $generator->genererBulletin($eleveId, $periodeId, (int)$user['id']);
            $row       = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        } else {
            $bulletin = BulletinData::fromArray(json_decode($row['data_json'], true) ?? []);
        }

        $this->render('Academique::bulletins/appreciation-directeur', [
            'title'                 => 'Appréciation du chef d\'établissement',
            'eleveId'               => $eleveId,
            'periodeId'             => $periodeId,
            'eleveNomComplet'       => trim($bulletin->eleveNom . ' ' . $bulletin->elevePrenom),
            'classeNom'             => $bulletin->classeNom,
            'periodeNom'            => $bulletin->periodeNom,
            'appreciationDirecteur' => $row['appreciation_directeur'] ?? $bulletin->appreciationDirecteur,
        ]);
    }

    /** Enregistre l'appréciation soumise par le formulaire ci-dessus. */
    public function updateAppreciation(string $eleveId, string $periodeId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        $eleveId   = (int)$eleveId;
        $periodeId = (int)$periodeId;

        if (!$this->policy->canAddDirecteurAppreciation($user)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
            return;
        }

        $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        if (!$row) {
            Session::flash('error', "Générez d'abord le bulletin avant d'y ajouter une appréciation.");
            $this->redirect(BASE_URL . "/v2/academique/bulletins/{$eleveId}/{$periodeId}/imprimer");
            return;
        }

        $texte = trim($this->request->post('appreciation_directeur', ''));
        $this->repo->updateAppreciationDirecteur($row['verification_token'], $texte !== '' ? $texte : null);

        EventDispatcher::dispatch(new BulletinAppreciationUpdated(
            eleveId          : $eleveId,
            periodeId        : $periodeId,
            verificationToken: $row['verification_token'],
            updatedById      : (int)$user['id'],
        ));

        Session::flash('success', 'Appréciation enregistrée.');
        $this->redirect(BASE_URL . "/v2/academique/bulletins/{$eleveId}/{$periodeId}/appreciation-directeur");
    }

    /**
     * Page publique de vérification d'authenticité (accessible via le QR
     * code imprimé sur le bulletin) — aucune authentification requise.
     */
    public function verifier(string $token): void
    {
        $row = $this->repo->findByToken($token);
        if (!$row) {
            http_response_code(404);
            $this->render('Academique::bulletins/verification', [
                'trouve' => false,
            ], 'none');
            return;
        }

        $bulletin = BulletinData::fromArray(json_decode($row['data_json'], true) ?? []);

        $this->render('Academique::bulletins/verification', [
            'trouve'   => true,
            'bulletin' => $bulletin,
        ], 'none');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Construit le view-model complet consommé par la vue imprimer.php + partials. */
    private function buildViewModel(
        BulletinData $b,
        ?\stdClass   $periode,
        ?string      $appreciationDirecteur = null,
        bool         $peutModifierAppreciation = false,
    ): array {
        $qr = new QrEncoder();

        return [
            'eleveId'                     => $b->eleveId,
            'periodeId'                   => $b->periodeId,
            'peutModifierAppreciation'    => $peutModifierAppreciation,
            'etablissement' => [
                // Figés au moment de la génération du bulletin — un document déjà
                // émis ne doit pas changer de coordonnées si l'établissement les
                // met à jour ensuite (voir BulletinData::$etablissementTelephone).
                'nom'       => $b->etablissementNom,
                'adresse'   => $b->etablissementAdresse,
                'logo'      => $b->etablissementLogo,
                'telephone' => $b->etablissementTelephone,
                'email'     => $b->etablissementEmail,
            ],
            'titreBulletin'       => $this->titreBulletin($b, $periode),
            'estBilanAnnuel'      => $this->estBilanAnnuel($periode),
            'eleveNomComplet'     => trim($b->eleveNom . ' ' . $b->elevePrenom),
            'classeNom'           => $b->classeNom,
            'effectif'            => $b->effectifClasse ?: $b->nbEleves,
            'anneeScolaire'       => $b->anneeScolaire,
            'dateEdition'         => date('d/m/Y'),
            'lignesMatieres'      => $b->lignesMatieres,
            'moyenneGenerale'     => $b->moyennePeriode,
            'rangEleve'           => $b->rang,
            'nbEleves'            => $b->nbEleves,
            'resultatsAnnuels'    => $b->resultatsAnnuels,
            'statistiquesClasse'  => $b->statistiquesClasse,
            'moyenneLitteraire'   => $b->moyenneLitteraire,
            'moyenneScientifique' => $b->moyenneScientifique,
            'moyenneAutre'        => $b->moyenneAutre,
            'absences'            => $b->absences,
            'appreciationDirecteur' => $appreciationDirecteur ?? $b->appreciationDirecteur,
            'qrSvg'               => $qr->generateSvg($b->qrCodeUrl),
            'verificationToken'   => $b->verificationToken,
        ];
    }

    /** Bulletin de fin d'année (2ème semestre) : seul cas où les résultats annuels (moyenne/rang/décision) ont un sens. */
    private function estBilanAnnuel(?\stdClass $periode): bool
    {
        return $periode !== null
            && $periode->type_periode === 'semestre'
            && (int)$periode->numero === 2;
    }

    private function titreBulletin(BulletinData $b, ?\stdClass $periode): string
    {
        if ($periode !== null && $periode->type_periode === 'semestre') {
            return ((int)$periode->numero === 1)
                ? 'Bulletin du premier semestre'
                : 'Bulletin du deuxieme semestre';
        }
        return 'Bulletin — ' . $b->periodeNom;
    }
}
