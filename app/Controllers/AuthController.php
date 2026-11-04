<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use Core\Tenant\BrandingService;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantMembershipService;
use App\Models\UserModel;
use App\Services\GlobalAuthService;

class AuthController extends Controller
{
    private UserModel $userModel;
    private TenantMembershipService $membership;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->membership = TenantMembershipService::make();
    }

    /** Longueur minimale de mot de passe configurée pour l'établissement courant (voir /parametres/securite). */
    private function passwordMinLength(): int
    {
        $etabId = BrandingService::forCurrentRequest()->etablissementId;
        return (int)SettingsService::make()->get($etabId, 'securite', 'password_min_length', 8);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CONNEXION / DÉCONNEXION
    // ═══════════════════════════════════════════════════════════════════════════

    public function showLogin(): void
    {
        if (Session::isLogged()) {
            $this->redirect(BASE_URL . '/dashboard');
        }

        $this->render('auth/login', [
            'title'  => 'Connexion',
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ], 'auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();

        $email    = trim($this->request->sanitize('email'));
        $password = $this->request->post('password', '');

        $errors = $this->validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:6']
        );

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', ['email' => $email]);
            $this->redirect(BASE_URL . '/login');
        }

        // Anti-force-brute : contrôle AVANT toute vérification du mot de passe.
        $throttle = new \App\Shared\Http\LoginThrottle(getenv('AUTH_THROTTLE_DIR') ?: ROOT_PATH . '/storage/cache/throttle');
        $ip       = $this->request->ip();
        $cles     = [
            'ec:' . strtolower($email) . '|' . $ip => 5,   // ce compte depuis cette adresse
            'ip:' . $ip                            => 30,  // cette adresse, tous comptes confondus
            'e:' . strtolower($email)              => 20,  // ce compte, attaque répartie sur plusieurs adresses
        ];
        $attente = 0;
        foreach ($cles as $cle => $limite) {
            $attente = max($attente, $throttle->retryAfter($cle, $limite));
        }
        if ($attente > 0) {
            \Core\Logger::security('LOGIN_THROTTLED', "Connexion bloquée (trop d'échecs) pour : {$email} depuis {$ip}");
            $minutes = (int)ceil($attente / 60);
            Session::flash('errors', ['auth' => ["Trop de tentatives de connexion. Réessayez dans {$minutes} minute(s)."]]);
            Session::flash('old', ['email' => $email]);
            $this->redirect(BASE_URL . '/login');
        }

        // Authentification GLOBALE : l'établissement de l'utilisateur n'est pas connu avant d'avoir vérifié son identité (UserModel::findByEmail
        // est limité au tenant par défaut et ne convient pas ici). Même réponse que l'e-mail soit inconnu ou le mot de passe faux.
        $auth = GlobalAuthService::make();
        $user = $auth->authenticate($email, $password);

        if ($user === null) {
            foreach (array_keys($cles) as $cle) {
                $throttle->recordFailure($cle);
            }
            \Core\Logger::security('LOGIN_FAILED', "Tentative échouée pour : {$email}");
            $this->journaliserEchecConnexion($auth, $throttle, $ip, $email);
            Session::flash('errors', ['auth' => ['Email ou mot de passe incorrect.']]);
            Session::flash('old', ['email' => $email]);
            $this->redirect(BASE_URL . '/login');
        }

        $throttle->clear('ec:' . strtolower($email) . '|' . $ip);

        if ((int)$user->actif === 0) {
            \Core\Logger::security('LOGIN_DISABLED', "Compte désactivé : {$email}");
            $auth->journaliser('auth.login_denied', $user, null, 'denied', ['raison' => 'compte_desactive']);
            Session::flash('errors', ['auth' => ['Votre compte est désactivé. Contactez l\'administrateur.']]);
            $this->redirect(BASE_URL . '/login');
        }

        // Établissements de l'utilisateur (appartenances actives, ou repli historique si AUCUNE ligne n'existe) : accessibles ou bloqués.
        $classement  = $auth->classer($user);
        $accessibles = $classement['accessibles'];
        foreach ($classement['bloques'] as $b) {
            if ($b['raison'] === GlobalAuthService::RAISON_ROLE_MANQUANT) {
                \Core\Logger::security('LOGIN_ROLE_MISSING', "user_id={$user->id} etablissement_id={$b['id']} : appartenance sans rôle propre");
                $auth->journaliser('auth.role_missing', $user, (int)$b['id'], 'denied', ['raison' => 'appartenance_sans_role']);
            }
        }

        if ($accessibles === []) {
            $this->refuserConnexion($auth, $user, $classement['bloques']);
        }

        if (count($accessibles) === 1) {
            // Un seul établissement accessible : ouverture directe.
            $ok = $this->completeLogin($user, (int)$accessibles[0]['id']);
            $this->redirect(BASE_URL . ($ok ? '/dashboard' : '/login'));
        }

        // Plusieurs établissements accessibles : identité vérifiée mais AUCUNE session scolaire (Session::isLogged() reste faux, aucun
        // contexte d'établissement) tant que le choix n'est pas fait et revérifié côté serveur. L'état d'attente expire.
        Session::regenerate();
        Session::set('_pending_auth_user_id', (int)$user->id);
        Session::set('_pending_auth_at', time());
        \Core\Logger::security('LOGIN_PENDING_SCHOOL_CHOICE', "En attente de choix d'établissement : {$email} ({$user->id})");
        $auth->journaliser('auth.global_login_success', $user, null, 'success', ['etablissements_accessibles' => count($accessibles), 'choix_requis' => true]);
        $this->redirect(BASE_URL . '/choisir-etablissement');
    }

    /**
     * Identités vérifiées mais aucun établissement utilisable : message PRÉCIS (suspension, abonnement expiré…), ou « aucun établissement ».
     * Réservé à l'utilisateur déjà authentifié : un e-mail inconnu ou un mauvais mot de passe reçoit toujours la réponse neutre.
     *
     * @param list<array<string,mixed>> $bloques
     */
    private function refuserConnexion(GlobalAuthService $auth, object $user, array $bloques): never
    {
        if ($bloques === []) {
            $messages = ['Aucun établissement n\'est associé à votre compte. Contactez l\'administrateur.'];
            $raisons  = ['aucune_appartenance'];
        } else {
            $messages = array_map(static fn(array $b): string => GlobalAuthService::messageBlocage((string)$b['nom'], (string)$b['raison']), $bloques);
            $raisons  = array_map(static fn(array $b): string => (string)$b['raison'], $bloques);
        }
        \Core\Logger::security('LOGIN_DENIED', "user_id={$user->id} : " . implode(',', $raisons));
        $auth->journaliser('auth.login_denied', $user, null, 'denied', ['raisons' => $raisons]);
        Session::flash('errors', ['auth' => $messages]);
        $this->redirect(BASE_URL . '/login');
    }

    /** Échec de connexion dans le journal global (au plus 30 lignes par adresse IP et par fenêtre : un déluge d'échecs ne sature pas le journal). */
    private function journaliserEchecConnexion(GlobalAuthService $auth, \App\Shared\Http\LoginThrottle $throttle, string $ip, string $email): void
    {
        if ($throttle->retryAfter('sal:' . $ip, 30) > 0) {
            return;
        }
        $throttle->recordFailure('sal:' . $ip);
        $auth->journaliser('auth.global_login_failure', null, null, 'failure', ['raison' => 'identifiants_invalides'], $email);
    }

    /**
     * Ouvre la session scolaire pour UN établissement. Le rôle vient de `user_roles_etab` pour CET établissement (jamais du rôle global
     * `users.role` dès qu'une appartenance existe) ; sans rôle propre, la session n'est PAS ouverte. Renvoie false en cas de refus.
     */
    private function completeLogin(object $user, ?int $etablissementId, string $evenement = 'auth.global_login_success'): bool
    {
        $auth = GlobalAuthService::make();
        if ($etablissementId !== null) {
            $resolu = $auth->resoudreRole($user, $etablissementId);
        } else {
            $role   = (string)$user->role;
            $resolu = ['role' => $role !== '' ? $role : null, 'roles' => $role !== '' ? [$role] : [], 'legacy' => true];
        }

        if ($resolu['role'] === null) {
            \Core\Logger::security('LOGIN_ROLE_MISSING', "user_id={$user->id} etablissement_id=" . (string)$etablissementId . ' : appartenance sans rôle propre');
            $auth->journaliser('auth.role_missing', $user, $etablissementId, 'denied', ['raison' => 'appartenance_sans_role']);
            Session::flash('errors', ['auth' => [GlobalAuthService::messageBlocage('de cet établissement', GlobalAuthService::RAISON_ROLE_MANQUANT)]]);
            return false;
        }

        $role        = $resolu['role'];
        $permissions = $auth->permissions($user, (int)$etablissementId, $role, $resolu['legacy']);

        // Établissement quitté (bascule) : mémorisé AVANT la réécriture de la session, pour l'audit.
        $precedent = Session::getUser()['etablissement_id'] ?? null;
        // Anti-fixation : nouvel identifiant de session dès que les privilèges changent (connexion).
        Session::regenerate();

        // Bascule : tout ce qui appartient à l'établissement QUITTÉ est purgé (aucun état résiduel d'un tenant à l'autre).
        if ($evenement === 'auth.tenant_switch') {
            $this->purgerContexteEtablissement();
        }

        // Liste de bascule : uniquement les établissements ACCESSIBLES (le serveur revérifie de toute façon à chaque bascule).
        $classement = $etablissementId !== null ? $auth->classer($user) : ['accessibles' => []];
        $schools    = array_map(static fn(array $e): array => [
            'id' => (int)$e['id'], 'slug' => $e['slug'], 'nom' => $e['nom'], 'nom_court' => $e['nom_court'], 'is_primary' => (bool)$e['is_primary'],
        ], $classement['accessibles']);
        $nomEtablissement = '';
        foreach ($classement['accessibles'] as $e) {
            if ((int)$e['id'] === $etablissementId) { $nomEtablissement = (string)$e['nom']; }
        }

        Session::setUser([
            'id'                     => (int)$user->id,
            'nom'                    => $user->nom,
            'prenom'                 => $user->prenom ?? '',
            'email'                  => $user->email,
            'role'                   => $role,
            'photo'                  => $user->photo ?? null,
            'permissions'            => $permissions,
            'etablissement_id'       => $etablissementId,
            'pwd_at'                 => $auth->dateMotDePasse((int)$user->id) ?? 0,   // invalidation des sessions après changement de mot de passe
            // Uniquement peuplé si l'utilisateur appartient à 2+ établissements
            // (garde la session légère pour le cas mono-tenant, immense majorité).
            'available_etablissements' => count($schools) > 1 ? $schools : [],
        ]);

        if ($etablissementId !== null) {
            $this->membership->setLastUsed((int)$user->id, $etablissementId);
        }

        $auth->touchLastLogin((int)$user->id);
        \Core\Logger::security('LOGIN_SUCCESS', "Connexion réussie : {$user->email} (rôle={$role}, etab={$etablissementId})");
        if (count($resolu['roles']) > 1) {
            $auth->journaliser('auth.multiple_roles', $user, $etablissementId, 'success', ['roles' => $resolu['roles'], 'role' => $role]);
        }
        $detailsEvenement = ['role' => $role];
        if ($evenement === 'auth.tenant_switch') {
            $detailsEvenement += ['de' => $precedent, 'vers' => $etablissementId];
        }
        $auth->journaliser($evenement, $user, $etablissementId, 'success', $detailsEvenement);
        Session::flash('success', $evenement === 'auth.tenant_switch' && $nomEtablissement !== ''
            ? 'Vous êtes maintenant connecté à « ' . $nomEtablissement . ' ».'
            : 'Bienvenue, ' . $user->nom . ' !');
        return true;
    }
    /**
     * Utilisateur en attente de choix d'établissement : identifiant, ou null si l'état est absent ou EXPIRÉ (il est alors purgé).
     * L'état est valable ATTENTE_CHOIX_SECONDES secondes ; un état sans horodatage est considéré comme expiré.
     */
    private function utilisateurEnAttente(): ?int
    {
        $userId = Session::get('_pending_auth_user_id');
        if (!$userId) {
            return null;
        }
        $depuis = (int)Session::get('_pending_auth_at', 0);
        if ($depuis <= 0 || time() - $depuis > GlobalAuthService::ATTENTE_CHOIX_SECONDES) {
            $this->purgerAttente();
            Session::flash('errors', ['auth' => ['Le délai pour choisir votre établissement a expiré. Veuillez vous reconnecter.']]);
            return null;
        }
        return (int)$userId;
    }

    private function purgerAttente(): void
    {
        Session::delete('_pending_auth_user_id');
        Session::delete('_pending_auth_at');
    }

    public function showChooseEtablissement(): void
    {
        $userId = $this->utilisateurEnAttente();
        if ($userId === null) {
            $this->redirect(BASE_URL . '/login');
        }

        $auth = GlobalAuthService::make();
        $user = $auth->lookupById($userId);
        if ($user === null || (int)$user->actif === 0) {
            $this->purgerAttente();
            $this->redirect(BASE_URL . '/login');
        }

        $classement  = $auth->classer($user);
        $accessibles = $classement['accessibles'];
        if ($accessibles === []) {
            // Appartenances modifiées entre-temps (retrait, suspension…) : on ne laisse pas l'utilisateur sur un écran de choix vide.
            $this->purgerAttente();
            $this->refuserConnexion($auth, $user, $classement['bloques']);
        }
        if (count($accessibles) === 1) {
            $this->purgerAttente();
            $ok = $this->completeLogin($user, (int)$accessibles[0]['id'], 'auth.tenant_selected');
            $this->redirect(BASE_URL . ($ok ? '/dashboard' : '/login'));
        }

        $this->render('auth/choose-school', [
            'title'      => 'Choisir un établissement',
            'schools'    => $accessibles,
            // Établissements bloqués, avec leur raison : transmis à la vue (affichage désactivé prévu).
            'bloques'    => $classement['bloques'],
            'lastUsedId' => $this->membership->getLastUsed((int)$userId),
            'userName'   => trim(($user->prenom ?? '') . ' ' . $user->nom),
        ], 'auth');
    }

    public function chooseEtablissement(): void
    {
        $this->verifyCsrf();

        $userId = $this->utilisateurEnAttente();
        if ($userId === null) {
            $this->redirect(BASE_URL . '/login');
        }

        $auth   = GlobalAuthService::make();
        $user   = $auth->lookupById($userId);
        $etabId = (int)$this->request->post('etablissement_id', 0);
        if ($user === null || (int)$user->actif === 0) {
            $this->purgerAttente();
            $this->redirect(BASE_URL . '/login');
        }

        // Le client ne peut jamais imposer un établissement : l'appartenance ACTIVE et l'état de l'établissement sont revérifiés ici.
        $classement = $auth->classer($user);
        $choisi     = null;
        foreach ($classement['accessibles'] as $e) {
            if ((int)$e['id'] === $etabId) {
                $choisi = $e;
            }
        }
        if ($choisi === null) {
            $bloque = null;
            foreach ($classement['bloques'] as $e) {
                if ((int)$e['id'] === $etabId) {
                    $bloque = $e;
                }
            }
            if ($bloque !== null) {
                \Core\Logger::security('SCHOOL_CHOICE_BLOCKED', "user_id={$userId} etablissement_id={$etabId} : {$bloque['raison']}");
                $auth->journaliser('auth.login_denied', $user, $etabId, 'denied', ['raison' => $bloque['raison'], 'etape' => 'choix']);
                Session::flash('errors', ['auth' => [GlobalAuthService::messageBlocage((string)$bloque['nom'], (string)$bloque['raison'])]]);
            } else {
                \Core\Logger::security('SCHOOL_CHOICE_REJECTED', "user_id={$userId} a tenté etablissement_id={$etabId} sans appartenance");
                $auth->journaliser('auth.login_denied', $user, $etabId, 'denied', ['raison' => 'sans_appartenance', 'etape' => 'choix']);
                Session::flash('errors', ['auth' => ['Établissement invalide.']]);
            }
            $this->redirect(BASE_URL . '/choisir-etablissement');
        }

        $this->purgerAttente();
        $ok = $this->completeLogin($user, $etabId, 'auth.tenant_selected');
        $this->redirect(BASE_URL . ($ok ? '/dashboard' : '/login'));
    }
    /**
     * Changement d'établissement en cours de session (blueprint §7.2,
     * POST /auth/switch-school) — pour un utilisateur DÉJÀ pleinement
     * connecté et appartenant à plusieurs établissements. Aucune saisie de
     * mot de passe requise, mais l'appartenance est revérifiée à chaque
     * appel (jamais de confiance dans un id fourni par le formulaire).
     */
    public function switchSchool(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $sessionUser  = Session::getUser();
        $userId       = (int)$sessionUser['id'];
        $courant      = (int)($sessionUser['etablissement_id'] ?? 0);
        $targetEtabId = (int)$this->request->post('school_id', 0);

        // Même établissement : rien à faire.
        if ($targetEtabId === $courant) {
            $this->redirect(BASE_URL . '/dashboard');
        }

        // L'utilisateur est relu PAR SON IDENTIFIANT DE SESSION, tous établissements confondus (UserModel::findById est limité à
        // l'établissement courant : il échouait pour un membre rattaché à un autre établissement).
        $auth = GlobalAuthService::make();
        $user = $auth->lookupById($userId);
        if ($user === null || (int)$user->actif === 0) {
            $this->redirect(BASE_URL . '/login');
        }

        // Le client ne choisit jamais librement : appartenance ACTIVE, état de l'établissement et rôle propre sont revérifiés ici.
        $classement = $auth->classer($user);
        $cible      = null;
        foreach ($classement['accessibles'] as $e) {
            if ((int)$e['id'] === $targetEtabId) {
                $cible = $e;
            }
        }
        if ($cible === null) {
            $bloque = null;
            foreach ($classement['bloques'] as $e) {
                if ((int)$e['id'] === $targetEtabId) {
                    $bloque = $e;
                }
            }
            if ($bloque !== null) {
                \Core\Logger::security('SWITCH_SCHOOL_BLOCKED', "user_id={$userId} → etablissement_id={$targetEtabId} : {$bloque['raison']}");
                $auth->journaliser('auth.login_denied', $user, $targetEtabId, 'denied', ['raison' => $bloque['raison'], 'etape' => 'bascule', 'de' => $courant]);
                Session::flash('error', GlobalAuthService::messageBlocage((string)$bloque['nom'], (string)$bloque['raison']));
            } else {
                \Core\Logger::security('SWITCH_SCHOOL_REJECTED', "user_id={$userId} a tenté school_id={$targetEtabId} sans appartenance");
                $auth->journaliser('auth.login_denied', $user, $targetEtabId, 'denied', ['raison' => 'sans_appartenance', 'etape' => 'bascule', 'de' => $courant]);
                Session::flash('error', 'Vous n\'appartenez pas à cet établissement.');
            }
            $this->redirect(BASE_URL . '/dashboard');
        }

        if (!$this->completeLogin($user, $targetEtabId, 'auth.tenant_switch')) {
            \Core\Logger::security('SWITCH_SCHOOL_REFUSED', "user_id={$userId} → etablissement_id={$targetEtabId} : rôle absent");
            $this->redirect(BASE_URL . '/dashboard');
        }
        \Core\Logger::security('SWITCH_SCHOOL_SUCCESS', "user_id={$userId} → etablissement_id={$targetEtabId}");
        $this->redirect(BASE_URL . '/dashboard');
    }

    /** Données de session propres à l'établissement quitté (purgées à chaque bascule). */
    private function purgerContexteEtablissement(): void
    {
        foreach (['_auth_refreshed_at', '__etab_slug', 'bi_config_direction', 'old_input', 'errors'] as $cle) {
            Session::delete($cle);
        }
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        $u = Session::getUser();
        \Core\Logger::security('LOGOUT', 'Déconnexion : ' . ($u['email'] ?? 'unknown'));
        Session::delete('_pending_auth_user_id');
        Session::logout();
        Session::flash('success', 'Vous avez été déconnecté avec succès.');
        $this->redirect(BASE_URL . '/login');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RÉINITIALISATION DU MOT DE PASSE
    // ═══════════════════════════════════════════════════════════════════════════

    public function showForgotPassword(): void
    {
        if (Session::isLogged()) {
            $this->redirect(BASE_URL . '/dashboard');
        }

        $this->render('auth/forgot-password', [
            'title'  => 'Mot de passe oublié',
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ], 'auth');
    }

    public function forgotPassword(): void
    {
        $this->verifyCsrf();

        $email = trim($this->request->sanitize('email'));

        $errors = $this->validate(['email' => $email], ['email' => 'required|email']);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', ['email' => $email]);
            $this->redirect(BASE_URL . '/forgot-password');
        }

        // Message générique pour ne pas révéler si l'email existe
        $successMsg = 'Si cet email est enregistré, un lien de réinitialisation a été envoyé.';

        // Limitation des demandes (anti-spam de boîte mail et de quota d'envoi) : TOUTES les demandes sont comptées, que l'adresse existe ou non,
        // et la réponse reste identique quand la limite est atteinte (rien n'est révélé).
        $throttle = new \App\Shared\Http\LoginThrottle(getenv('AUTH_THROTTLE_DIR') ?: ROOT_PATH . '/storage/cache/throttle');
        $ip       = $this->request->ip();
        $cles     = [
            'fp:' . strtolower($email) . '|' . $ip => 3,    // cette adresse depuis cette IP
            'fpe:' . strtolower($email)            => 5,    // cette adresse, toutes IP confondues
            'fpi:' . $ip                           => 10,   // cette IP, toutes adresses confondues
        ];
        $limite = false;
        foreach ($cles as $cle => $max) {
            $limite = $limite || $throttle->retryAfter($cle, $max) > 0;
        }
        foreach (array_keys($cles) as $cle) {
            $throttle->recordFailure($cle);
        }
        if ($limite) {
            \Core\Logger::security('PASSWORD_RESET_THROTTLED', "Demande de réinitialisation limitée depuis {$ip}");
            $this->repondreNeutre($successMsg);
        }

        // Recherche GLOBALE (tous établissements) : UserModel::findByEmail est limité à l'établissement par défaut et ne trouvait pas les
        // utilisateurs des autres établissements. La réponse reste identique que le compte existe, soit inconnu ou désactivé.
        $auth    = GlobalAuthService::make();
        $user    = $auth->lookupByEmail($email);
        $auth->journaliser('auth.password_reset_requested', $user, null, 'success', [], $email);
        $apresReponse = null;
        if ($user && (int)$user->actif === 1) {
            $token = bin2hex(random_bytes(32));
            $this->userModel->createResetToken((string)$user->email, $token);

            // Lien ABSOLU construit depuis APP_URL (configuration), jamais depuis l'en-tête Host de la requête (empoisonnement du lien).
            $config   = require ROOT_PATH . '/config/app.php';
            $resetUrl = rtrim((string)$config['url'], '/') . '/reset-password/' . $token;
            // Destinataire = adresse ENREGISTRÉE du compte (jamais la saisie brute du formulaire).
            $destinataire = (string)$user->email;

            // L'envoi (SMTP : plusieurs secondes) est EXÉCUTÉ APRÈS la réponse au navigateur, pour que le temps de réponse ne révèle pas
            // si l'adresse existe. Le jeton est déjà enregistré : le lien est valide quand le mail arrive.
            $apresReponse = static function () use ($destinataire, $resetUrl, $user): void {
                $mailer = new \App\Services\EmailService();
                $envoye = $mailer->send(
                    $destinataire,
                    'Réinitialisation de votre mot de passe',
                    $mailer->buildHtml(
                        'Réinitialisation de votre mot de passe',
                        "Une demande de réinitialisation du mot de passe de votre compte a été faite. Ce lien est valable 1 heure.\n"
                        . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe ne sera pas modifié.",
                        $resetUrl,
                        'Choisir un nouveau mot de passe'
                    )
                );
                // Le lien ne doit JAMAIS apparaître dans la réponse HTTP (prise de contrôle anonyme du compte) NI dans un journal : un échec
                // d'envoi est seulement signalé (journal global, sans jeton ni adresse), l'utilisateur peut refaire une demande.
                if (!$envoye) {
                    \Core\Logger::error('[MAIL] Réinitialisation du mot de passe : e-mail non envoyé.');
                    GlobalAuthService::make()->journaliser('auth.password_reset_mail_failed', $user, null, 'failure', ['raison' => 'envoi_impossible']);
                }
            };
        }

        $this->repondreNeutre($successMsg, $apresReponse);
    }

    /**
     * Réponse IDENTIQUE (message, redirection, en-têtes, durée) que l'adresse existe, soit inconnue, ou que la limite soit atteinte.
     *
     * Quand un travail différé est fourni (envoi du mail), la réponse est terminée et libérée AVANT son exécution : en-têtes
     * « Connection: close » + « Content-Length: 0 », verrou de session relâché (sinon la page suivante attendrait la fin de l'envoi),
     * tampon vidé, puis le travail s'exécute dans la fonction d'arrêt PHP. Aucune file de tâches ni worker requis.
     */
    private function repondreNeutre(string $message, ?callable $apresReponse = null): never
    {
        Session::flash('success', $message);
        header('Connection: close');
        header('Content-Length: 0');
        if ($apresReponse !== null) {
            ignore_user_abort(true);
            register_shutdown_function(static function () use ($apresReponse): void {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                flush();
                @set_time_limit(60);
                try {
                    $apresReponse();
                } catch (\Throwable $e) {
                    \Core\Logger::error('[MAIL] Envoi différé de réinitialisation interrompu : ' . get_class($e));
                }
            });
        }
        $this->redirect(BASE_URL . '/forgot-password');
    }

    public function showResetPassword(string $token): void
    {
        $reset = $this->userModel->findByResetToken($token);

        if (!$reset) {
            Session::flash('error', 'Ce lien est invalide ou a expiré. Veuillez faire une nouvelle demande.');
            $this->redirect(BASE_URL . '/forgot-password');
        }

        $this->render('auth/reset-password', [
            'title'  => 'Nouveau mot de passe',
            'token'  => $token,
            'errors' => Session::getFlash('errors', []),
        ], 'auth');
    }

    public function resetPassword(): void
    {
        $this->verifyCsrf();

        $token    = (string)$this->request->post('token', '');
        $password = (string)$this->request->post('password', '');
        $confirm  = (string)$this->request->post('password_confirmation', '');

        $auth     = GlobalAuthService::make();
        $throttle = new \App\Shared\Http\LoginThrottle(getenv('AUTH_THROTTLE_DIR') ?: ROOT_PATH . '/storage/cache/throttle');
        $ip       = $this->request->ip();
        if ($throttle->retryAfter('rp:' . $ip, 10) > 0) {
            \Core\Logger::security('PASSWORD_RESET_THROTTLED', "Trop d'essais de lien de réinitialisation depuis {$ip}");
            Session::flash('error', 'Trop de tentatives. Réessayez plus tard.');
            $this->redirect(BASE_URL . '/forgot-password');
        }

        // Lien valide ? (lecture seule : le jeton n'est PAS encore consommé, pour qu'un mot de passe refusé puisse être corrigé.)
        $reset = $auth->jetonReinitialisationValide($token);
        $user  = $reset !== null ? $auth->lookupByEmail((string)$reset->email) : null;
        if ($reset === null || $user === null || (int)$user->actif === 0) {
            $throttle->recordFailure('rp:' . $ip);
            \Core\Logger::security('PASSWORD_RESET_INVALID_TOKEN', "Lien de réinitialisation invalide, expiré ou déjà utilisé (depuis {$ip})");
            $auth->journaliser('auth.password_reset_failed', $user, null, 'failure', ['raison' => 'lien_invalide_ou_expire']);
            Session::flash('error', 'Ce lien est invalide ou a expiré.');
            $this->redirect(BASE_URL . '/forgot-password');
        }

        // Politique de la PERSONNE (administrateur d'un établissement : 14 caractères et 3 familles ; sinon le réglage le plus élevé).
        $errors = [];
        foreach ($auth->validerMotDePasse($user, $password) as $message) {
            $errors['password'][] = $message;
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'][] = 'Les mots de passe ne correspondent pas.';
        }
        if (!empty($errors)) {
            $auth->journaliser('auth.password_reset_failed', $user, null, 'failure', ['raison' => 'mot_de_passe_refuse']);
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/reset-password/' . $token);
        }

        // Consommation ATOMIQUE du jeton et changement du mot de passe (une seule requête peut réussir avec un même lien).
        $modifie = $auth->reinitialiserMotDePasse($token, password_hash($password, PASSWORD_BCRYPT));
        if ($modifie === null) {
            $throttle->recordFailure('rp:' . $ip);
            $auth->journaliser('auth.password_reset_failed', $user, null, 'failure', ['raison' => 'lien_deja_utilise']);
            Session::flash('error', 'Ce lien est invalide ou a expiré.');
            $this->redirect(BASE_URL . '/forgot-password');
        }

        \Core\Logger::security('PASSWORD_RESET_SUCCESS', "Mot de passe réinitialisé : user_id={$modifie->id}");
        $auth->journaliser('auth.password_reset', $modifie, null, 'success');
        Session::flash('success', 'Mot de passe mis à jour avec succès. Vous pouvez vous connecter.');
        $this->redirect(BASE_URL . '/login');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PROFIL UTILISATEUR
    // ═══════════════════════════════════════════════════════════════════════════

    public function showProfile(): void
    {
        $this->requireAuth();

        $sessionUser = Session::getUser();
        $user = GlobalAuthService::make()->monCompte((int)$sessionUser['id']);

        $this->render('auth/profile', [
            'title'  => 'Mon profil',
            'user'   => $user,
            'errors' => Session::getFlash('errors', []),
        ]);
    }

    public function updateProfile(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $sessionUser = Session::getUser();
        $userId      = $sessionUser['id'];

        $nom      = trim($this->request->sanitize('nom'));
        $prenom   = trim($this->request->sanitize('prenom'));
        $email    = trim($this->request->sanitize('email'));
        $telephone = trim($this->request->sanitize('telephone'));

        $errors = $this->validate(
            ['nom' => $nom, 'email' => $email],
            ['nom' => 'required|min:2|max:100', 'email' => 'required|email']
        );

        if ($this->userModel->emailExistsForOther($email, $userId)) {
            $errors['email'][] = 'Cet email est déjà utilisé par un autre compte.';
        }

        // Traitement de la photo de profil
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $photoPath = $this->handlePhotoUpload($_FILES['photo'], $errors);
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/profile');
        }

        $data = ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'telephone' => $telephone];
        if ($photoPath !== null) {
            $data['photo'] = $photoPath;
        }

        GlobalAuthService::make()->mettreAJourMonProfil((int)$userId, $data);

        // Mettre à jour la session
        $updated = array_merge($sessionUser, [
            'nom'    => $nom,
            'prenom' => $prenom,
            'email'  => $email,
        ]);
        if ($photoPath !== null) {
            $updated['photo'] = $photoPath;
        }
        Session::setUser($updated);

        Session::flash('success', 'Profil mis à jour avec succès.');
        $this->redirect(BASE_URL . '/profile');
    }

    /** Supprime la photo de profil de l'utilisateur connecté (fichier + référence en base + session). */
    public function deletePhoto(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $sessionUser = Session::getUser();
        $user        = GlobalAuthService::make()->monCompte((int)$sessionUser['id']);
        $chemin      = (string)($user->photo ?? '');

        // Le fichier n'est supprimé que s'il se trouve bien dans le dossier des avatars (jamais un chemin arbitraire).
        if ($chemin !== '' && str_starts_with($chemin, 'storage/uploads/avatars/') && !str_contains($chemin, '..')) {
            (new \App\Services\UploadService())->delete($chemin);
        }

        GlobalAuthService::make()->mettreAJourMonProfil((int)$sessionUser['id'], ['photo' => null]);
        $sessionUser['photo'] = null;
        Session::setUser($sessionUser);

        Session::flash('success', 'Photo de profil supprimée.');
        $this->redirect(BASE_URL . '/profile');
    }

    public function changePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $sessionUser    = Session::getUser();
        $current        = $this->request->post('current_password', '');
        $newPass        = $this->request->post('password', '');
        $confirm        = $this->request->post('password_confirmation', '');

        $errors = $this->validate(
            ['password' => $newPass],
            ['password' => 'required|min:' . $this->passwordMinLength()]
        );

        if ($newPass !== $confirm) {
            $errors['password_confirmation'][] = 'Les mots de passe ne correspondent pas.';
        }

        $user = GlobalAuthService::make()->monCompte((int)$sessionUser['id']);
        if (!$user || !password_verify($current, $user->password)) {
            $errors['current_password'][] = 'Le mot de passe actuel est incorrect.';
        }

        if ($user) {
            // Politique de la PERSONNE (un administrateur ne peut pas affaiblir son mot de passe par le profil).
            foreach (GlobalAuthService::make()->validerMotDePasse($user, (string)$newPass) as $message) {
                $errors['password'][] = $message;
            }
        }
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/profile');
        }

        GlobalAuthService::make()->changerMonMotDePasse((int)$sessionUser['id'], password_hash($newPass, PASSWORD_BCRYPT));
        // La session COURANTE reste valide (nouvelle date du mot de passe) ; les AUTRES sessions de ce compte sont invalidées au prochain
        // rafraîchissement des droits (60 s au plus). Les liens de réinitialisation en attente sont révoqués.
        $sessionUser['pwd_at'] = GlobalAuthService::make()->dateMotDePasse((int)$sessionUser['id']) ?? 0;
        Session::setUser($sessionUser);
        GlobalAuthService::make()->journaliser('auth.password_changed', $user, (int)($sessionUser['etablissement_id'] ?? 0) ?: null, 'success');
        (new \App\Services\AuditService())->log((int)$sessionUser['id'], 'password_changed', 'auth', 'users', (int)$sessionUser['id'], null, ['source' => 'profil']);

        Session::flash('success', 'Mot de passe modifié avec succès.');
        $this->redirect(BASE_URL . '/profile');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INSCRIPTION (accès administrateur uniquement en production)
    // ═══════════════════════════════════════════════════════════════════════════

    public function showRegister(): void
    {
        if (Session::isLogged()) {
            $this->redirect(BASE_URL . '/dashboard');
        }
        $this->render('auth/register', [
            'title'  => 'Inscription',
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ], 'auth');
    }

    public function register(): void
    {
        $this->verifyCsrf();

        // Anti-spam : 3 inscriptions par heure et par adresse IP, 30 par heure au total.
        $limiteur = new \App\Shared\Http\PublicThrottle(ROOT_PATH . '/storage/cache/throttle', 3, 3600);
        $global   = new \App\Shared\Http\PublicThrottle(ROOT_PATH . '/storage/cache/throttle', 30, 3600);
        $ip       = $this->request->ip();
        if (!$limiteur->hit('register', $ip)['allowed'] || !$global->hit('register-global', 'all')['allowed']) {
            \Core\Logger::security('REGISTER_THROTTLED', "Inscription bloquée (trop de demandes) depuis {$ip}");
            Session::flash('error', 'Trop de demandes d\'inscription. Réessayez plus tard.');
            $this->redirect(BASE_URL . '/register');
        }

        $nom      = trim($this->request->sanitize('nom'));
        $email    = trim($this->request->sanitize('email'));
        $password = $this->request->post('password', '');

        $errors = $this->validate(
            ['nom' => $nom, 'email' => $email, 'password' => $password],
            ['nom' => 'required|min:2|max:100', 'email' => 'required|email', 'password' => 'required|min:' . $this->passwordMinLength()]
        );

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', ['nom' => $nom, 'email' => $email]);
            $this->redirect(BASE_URL . '/register');
        }

        // Même réponse que l'e-mail existe déjà ou non (pas d'énumération des comptes) ; aucun compte n'est créé en double.
        if ($this->userModel->findByEmail($email)) {
            \Core\Logger::security('REGISTER_DUPLICATE', "Inscription sur un e-mail existant : {$email}");
            Session::flash('success', 'Compte créé. Il sera activé par un administrateur.');
            $this->redirect(BASE_URL . '/login');
        }

        $this->userModel->insert([
            'nom'      => $nom,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role'     => 'eleve',
            'actif'    => 0, // Compte inactif jusqu'à validation admin
        ]);

        Session::flash('success', 'Compte créé. Il sera activé par un administrateur.');
        $this->redirect(BASE_URL . '/login');
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    /**
     * Avatar du profil : validation par le type MIME réel, extension DÉDUITE du MIME (jamais du nom fourni par le client)
     * et nom aléatoire, via UploadService (comme les photos d'élèves et de professeurs).
     */
    private function handlePhotoUpload(array $file, array &$errors): ?string
    {
        try {
            return (new \App\Services\UploadService())->upload($file, 'avatar', 'avatar_' . Session::getUser()['id'] . '_');
        } catch (\RuntimeException $e) {
            $errors['photo'][] = $e->getMessage();
            return null;
        }
    }
}
