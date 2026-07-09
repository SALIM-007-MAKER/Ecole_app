<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\AnnonceModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

class AnnonceController extends Controller
{
    private AnnonceModel      $annonceModel;
    private NotificationModel $notifModel;

    public function __construct()
    {
        parent::__construct();
        $this->annonceModel = new AnnonceModel();
        $this->notifModel   = new NotificationModel();
    }

    // ─── Liste (élèves, parents, tous) ───────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $roleAudience = match ($user['role']) {
            'parent' => 'parents',
            'eleve'  => 'eleves',
            default  => '',
        };

        $q         = trim($this->request->get('q', ''));
        $audFilter = $this->request->get('audience', '');

        $annonces = $this->annonceModel->findPubliees($roleAudience, $q, $audFilter);

        $this->render('annonces/index', [
            'title'     => 'Annonces',
            'annonces'  => $annonces,
            'audiences' => AnnonceModel::AUDIENCES,
            'canCreate' => $this->can('annonces.create'),
            'q'         => $q,
            'audFilter' => $audFilter,
        ]);
    }

    // ─── Créer ───────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requirePermission('annonces.create');

        $this->render('annonces/form', [
            'title'     => 'Nouvelle annonce',
            'annonce'   => null,
            'audiences' => AnnonceModel::AUDIENCES,
            'isEdit'    => false,
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('annonces.create');
        $this->verifyCsrf();

        $data   = $this->collectData();
        $errors = $this->validateAnnonce($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/annonces/create');
            return;
        }

        $user = $this->currentUser();
        $data['publie_par']   = (int)$user['id'];
        $data['published_at'] = date('Y-m-d H:i:s');

        $id       = $this->annonceModel->insert($data);
        $annonce  = (object)array_merge($data, ['id' => $id]);

        // Notifier l'audience via NotificationService (multi-canaux)
        $this->notifierAudience($annonce);

        Session::flash('success', 'Annonce publiée avec succès.');
        $this->redirect(BASE_URL . '/annonces');
    }

    // ─── Modifier ─────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $this->requirePermission('annonces.edit');

        $annonce = $this->annonceModel->findById((int)$id);
        if (!$annonce) {
            Session::flash('error', 'Annonce introuvable.');
            $this->redirect(BASE_URL . '/annonces');
            return;
        }

        $this->render('annonces/form', [
            'title'     => 'Modifier l\'annonce',
            'annonce'   => $annonce,
            'audiences' => AnnonceModel::AUDIENCES,
            'isEdit'    => true,
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('annonces.edit');
        $this->verifyCsrf();

        $annonce = $this->annonceModel->findById((int)$id);
        if (!$annonce) {
            $this->redirect(BASE_URL . '/annonces');
            return;
        }

        $data   = $this->collectData();
        $errors = $this->validateAnnonce($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/annonces/' . $id . '/edit');
            return;
        }

        $this->annonceModel->update((int)$id, $data);
        Session::flash('success', 'Annonce modifiée avec succès.');
        $this->redirect(BASE_URL . '/annonces');
    }

    public function delete(string $id): void
    {
        $this->requirePermission('annonces.delete');
        $this->verifyCsrf();

        $annonce = $this->annonceModel->findById((int)$id);
        if (!$annonce) {
            Session::flash('error', 'Annonce introuvable.');
            $this->redirect(BASE_URL . '/annonces');
            return;
        }

        $this->annonceModel->delete((int)$id);
        Session::flash('success', 'Annonce supprimée.');
        $this->redirect(BASE_URL . '/annonces');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function collectData(): array
    {
        return [
            'titre'   => trim($this->request->post('titre', '')),
            'contenu' => trim($this->request->post('contenu', '')),
            'audience'=> $this->request->post('audience', 'tous'),
            'actif'   => $this->request->post('actif', 1) ? 1 : 0,
        ];
    }

    private function validateAnnonce(array $d): array
    {
        $errors = [];
        if (empty($d['titre']))   $errors['titre'][]   = 'Le titre est obligatoire.';
        if (empty($d['contenu'])) $errors['contenu'][] = 'Le contenu est obligatoire.';
        if (!isset(AnnonceModel::AUDIENCES[$d['audience']])) {
            $errors['audience'][] = 'Audience invalide.';
        }
        return $errors;
    }

    private function notifierAudience(object $annonce): void
    {
        try {
            (new \App\Services\NotificationService())->onAnnonce($annonce);
        } catch (\Throwable) {}
    }
}
