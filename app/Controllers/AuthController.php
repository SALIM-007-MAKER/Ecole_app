<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use Core\Tenant\TenantMembershipService;
use App\Models\UserModel;

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

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user->password)) {
            \Core\Logger::security('LOGIN_FAILED', "Tentative échouée pour : {$email}");
            Session::flash('errors', ['auth' => ['Email ou mot de passe incorrect.']]);
            Session::flash('old', ['email' => $email]);
            $this->redirect(BASE_URL . '/login');
        }

        if ((int)$user->actif === 0) {
            \Core\Logger::security('LOGIN_DISABLED', "Compte désactivé : {$email}");
            Session::flash('errors', ['auth' => ['Votre compte est désactivé. Contactez l\'administrateur.']]);
            $this->redirect(BASE_URL . '/login');
        }

        // ── Flux multi-établissements (blueprint §7.2) ───────────────────────
        // Source de vérité : user_etablissements (Phase 14.6), et non plus
        // directement users.etablissement_id (colonne de transition V1).
        $schools = $this->membership->listForUser((int)$user->id);

        if (count($schools) === 0) {
            // Filet de sécurité : aucune appartenance enregistrée (ne devrait
            // pas arriver, T007/14.4 peuple user_etablissements pour tout
            // utilisateur existant) → repli sur la colonne V1 de transition.
            $fallbackEtabId = isset($user->etablissement_id) ? (int)$user->etablissement_id : null;
            $this->completeLogin($user, $fallbackEtabId);
            $this->redirect(BASE_URL . '/dashboard');
        }

        if (count($schools) === 1) {
            // Mono-établissement (100% des utilisateurs aujourd'hui) : activation
            // automatique, comportement strictement identique à avant Phase 14.6.
            $this->completeLogin($user, $schools[0]['id']);
            $this->redirect(BASE_URL . '/dashboard');
        }

        // Multi-établissements : authentification vérifiée mais session PAS
        // encore ouverte — l'utilisateur doit choisir explicitement son
        // établissement avant d'obtenir le moindre accès (Session::isLogged()
        // reste false tant que ce choix n'est pas fait).
        Session::set('_pending_auth_user_id', (int)$user->id);
        \Core\Logger::security('LOGIN_PENDING_SCHOOL_CHOICE', "En attente de choix d'établissement : {$email} ({$user->id})");
        $this->redirect(BASE_URL . '/choisir-etablissement');
    }

    /**
     * Construit la session complète pour un utilisateur ET un établissement
     * déjà validés (appartenance vérifiée par l'appelant). Centralise la
     * résolution rôle+permissions pour ne jamais la dupliquer entre le login
     * direct (mono-établissement), la finalisation du choix (multi-écoles)
     * et le changement d'établissement en cours de session.
     */
    private function completeLogin(object $user, ?int $etablissementId): void
    {
        $role = $etablissementId !== null
            ? ($this->membership->roleForUser((int)$user->id, $etablissementId) ?? $user->role)
            : $user->role;

        $permissions = $this->userModel->getPermissions($role, (int)$user->id, $etablissementId);

        $schools = $etablissementId !== null ? $this->membership->listForUser((int)$user->id) : [];

        Session::setUser([
            'id'                     => (int)$user->id,
            'nom'                    => $user->nom,
            'prenom'                 => $user->prenom ?? '',
            'email'                  => $user->email,
            'role'                   => $role,
            'photo'                  => $user->photo ?? null,
            'permissions'            => $permissions,
            'etablissement_id'       => $etablissementId,
            // Uniquement peuplé si l'utilisateur appartient à 2+ établissements
            // (garde la session légère pour le cas mono-tenant, immense majorité).
            'available_etablissements' => count($schools) > 1 ? $schools : [],
        ]);

        if ($etablissementId !== null) {
            $this->membership->setLastUsed((int)$user->id, $etablissementId);
        }

        $this->userModel->updateLastLogin((int)$user->id);
        \Core\Logger::security('LOGIN_SUCCESS', "Connexion réussie : {$user->email} (rôle={$role}, etab={$etablissementId})");
        Session::flash('success', 'Bienvenue, ' . $user->nom . ' !');
    }

    public function showChooseEtablissement(): void
    {
        $userId = Session::get('_pending_auth_user_id');
        if (!$userId) {
            $this->redirect(BASE_URL . '/login');
        }

        $user = $this->userModel->findById((int)$userId);
        if (!$user) {
            Session::delete('_pending_auth_user_id');
            $this->redirect(BASE_URL . '/login');
        }

        $schools = $this->membership->listForUser((int)$userId);
        if (count($schools) <= 1) {
            // Appartenance modifiée entre-temps (ex: retiré d'une école) —
            // ne pas laisser l'utilisateur bloqué sur un écran de choix inutile.
            Session::delete('_pending_auth_user_id');
            $this->redirect(BASE_URL . '/login');
        }

        $this->render('auth/choose-school', [
            'title'      => 'Choisir un établissement',
            'schools'    => $schools,
            'lastUsedId' => $this->membership->getLastUsed((int)$userId),
            'userName'   => trim(($user->prenom ?? '') . ' ' . $user->nom),
        ], 'auth');
    }

    public function chooseEtablissement(): void
    {
        $this->verifyCsrf();

        $userId = Session::get('_pending_auth_user_id');
        if (!$userId) {
            $this->redirect(BASE_URL . '/login');
        }

        $user = $this->userModel->findById((int)$userId);
        $etabId = (int)$this->request->post('etablissement_id', 0);

        if (!$user || !$this->membership->isMember((int)$userId, $etabId)) {
            \Core\Logger::security('SCHOOL_CHOICE_REJECTED', "user_id={$userId} a tenté etablissement_id={$etabId} sans appartenance");
            Session::flash('errors', ['auth' => ['Établissement invalide.']]);
            $this->redirect(BASE_URL . '/choisir-etablissement');
        }

        Session::delete('_pending_auth_user_id');
        $this->completeLogin($user, $etabId);
        $this->redirect(BASE_URL . '/dashboard');
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

        $sessionUser = Session::getUser();
        $userId = (int)$sessionUser['id'];
        $targetEtabId = (int)$this->request->post('school_id', 0);

        if (!$this->membership->isMember($userId, $targetEtabId)) {
            \Core\Logger::security('SWITCH_SCHOOL_REJECTED', "user_id={$userId} a tenté school_id={$targetEtabId} sans appartenance");
            Session::flash('error', 'Vous n\'appartenez pas à cet établissement.');
            $this->redirect(BASE_URL . '/dashboard');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->redirect(BASE_URL . '/login');
        }

        $this->completeLogin($user, $targetEtabId);
        \Core\Logger::security('SWITCH_SCHOOL_SUCCESS', "user_id={$userId} → etablissement_id={$targetEtabId}");
        $this->redirect(BASE_URL . '/dashboard');
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

        $user = $this->userModel->findByEmail($email);
        if ($user && (int)$user->actif === 1) {
            $token = bin2hex(random_bytes(32));
            $this->userModel->createResetToken($email, $token);

            $resetUrl = BASE_URL . '/reset-password/' . $token;

            // En développement : afficher le lien directement
            $config = require ROOT_PATH . '/config/app.php';
            if ($config['debug']) {
                Session::flash('info', 'Lien de réinitialisation (mode développement) : ' . $resetUrl);
                $this->redirect(BASE_URL . '/forgot-password');
            }

            // En production : envoyer un email (configurer un mailer)
            // mail($email, 'Réinitialisation de votre mot de passe', "Lien : {$resetUrl}");
        }

        Session::flash('success', $successMsg);
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

        $token    = $this->request->post('token', '');
        $password = $this->request->post('password', '');
        $confirm  = $this->request->post('password_confirmation', '');

        $errors = $this->validate(
            ['password' => $password],
            ['password' => 'required|min:8']
        );

        if ($password !== $confirm) {
            $errors['password_confirmation'][] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/reset-password/' . $token);
        }

        $reset = $this->userModel->findByResetToken($token);
        if (!$reset) {
            Session::flash('error', 'Ce lien est invalide ou a expiré.');
            $this->redirect(BASE_URL . '/forgot-password');
        }

        $user = $this->userModel->findByEmail($reset->email);
        if (!$user) {
            Session::flash('error', 'Compte introuvable.');
            $this->redirect(BASE_URL . '/forgot-password');
        }

        $this->userModel->updatePassword((int)$user->id, password_hash($password, PASSWORD_BCRYPT));
        $this->userModel->markResetTokenUsed($token);

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
        $user = $this->userModel->findById($sessionUser['id']);

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

        $this->userModel->updateProfile($userId, $data);

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
            ['password' => 'required|min:8']
        );

        if ($newPass !== $confirm) {
            $errors['password_confirmation'][] = 'Les mots de passe ne correspondent pas.';
        }

        $user = $this->userModel->findById($sessionUser['id']);
        if (!$user || !password_verify($current, $user->password)) {
            $errors['current_password'][] = 'Le mot de passe actuel est incorrect.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/profile');
        }

        $this->userModel->updatePassword($sessionUser['id'], password_hash($newPass, PASSWORD_BCRYPT));

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

        $nom      = trim($this->request->sanitize('nom'));
        $email    = trim($this->request->sanitize('email'));
        $password = $this->request->post('password', '');

        $errors = $this->validate(
            ['nom' => $nom, 'email' => $email, 'password' => $password],
            ['nom' => 'required|min:2|max:100', 'email' => 'required|email', 'password' => 'required|min:8']
        );

        if ($this->userModel->findByEmail($email)) {
            $errors['email'][] = 'Cet email est déjà utilisé.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', ['nom' => $nom, 'email' => $email]);
            $this->redirect(BASE_URL . '/register');
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

    private function handlePhotoUpload(array $file, array &$errors): ?string
    {
        $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize   = 2 * 1024 * 1024; // 2 Mo

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'][] = 'Erreur lors du téléversement de la photo.';
            return null;
        }

        if (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
            $errors['photo'][] = 'Format accepté : JPG, PNG, GIF, WebP.';
            return null;
        }

        if ($file['size'] > $maxSize) {
            $errors['photo'][] = 'La photo ne doit pas dépasser 2 Mo.';
            return null;
        }

        $uploadDir = ROOT_PATH . '/storage/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . Session::getUser()['id'] . '_' . time() . '.' . strtolower($ext);
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errors['photo'][] = 'Impossible d\'enregistrer la photo.';
            return null;
        }

        return 'storage/uploads/avatars/' . $filename;
    }
}
