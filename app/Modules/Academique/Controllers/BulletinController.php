<?php

declare(strict_types=1);

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\Events\BulletinAppreciationUpdated;
use App\Modules\Academique\Events\BulletinRegenerated;
use App\Modules\Academique\Services\ConduiteIncompleteException;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Policies\BulletinPolicy;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\Services\BulletinPublicationGate;
use App\Modules\Academique\Services\BulletinTokenException;
use App\Modules\Academique\Services\BulletinTokenService;
use App\Modules\Academique\Services\BulletinVerificationUrl;
use App\Modules\Academique\DTO\BulletinData;
use App\Services\AuditService;
use App\Shared\Http\ClientIp;
use App\Shared\Http\PublicThrottle;
use Core\Controller;
use Core\Logger;
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

        if (!$this->policy->canViewForParent($user, $eleveId, $periodeId)) {
            \Core\Logger::security('BULLETIN_ACCESS_DENIED', "imprimer eleve={$eleveId} periode={$periodeId} user=" . (int)($user['id'] ?? 0) . ' role=' . ($user['role'] ?? ''));
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
            return;
        }

        $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        if (!$row) {
            if (!$this->policy->canGenerate($user)) {
                http_response_code(403);
                $this->render('errors/403', [
                    'title' => 'Aucun bulletin généré pour cet élève sur cette période',
                ], 'none');
                return;
            }
            try {
                $generator = BulletinEngineFactory::make((int)$user['etablissement_id']);
                $generator->genererBulletin($eleveId, $periodeId, (int)$user['id']);
            } catch (BulletinTokenException $e) {
                // BULLETIN_TOKEN_SECRET absent ou invalide : aucune génération persistée n'est possible.
                Logger::critical('Génération de bulletin impossible : ' . $e->getMessage());
                $this->indisponible('Génération impossible : la configuration des jetons de vérification est incomplète.');
                return;
            }
            $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
            if (!$row) {
                $this->indisponible('Le bulletin n\'a pas pu être enregistré.');
                return;
            }
        }
        $bulletin = BulletinData::fromArray(json_decode($row['data_json'], true) ?? []);

        // Un document est OFFICIEL (QR de vérification, pas de filigrane) uniquement s'il est publié/archivé ET
        // porte un jeton valide. Tout le reste — brouillon, aperçu — est imprimable avec le filigrane
        // « BROUILLON » et SANS QR. Un bulletin publié dont le jeton est invalide n'est jamais imprimé comme
        // officiel : l'impression est refusée plutôt que dégradée silencieusement.
        $statut   = (string)($row['statut'] ?? 'brouillon');
        $officiel = in_array($statut, ['publie', 'archive'], true);
        $qrSvg    = '';
        if ($officiel) {
            try {
                $qrSvg = (new QrEncoder())->generateSvg($this->urlQrOfficiel($row));
            } catch (BulletinTokenException | \RuntimeException | \InvalidArgumentException $e) {
                Logger::critical('Impression officielle refusée (bulletin ' . (int)$row['id'] . ') : ' . $e->getMessage());
                $this->indisponible('Impression officielle impossible : jeton de vérification ou adresse du site invalide. Contactez l\'administrateur.');
                return;
            }
        }
        $peutPublier = $statut === 'brouillon'
            && (int)($row['token_version'] ?? 1) === BulletinTokenService::VERSION
            && BulletinPublicationGate::isOpen()
            && $this->policy->canPublish($user);
        // Bouton « Régénérer en vue de la publication » : brouillon + droit de génération + même établissement
        $peutRegenerer = $statut === 'brouillon'
            && $this->policy->canGenerate($user)
            && (int)($row['etablissement_id'] ?? 0) === (int)($user['etablissement_id'] ?? -1);

        $this->auditerImpression($user, $eleveId, $periodeId, $statut, $officiel);

        $periode = (new PeriodeScolaireModel())->findById($periodeId) ?: null;

        // `appreciation_directeur` vit dans une colonne dédiée de bulletins_v2,
        // distincte du snapshot `data_json` (toujours généré à null par
        // BulletinGenerator) — c'est donc la colonne, quand une ligne persistée
        // existe, qui reflète une éventuelle saisie ultérieure de la direction.
        $appreciationDirecteur = $row['appreciation_directeur'] ?? $bulletin->appreciationDirecteur;

        $this->render(
            'Academique::bulletins/imprimer',
            $this->buildViewModel(
                $bulletin, $periode, $appreciationDirecteur, $this->policy->canAddDirecteurAppreciation($user),
                $qrSvg, $officiel, $peutPublier, $peutRegenerer
            ),
            'none'
        );
    }

    /**
     * URL absolue du QR d'un bulletin officiel, construite à partir de la colonne (source unique du jeton) et de
     * APP_URL. Refuse un jeton hérité (version ≠ 2), un jeton qui ne correspond pas au bulletin, un secret absent
     * ou une URL de base inutilisable (relative, locale en production, non HTTPS en production).
     *
     * @throws BulletinTokenException|\RuntimeException|\InvalidArgumentException
     */
    protected function urlQrOfficiel(array $row): string
    {
        if ((int)($row['token_version'] ?? 1) !== BulletinTokenService::VERSION) {
            throw new \RuntimeException('jeton hérité (version ' . (int)($row['token_version'] ?? 1) . ')');
        }
        $token = (string)$row['verification_token'];
        $svc   = BulletinTokenService::fromEnvironment();
        if (!$svc->matches($token, (int)$row['etablissement_id'], (int)$row['eleve_id'], (int)$row['periode_id'])) {
            throw new \RuntimeException('jeton ne correspondant pas au bulletin');
        }
        $app = require ROOT_PATH . '/config/app.php';
        return BulletinVerificationUrl::build($token, (string)($app['url'] ?? ''), (string)($app['env'] ?? 'production'));
    }

    /** Page d'erreur 503 explicite (aucun détail technique sensible). */
    private function indisponible(string $message): void
    {
        http_response_code(503);
        $this->render('errors/403', ['title' => $message], 'none');
    }

    /** Journal d'audit des impressions : qui, quel bulletin, quel statut — jamais le jeton. */
    private function auditerImpression(array $user, int $eleveId, int $periodeId, string $statut, bool $officiel): void
    {
        try {
            (new AuditService())->log(
                (int)($user['id'] ?? 0), 'imprimer', 'academique', 'bulletin', $eleveId, null,
                ['periode_id' => $periodeId, 'statut' => $statut, 'officiel' => $officiel]
            );
        } catch (\Throwable) {
            // L'audit ne doit jamais empêcher l'impression.
        }
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
        $this->enTetesVerification();

        // 1 — Limitation de débit (30 requêtes / minute / IP). L'IP vient de REMOTE_ADDR ; X-Forwarded-For
        //     n'est lu que si le proxy figure explicitement dans TRUSTED_PROXIES (jamais par défaut).
        $ip     = ClientIp::resolve($_SERVER, ClientIp::trustedFromEnvironment());
        $limite = $this->throttle()->hit('bulletin-verify', $ip);
        if (!$limite['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $limite['retryAfter']);
            $this->render('Academique::bulletins/verification', ['trouve' => false, 'limite' => true], 'none');
            return;
        }

        // 2 — Format strict (64 hexadécimaux minuscules) AVANT toute requête : rejette les valeurs tronquées,
        //     modifiées, en majuscules (la collation de la colonne ignore la casse) ou malformées.
        $row = BulletinTokenService::isWellFormed($token) ? $this->repo->findByToken($token) : null;

        // 3 — Jeton inconnu : peut être un ancien jeton retiré (recherche par empreinte). On le journalise
        //     (sans le jeton) pour savoir qu'un ancien QR circule, mais la réponse reste IDENTIQUE.
        if ($row === null) {
            $this->signalerAncienJeton($token);
            $this->introuvable();
            return;
        }

        // 4 — Un jeton hérité (version ≠ 2) n'est JAMAIS accepté comme jeton officiel.
        if ((int)($row['token_version'] ?? 1) !== BulletinTokenService::VERSION) {
            Logger::security('BULLETIN_LEGACY_QR_SCANNED', 'bulletin_id=' . (int)$row['id'] . ' version=' . (int)($row['token_version'] ?? 1));
            $this->introuvable();
            return;
        }

        // 5 — Seul un bulletin ÉMIS (publié ou archivé) est vérifiable : un brouillon produit la même réponse
        //     qu'un jeton inconnu (on ne révèle ni l'existence ni l'état d'un bulletin non émis).
        if (!in_array($row['statut'] ?? '', ['publie', 'archive'], true)) {
            $this->introuvable();
            return;
        }

        // 6 — Recalcul du HMAC : la ligne de base de données seule ne suffit pas à rendre un jeton valide.
        try {
            $service = BulletinTokenService::fromEnvironment();
        } catch (BulletinTokenException $e) {
            Logger::critical('Vérification de bulletin impossible : ' . $e->getMessage());   // jamais la valeur du secret
            http_response_code(503);
            $this->render('Academique::bulletins/verification', ['trouve' => false, 'indisponible' => true], 'none');
            return;
        }
        if (!$service->matches($token, (int)$row['etablissement_id'], (int)$row['eleve_id'], (int)$row['periode_id'])) {
            Logger::security('BULLETIN_TOKEN_MISMATCH', 'bulletin_id=' . (int)$row['id'] . ' fp=' . BulletinTokenService::fingerprint($token));
            $this->introuvable();
            return;
        }

        // 7 — Authentique : AUCUNE donnée de bulletin n'est transmise à la vue (résultat uniquement).
        $this->render('Academique::bulletins/verification', ['trouve' => true], 'none');
    }

    /** Réponse unique pour tout ce qui n'est pas un bulletin officiel authentique : même statut, même contenu. */
    private function introuvable(): void
    {
        http_response_code(404);
        $this->render('Academique::bulletins/verification', ['trouve' => false], 'none');
    }

    private function enTetesVerification(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');          // le jeton figure dans l'URL : ne jamais le transmettre en Referer
        header('X-Robots-Tag: noindex, nofollow');
    }

    /** Limiteur de la page publique (surchargeable par les tests). */
    protected function throttle(): PublicThrottle
    {
        return new PublicThrottle(ROOT_PATH . '/storage/cache/throttle', 30, 60);
    }

    /** Journalise l'usage d'un jeton retiré (par empreinte SHA-256) sans jamais écrire le jeton. */
    private function signalerAncienJeton(string $token): void
    {
        if (!BulletinTokenService::isWellFormed($token)) {
            return;
        }
        $retire = $this->repo->findRetiredByDigest(BulletinTokenService::retiredDigest($token));
        if ($retire !== null) {
            Logger::security('BULLETIN_LEGACY_QR_SCANNED', 'retired_id=' . (int)$retire['id'] . ' bulletin_id=' . (int)($retire['bulletin_id'] ?? 0));
        }
    }

    /**
     * Publie un bulletin (brouillon → publié). Conditions CUMULATIVES, toutes vérifiées côté serveur :
     * session, CSRF, interrupteur de publication ouvert, politique (academique.bulletin.publish|admin), bulletin
     * existant en brouillon, jeton officiel valide. Journalisation sans jeton complet (empreinte uniquement).
     */
    public function publier(string $eleveId, string $periodeId): void
    {
        $this->changerStatut('publier', (int)$eleveId, (int)$periodeId);
    }

    /** Archive un bulletin publié (publié → archivé), mêmes garanties que publier(). */
    public function archiver(string $eleveId, string $periodeId): void
    {
        $this->changerStatut('archiver', (int)$eleveId, (int)$periodeId);
    }

    /**
     * Régénère explicitement un bulletin EN BROUILLON en vue de sa publication : exige la Conduite complète de la classe,
     * applique le format d'instantané à jour et conserve le jeton. Ne publie JAMAIS. Protections : authentification, CSRF,
     * droit de génération, établissement de l'élève = établissement de l'utilisateur, statut brouillon.
     */
    public function regenerer(string $eleveId, string $periodeId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user      = $this->currentUser();
        $eleveId   = (int)$eleveId;
        $periodeId = (int)$periodeId;
        $ctx       = "action=regenerer eleve={$eleveId} periode={$periodeId} user=" . (int)($user['id'] ?? 0) . ' role=' . ($user['role'] ?? '');

        if (!$this->policy->canGenerate($user)) {
            Logger::security('BULLETIN_REGENERATION_REFUSED', "raison=droits_insuffisants {$ctx}");
            $this->refuser();
            return;
        }

        $retour = BASE_URL . "/v2/academique/bulletins/{$eleveId}/{$periodeId}/imprimer";
        $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        if (!$row) {
            Session::flash('error', 'Bulletin introuvable : générez-le d\'abord.');
            $this->redirect($retour);
        }
        if ((int)($row['etablissement_id'] ?? 0) !== (int)($user['etablissement_id'] ?? -1)) {
            Logger::security('BULLETIN_REGENERATION_REFUSED', "raison=autre_etablissement {$ctx}");
            $this->refuser();
            return;
        }
        if (($row['statut'] ?? '') !== 'brouillon') {
            Session::flash('error', 'Seul un bulletin en brouillon peut être régénéré : celui-ci est déjà publié ou archivé.');
            $this->redirect($retour);
        }

        try {
            $generator = BulletinEngineFactory::make((int)$user['etablissement_id']);
            $bulletin  = $generator->genererBulletin($eleveId, $periodeId, (int)$user['id'], pourPublication: true);
            EventDispatcher::dispatch(new BulletinRegenerated(
                eleveId: $eleveId, classeId: $bulletin->classeId, periodeId: $periodeId,
                verificationToken: $bulletin->verificationToken, regeneratedById: (int)$user['id'],
            ));
            Session::flash('success', 'Bulletin régénéré en vue de la publication — il reste en brouillon (non publié).');
        } catch (ConduiteIncompleteException $e) {
            Logger::security('BULLETIN_REGENERATION_REFUSED', 'raison=conduite_incomplete ' . $ctx);
            Session::flash('error', $e->getMessage());
        } catch (BulletinTokenException | \RuntimeException $e) {
            Logger::security('BULLETIN_REGENERATION_REFUSED', 'raison=' . substr($e->getMessage(), 0, 120) . " {$ctx}");
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($retour);
    }

    private function changerStatut(string $action, int $eleveId, int $periodeId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();
        $ctx  = "action={$action} eleve={$eleveId} periode={$periodeId} user=" . (int)($user['id'] ?? 0) . ' role=' . ($user['role'] ?? '');

        if (!BulletinPublicationGate::isOpen()) {
            Logger::security('BULLETIN_PUBLICATION_REFUSED', "raison=interrupteur_ferme {$ctx}");
            $this->refuser();
            return;
        }
        $autorise = $action === 'publier' ? $this->policy->canPublish($user) : $this->policy->canArchive($user);
        if (!$autorise) {
            Logger::security('BULLETIN_PUBLICATION_REFUSED', "raison=droits_insuffisants {$ctx}");
            $this->refuser();
            return;
        }

        $retour = BASE_URL . "/v2/academique/bulletins/{$eleveId}/{$periodeId}/imprimer";
        $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        if (!$row) {
            Session::flash('error', 'Bulletin introuvable : générez-le d\'abord.');
            $this->redirect($retour);
        }

        try {
            $generator = BulletinEngineFactory::make((int)($user['etablissement_id'] ?? 0));
            if ($action === 'publier') {
                $generator->publierBulletin((string)$row['verification_token'], (int)$user['id']);
                Session::flash('success', 'Bulletin publié : il est maintenant officiel et vérifiable par son QR.');
            } else {
                $generator->archiverBulletin((string)$row['verification_token'], (int)$user['id']);
                Session::flash('success', 'Bulletin archivé.');
            }
        } catch (BulletinTokenException | \RuntimeException $e) {
            Logger::security('BULLETIN_PUBLICATION_REFUSED', "raison=" . substr($e->getMessage(), 0, 120) . " {$ctx}");
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($retour);
    }

    private function refuser(): void
    {
        http_response_code(403);
        $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Construit le view-model complet consommé par la vue imprimer.php + partials. */
    private function buildViewModel(
        BulletinData $b,
        ?\stdClass   $periode,
        ?string      $appreciationDirecteur = null,
        bool         $peutModifierAppreciation = false,
        string       $qrSvg = '',
        bool         $estOfficiel = false,
        bool         $peutPublier = false,
        bool         $peutRegenerer = false,
    ): array {
        return [
            'estOfficiel'                 => $estOfficiel,
            'peutPublier'                 => $peutPublier,
            'peutRegenerer'               => $peutRegenerer,
            'conduiteComplete'            => $b->conduiteComplete,
            'csrfToken'                   => Session::getCsrfToken(),
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
            'qrSvg'               => $qrSvg,   // vide pour tout bulletin non officiel
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
