<?php

namespace App\Modules\VieScolaire\Recompenses\Controllers;

use App\Modules\VieScolaire\Recompenses\DTO\RewardDTO;
use App\Modules\VieScolaire\Recompenses\DTO\RewardFiltersDTO;
use App\Modules\VieScolaire\Recompenses\Policies\RewardPolicy;
use App\Modules\VieScolaire\Recompenses\Repositories\RewardRepository;
use App\Modules\VieScolaire\Recompenses\Services\RewardService;
use Core\Controller;
use Core\View;

class RewardController extends Controller
{
    private RewardService    $service;
    private RewardRepository $repo;
    private RewardPolicy     $policy;

    public function __construct()
    {
        $this->service = new RewardService();
        $this->repo    = new RewardRepository();
        $this->policy  = new RewardPolicy();
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('reward.view');

        $filters = RewardFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);

        View::render('VieScolaire::recompenses/index', [
            'user'       => $user,
            'recompenses'=> $result['data'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'pages'      => $result['pages'],
            'filters'    => $filters,
            'categories' => $this->service->categories(),
            'policy'     => $this->policy,
        ]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('reward.view');

        $classeId = (int)($_GET['classe_id'] ?? 0);
        $annee    = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $stats    = $classeId ? $this->service->statistiquesClasse($classeId, $annee) : [];

        View::render('VieScolaire::recompenses/statistiques', [
            'user'    => $user,
            'stats'   => $stats,
            'classeId'=> $classeId,
            'annee'   => $annee,
        ]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requireAuth();
        $this->requirePermission('reward.export');

        $filters     = RewardFiltersDTO::fromRequest(array_merge($_GET, ['per_page' => 500]));
        $recompenses = $this->repo->findAll($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="recompenses_' . date('Y-m-d') . '.csv"');
        $f = fopen('php://output', 'w');
        fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($f, ['ID', 'Élève', 'Classe', 'Année', 'Catégorie', 'Niveau', 'Motif', 'Statut', 'Date', 'Attribué par'], ';');

        foreach ($recompenses as $r) {
            fputcsv($f, [
                $r['id'],
                $r['eleve_nom'] . ' ' . $r['eleve_prenom'],
                $r['classe_nom'],
                $r['annee_scolaire'],
                $r['categorie_nom'],
                $r['niveau'],
                $r['motif'],
                $r['statut'],
                date('d/m/Y', strtotime($r['date_attribution'])),
                $r['attribue_par_nom'] . ' ' . $r['attribue_par_prenom'],
            ], ';');
        }
        fclose($f);
        exit;
    }

    // ── Classement comportemental ─────────────────────────────────────────────

    public function classement(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('reward.view');

        $classeId = (int)($_GET['classe_id'] ?? 0);
        $annee    = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $data     = $classeId ? $this->service->classementComportemental($classeId, $annee) : [];

        View::render('VieScolaire::recompenses/classement', [
            'user'    => $user,
            'data'    => $data,
            'classeId'=> $classeId,
            'annee'   => $annee,
        ]);
    }

    // ── Créer ─────────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('reward.create');

        View::render('VieScolaire::recompenses/create', [
            'user'      => $user,
            'categories'=> $this->service->categories(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('reward.create');
        $user = $this->currentUser();

        $uploadedFile = null;
        if (!empty($_FILES['piece_jointe']['tmp_name'])) {
            $ext  = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
            $dest = 'uploads/recompenses/' . uniqid('rw_', true) . '.' . $ext;
            if (!is_dir('uploads/recompenses')) {
                mkdir('uploads/recompenses', 0755, true);
            }
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
                $uploadedFile = $dest;
            }
        }

        try {
            $dto    = RewardDTO::fromRequest($_POST, $uploadedFile);
            $errors = $dto->validate();
            if (!empty($errors)) {
                $_SESSION['flash_error'] = implode('<br>', $errors);
                header('Location: /v2/vie-scolaire/recompenses/create');
                exit;
            }

            $reward = $this->service->attribuerRecompense($dto, (int)$user['id']);
            $_SESSION['flash_success'] = 'Récompense attribuée avec succès.';
            header('Location: /v2/vie-scolaire/recompenses/' . $reward['id']);
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /v2/vie-scolaire/recompenses/create');
            exit;
        }
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('reward.view');

        $reward = $this->service->findById($id);
        if ($reward === null) {
            http_response_code(404);
            View::render('errors/404', ['user' => $user]);
            return;
        }

        $historique = $this->service->historique($id);

        View::render('VieScolaire::recompenses/show', [
            'user'      => $user,
            'reward'    => $reward,
            'historique'=> $historique,
            'policy'    => $this->policy,
        ]);
    }

    // ── Modifier ──────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->requirePermission('reward.update');

        $reward = $this->service->findById($id);
        if ($reward === null) {
            http_response_code(404);
            View::render('errors/404', ['user' => $user]);
            return;
        }

        if (!$this->policy->canModify($user, $reward)) {
            $_SESSION['flash_error'] = 'Cette récompense ne peut plus être modifiée.';
            header('Location: /v2/vie-scolaire/recompenses/' . $id);
            exit;
        }

        View::render('VieScolaire::recompenses/edit', [
            'user'      => $user,
            'reward'    => $reward,
            'categories'=> $this->service->categories(),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('reward.update');
        $user = $this->currentUser();

        try {
            $dto    = RewardDTO::fromRequest($_POST);
            $errors = $dto->validate();
            if (!empty($errors)) {
                $_SESSION['flash_error'] = implode('<br>', $errors);
                header('Location: /v2/vie-scolaire/recompenses/' . $id . '/edit');
                exit;
            }

            $this->service->mettreAJour($id, $dto, (int)$user['id']);
            $_SESSION['flash_success'] = 'Récompense mise à jour.';
            header('Location: /v2/vie-scolaire/recompenses/' . $id);
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /v2/vie-scolaire/recompenses/' . $id . '/edit');
            exit;
        }
    }

    // ── Valider ───────────────────────────────────────────────────────────────

    public function valider(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('reward.validate');
        $user = $this->currentUser();

        try {
            $this->service->validerRecompense($id, (int)$user['id']);
            $_SESSION['flash_success'] = 'Récompense validée.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /v2/vie-scolaire/recompenses/' . $id);
        exit;
    }

    // ── Révoquer ──────────────────────────────────────────────────────────────

    public function revoquer(int $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->requirePermission('reward.validate');
        $user  = $this->currentUser();
        $motif = trim($_POST['motif'] ?? '');

        try {
            $this->service->revoquerRecompense($id, $motif, (int)$user['id']);
            $_SESSION['flash_success'] = 'Récompense révoquée.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /v2/vie-scolaire/recompenses/' . $id);
        exit;
    }
}
