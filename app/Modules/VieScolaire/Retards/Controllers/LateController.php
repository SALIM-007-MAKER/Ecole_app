<?php

namespace App\Modules\VieScolaire\Retards\Controllers;

use App\Modules\VieScolaire\Retards\DTO\LateDTO;
use App\Modules\VieScolaire\Retards\DTO\LateFiltersDTO;
use App\Modules\VieScolaire\Retards\DTO\LateJustificationDTO;
use App\Modules\VieScolaire\Retards\Policies\LatePolicy;
use App\Modules\VieScolaire\Retards\Services\LateService;
use Core\Controller;
use Core\View;

class LateController extends Controller
{
    private LateService $service;
    private LatePolicy  $policy;

    public function __construct()
    {
        $this->service = new LateService();
        $this->policy  = new LatePolicy();
    }

    public function index(): void
    {
        $this->requirePermission('late.view');
        $user    = $this->currentUser();
        $filters = LateFiltersDTO::fromRequest($_GET);
        $result  = $this->service->paginate($filters);

        View::render('VieScolaire::retards/index', [
            'retards'  => $result['data'],
            'total'    => $result['total'],
            'page'     => $result['page'],
            'lastPage' => $result['last_page'],
            'filters'  => $filters,
            'policy'   => $this->policy,
            'user'     => $user,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('late.create');

        $classes = (new \App\Models\ClasseModel())->findAll();

        View::render('VieScolaire::retards/create', [
            'classes' => $classes,
            'user'    => $this->currentUser(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('late.create');
        $this->verifyCsrf();

        $user = $this->currentUser();
        $dto  = LateDTO::fromRequest($_POST);

        $errors = $dto->validate();
        if (!empty($errors)) {
            $_SESSION['errors']    = $errors;
            $_SESSION['old_input'] = $_POST;
            header('Location: /v2/vie-scolaire/retards/create');
            exit;
        }

        try {
            $retardId = $this->service->enregistrerManuellement($dto, (int)$user['id']);
            $_SESSION['flash_success'] = 'Retard enregistré avec succès.';
            header("Location: /v2/vie-scolaire/retards/{$retardId}");
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /v2/vie-scolaire/retards/create');
        }
        exit;
    }

    public function show(string $id): void
    {
        $this->requirePermission('late.view');
        $user   = $this->currentUser();
        $retard = $this->service->findById((int)$id);
        if ($retard === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $repo     = new \App\Modules\VieScolaire\Retards\Repositories\LateRepository();
        $justif   = $repo->findJustificationByRetard((int)$id);

        View::render('VieScolaire::retards/show', [
            'retard'      => $retard,
            'justif'      => $justif,
            'policy'      => $this->policy,
            'user'        => $user,
            'canModify'   => $this->policy->canModifyRetard($user, $retard),
            'canJustify'  => $this->policy->canJustify($user),
            'canValidate' => $this->policy->canValidate($user),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requirePermission('late.update');
        $user   = $this->currentUser();
        $retard = $this->service->findById((int)$id);
        if ($retard === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if (!$this->policy->canModifyRetard($user, $retard)) {
            $_SESSION['flash_error'] = 'Ce retard ne peut plus être modifié.';
            header("Location: /v2/vie-scolaire/retards/{$id}");
            exit;
        }

        $classes = (new \App\Models\ClasseModel())->findAll();

        View::render('VieScolaire::retards/edit', [
            'retard'  => $retard,
            'classes' => $classes,
            'user'    => $user,
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('late.update');
        $this->verifyCsrf();

        $user   = $this->currentUser();
        $retard = $this->service->findById((int)$id);
        if ($retard === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if (!$this->policy->canModifyRetard($user, $retard)) {
            $_SESSION['flash_error'] = 'Ce retard ne peut plus être modifié.';
            header("Location: /v2/vie-scolaire/retards/{$id}");
            exit;
        }

        try {
            $this->service->mettreAJour((int)$id, $_POST, (int)$user['id']);
            $_SESSION['flash_success'] = 'Retard mis à jour.';
            header("Location: /v2/vie-scolaire/retards/{$id}");
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header("Location: /v2/vie-scolaire/retards/{$id}/edit");
        }
        exit;
    }

    public function justifier(string $id): void
    {
        $this->requirePermission('late.justify');
        $user   = $this->currentUser();
        $retard = $this->service->findById((int)$id);
        if ($retard === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('VieScolaire::retards/justifier', [
            'retard' => $retard,
            'user'   => $user,
        ]);
    }

    public function storeJustification(string $id): void
    {
        $this->requirePermission('late.justify');
        $this->verifyCsrf();

        $user = $this->currentUser();

        $uploadedFile = null;
        if (!empty($_FILES['fichier_justificatif']['tmp_name'])) {
            $ext      = pathinfo($_FILES['fichier_justificatif']['name'], PATHINFO_EXTENSION);
            $filename = 'retard_' . (int)$id . '_' . time() . '.' . $ext;
            $dest     = __DIR__ . '/../../../../../../public/uploads/retards/' . $filename;
            if (move_uploaded_file($_FILES['fichier_justificatif']['tmp_name'], $dest)) {
                $uploadedFile = '/public/uploads/retards/' . $filename;
            }
        }

        $dto    = LateJustificationDTO::fromRequest($_POST, $uploadedFile);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            header("Location: /v2/vie-scolaire/retards/{$id}/justifier");
            exit;
        }

        try {
            $this->service->soumettreJustification((int)$id, $dto, (int)$user['id']);
            $_SESSION['flash_success'] = 'Justification soumise avec succès.';
            header("Location: /v2/vie-scolaire/retards/{$id}");
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header("Location: /v2/vie-scolaire/retards/{$id}/justifier");
        }
        exit;
    }

    public function valider(string $id): void
    {
        $this->requirePermission('late.validate');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->validerJustification((int)$id, (int)$user['id']);
            $_SESSION['flash_success'] = 'Justification validée.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header("Location: /v2/vie-scolaire/retards/{$id}");
        exit;
    }

    public function refuser(string $id): void
    {
        $this->requirePermission('late.validate');
        $this->verifyCsrf();

        $user       = $this->currentUser();
        $motifRefus = trim($_POST['motif_refus'] ?? '');

        if (empty($motifRefus)) {
            $_SESSION['flash_error'] = 'Le motif de refus est obligatoire.';
            header("Location: /v2/vie-scolaire/retards/{$id}");
            exit;
        }

        try {
            $this->service->refuserJustification((int)$id, $motifRefus, (int)$user['id']);
            $_SESSION['flash_success'] = 'Justification refusée.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header("Location: /v2/vie-scolaire/retards/{$id}");
        exit;
    }

    public function destroy(string $id): void
    {
        $this->requirePermission('late.update');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->archiver((int)$id, (int)$user['id']);
            $_SESSION['flash_success'] = 'Retard archivé.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /v2/vie-scolaire/retards');
        exit;
    }

    public function statistiques(): void
    {
        $this->requirePermission('late.view');
        $user     = $this->currentUser();
        $classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : 0;
        $annee    = $_GET['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1);
        $classes  = (new \App\Models\ClasseModel())->findAll();

        $stats = $classeId > 0
            ? $this->service->statistiquesClasse($classeId, $annee)
            : [];

        View::render('VieScolaire::retards/statistiques', [
            'stats'    => $stats,
            'classes'  => $classes,
            'classeId' => $classeId,
            'annee'    => $annee,
            'seuil'    => LateService::SEUIL_ALERTE,
            'user'     => $user,
        ]);
    }

    public function export(): void
    {
        $this->requirePermission('late.export');

        $filters = LateFiltersDTO::fromRequest(array_merge($_GET, ['per_page' => 1000, 'page' => 1]));
        $result  = $this->service->paginate($filters);
        $rows    = $result['data'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="retards_' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['ID', 'Élève', 'Classe', 'Date', 'Heure prévue', 'Heure arrivée', 'Durée (min)', 'Statut', 'Observation']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['eleve_prenom'] . ' ' . $r['eleve_nom'],
                $r['classe_nom'],
                $r['date_retard'],
                $r['heure_prevue'] ?? '',
                $r['heure_arrivee'],
                $r['duree_minutes'],
                $r['statut'],
                $r['observation'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }
}
