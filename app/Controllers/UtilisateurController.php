<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\UserModel;

class UtilisateurController extends Controller
{
    private UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
    }

    public function index(): void
    {
        $this->requirePermission('users.view');

        $page   = max(1, (int)$this->request->get('page', 1));
        $search = trim($this->request->get('q', ''));
        $role   = $this->request->get('role', '');
        $actif  = $this->request->get('actif', '');

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($role !== '') {
            $where[]  = "role = ?";
            $params[] = $role;
        }
        if ($actif !== '') {
            $where[]  = "actif = ?";
            $params[] = (int)$actif;
        }

        $whereStr   = $where ? implode(' AND ', $where) : '';
        $pagination = $this->userModel->paginateOrdered($page, 20, $whereStr, $params);

        $stats = ['total' => 0];
        foreach (UserModel::allRoles() as $r) {
            $stats[$r]   = $this->userModel->countByRole($r);
            $stats['total'] += $stats[$r];
        }

        $this->render('utilisateurs/index', [
            'title'      => 'Gestion des utilisateurs',
            'pagination' => $pagination,
            'stats'      => $stats,
            'filters'    => compact('search', 'role', 'actif'),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('users.create');

        $this->render('utilisateurs/create', [
            'title'  => 'Nouvel utilisateur',
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('users.create');
        $this->verifyCsrf();

        $nom       = trim($this->request->sanitize('nom'));
        $prenom    = trim($this->request->sanitize('prenom'));
        $email     = trim($this->request->sanitize('email'));
        $role      = $this->request->post('role', '');
        $telephone = trim($this->request->sanitize('telephone'));
        $password  = $this->request->post('password', '');
        $confirm   = $this->request->post('password_confirmation', '');

        $errors = $this->validate(
            ['nom' => $nom, 'email' => $email, 'role' => $role, 'password' => $password],
            ['nom' => 'required|min:2|max:100', 'email' => 'required|email',
             'role' => 'required', 'password' => 'required|min:8']
        );

        if (!in_array($role, UserModel::allRoles(), true)) {
            $errors['role'][] = 'Rôle invalide.';
        }
        // Seul un admin peut créer un autre compte admin.
        if ($role === 'admin' && ($this->currentUser()['role'] ?? '') !== 'admin') {
            $errors['role'][] = 'Seul un administrateur peut attribuer le rôle administrateur.';
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'][] = 'Les mots de passe ne correspondent pas.';
        }
        if (empty($errors['email']) && $this->userModel->findByEmail($email)) {
            $errors['email'][] = 'Cet email est déjà utilisé.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', compact('nom', 'prenom', 'email', 'role', 'telephone'));
            $this->redirect(BASE_URL . '/utilisateurs/create');
        }

        $this->userModel->insert([
            'nom'        => $nom,
            'prenom'     => $prenom,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'role'       => $role,
            'telephone'  => $telephone !== '' ? $telephone : null,
            'actif'      => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', "Utilisateur « {$nom} {$prenom} » créé avec succès.");
        $this->redirect(BASE_URL . '/utilisateurs');
    }

    public function edit(string $id): void
    {
        $this->requirePermission('users.edit');

        $user = $this->userModel->findById((int)$id);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            $this->redirect(BASE_URL . '/utilisateurs');
        }

        $this->render('utilisateurs/edit', [
            'title'  => 'Modifier l\'utilisateur',
            'user'   => $user,
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('users.edit');
        $this->verifyCsrf();

        $userId = (int)$id;
        $user   = $this->userModel->findById($userId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            $this->redirect(BASE_URL . '/utilisateurs');
        }

        $nom       = trim($this->request->sanitize('nom'));
        $prenom    = trim($this->request->sanitize('prenom'));
        $email     = trim($this->request->sanitize('email'));
        $role      = $this->request->post('role', '');
        $telephone = trim($this->request->sanitize('telephone'));
        $actif     = (int)(bool)$this->request->post('actif', 0);
        $password  = $this->request->post('password', '');
        $confirm   = $this->request->post('password_confirmation', '');

        $errors = $this->validate(
            ['nom' => $nom, 'email' => $email, 'role' => $role],
            ['nom' => 'required|min:2|max:100', 'email' => 'required|email', 'role' => 'required']
        );

        if (!in_array($role, UserModel::allRoles(), true)) {
            $errors['role'][] = 'Rôle invalide.';
        }
        // Seul un admin peut accorder ou retirer le rôle admin — sans ce
        // contrôle, un directeur (qui possède déjà users.edit) peut
        // s'auto-promouvoir admin ou modifier un compte admin existant.
        $actingRole = $this->currentUser()['role'] ?? '';
        if ($actingRole !== 'admin' && ($role === 'admin' || $user->role === 'admin')) {
            $errors['role'][] = 'Seul un administrateur peut attribuer ou modifier le rôle administrateur.';
        }
        if (empty($errors['email']) && $this->userModel->emailExistsForOther($email, $userId)) {
            $errors['email'][] = 'Cet email est déjà utilisé par un autre compte.';
        }
        if ($password !== '' && strlen($password) < 8) {
            $errors['password'][] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($password !== '' && $password !== $confirm) {
            $errors['password_confirmation'][] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', compact('nom', 'prenom', 'email', 'role', 'telephone', 'actif'));
            $this->redirect(BASE_URL . '/utilisateurs/' . $userId . '/edit');
        }

        $data = [
            'nom'        => $nom,
            'prenom'     => $prenom,
            'email'      => $email,
            'role'       => $role,
            'telephone'  => $telephone !== '' ? $telephone : null,
            'actif'      => $actif,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->userModel->update($userId, $data);

        Session::flash('success', 'Utilisateur modifié avec succès.');
        $this->redirect(BASE_URL . '/utilisateurs');
    }

    public function delete(string $id): void
    {
        $this->requirePermission('users.delete');
        $this->verifyCsrf();

        $userId      = (int)$id;
        $currentUser = Session::getUser();

        if ($userId === $currentUser['id']) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            $this->redirect(BASE_URL . '/utilisateurs');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            $this->redirect(BASE_URL . '/utilisateurs');
        }

        $this->userModel->delete($userId);
        Session::flash('success', 'Utilisateur supprimé.');
        $this->redirect(BASE_URL . '/utilisateurs');
    }

    public function toggleActif(string $id): void
    {
        $this->requirePermission('users.edit');
        $this->verifyCsrf();

        $userId      = (int)$id;
        $currentUser = Session::getUser();

        if ($userId === $currentUser['id']) {
            Session::flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            $this->redirect(BASE_URL . '/utilisateurs');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            $this->redirect(BASE_URL . '/utilisateurs');
        }

        $newActif = (int)$user->actif === 1 ? 0 : 1;
        $this->userModel->update($userId, ['actif' => $newActif, 'updated_at' => date('Y-m-d H:i:s')]);

        Session::flash('success', $newActif ? 'Compte activé.' : 'Compte désactivé.');
        $this->redirect(BASE_URL . '/utilisateurs');
    }
}
