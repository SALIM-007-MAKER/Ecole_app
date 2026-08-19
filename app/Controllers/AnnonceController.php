<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\AnnonceModel;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

class AnnonceController extends Controller
{
    private AnnonceModel      $annonceModel;
    private NotificationModel $notifModel;
    private ClasseModel       $classeModel;
    private EleveModel        $eleveModel;
    private UserModel         $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->annonceModel = new AnnonceModel();
        $this->notifModel   = new NotificationModel();
        $this->classeModel  = new ClasseModel();
        $this->eleveModel   = new EleveModel();
        $this->userModel    = new UserModel();
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

        // Classes concernant l'utilisateur courant, pour la visibilité des
        // annonces audience='classe' (l'élève lui-même, ou celles de ses enfants).
        $classeIds = [];
        if ($user['role'] === 'eleve' && !empty($user['email'])) {
            $eleve = $this->eleveModel->findByEmail($user['email']);
            if ($eleve && !empty($eleve->classe_id)) {
                $classeIds[] = (int)$eleve->classe_id;
            }
        } elseif ($user['role'] === 'parent') {
            foreach ($this->eleveModel->findByParent((int)$user['id']) as $enfant) {
                if (!empty($enfant->classe_id)) {
                    $classeIds[] = (int)$enfant->classe_id;
                }
            }
            $classeIds = array_values(array_unique($classeIds));
        }

        $q         = trim($this->request->get('q', ''));
        $audFilter = $this->request->get('audience', '');

        $annonces = $this->annonceModel->findPubliees($roleAudience, $q, $audFilter, (int)$user['id'], $classeIds);

        // Consulter la liste des annonces vaut acquittement des notifications
        // "annonce" correspondantes — sans quoi le compteur de la cloche ne
        // redescend jamais tant que l'utilisateur ne va pas explicitement sur
        // /notifications marquer chaque ligne comme lue.
        $this->notifModel->markReadByType((int)$user['id'], 'annonce');

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
            'classes'   => $this->classeModel->findForSelect(),
            'users'     => $this->userModel->findAllWithRoles(UserModel::allRoles()),
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
            'classes'   => $this->classeModel->findForSelect(),
            'users'     => $this->userModel->findAllWithRoles(UserModel::allRoles()),
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
        $audience = $this->request->post('audience', 'tous');

        $classeId = null;
        if ($audience === 'classe') {
            $posted   = (int)$this->request->post('classe_id', 0);
            $classeId = $posted > 0 ? $posted : null;
        }

        $destinatairesIds = null;
        if ($audience === 'utilisateurs') {
            $posted = array_map('intval', (array)$this->request->post('destinataires_ids', []));
            $posted = array_values(array_unique(array_filter($posted, fn(int $id) => $id > 0)));
            $destinatairesIds = !empty($posted) ? json_encode($posted) : null;
        }

        return [
            'titre'              => trim($this->request->post('titre', '')),
            'contenu'            => trim($this->request->post('contenu', '')),
            'audience'           => $audience,
            'classe_id'          => $classeId,
            'destinataires_ids'  => $destinatairesIds,
            'actif'              => $this->request->post('actif', 1) ? 1 : 0,
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
        if ($d['audience'] === 'classe' && empty($d['classe_id'])) {
            $errors['classe_id'][] = 'Sélectionnez une classe.';
        }
        if ($d['audience'] === 'utilisateurs' && empty($d['destinataires_ids'])) {
            $errors['destinataires_ids'][] = 'Sélectionnez au moins un destinataire.';
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
