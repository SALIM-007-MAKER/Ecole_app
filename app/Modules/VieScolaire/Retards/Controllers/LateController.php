<?php

namespace App\Modules\VieScolaire\Retards\Controllers;

use App\Modules\VieScolaire\Retards\DTO\LateDTO;
use App\Modules\VieScolaire\Retards\DTO\LateFiltersDTO;
use App\Modules\VieScolaire\Retards\DTO\LateJustificationDTO;
use App\Modules\VieScolaire\Retards\Policies\LatePolicy;
use App\Modules\VieScolaire\Retards\Services\LateService;
use App\Services\UploadService;
use App\Shared\Auth\EleveScopeTrait;
use Core\Controller;
use Core\View;

class LateController extends Controller
{
    use EleveScopeTrait;

    private LateService   $service;
    private LatePolicy    $policy;
    private UploadService $uploadService;

    public function __construct()
    {
        parent::__construct();
        $this->service       = new LateService();
        $this->policy        = new LatePolicy();
        $this->uploadService = new UploadService();
    }

    public function index(): void
    {
        $this->requirePermission('late.view');
        $user    = $this->currentUser();
        $filters = LateFiltersDTO::fromRequest($_GET);

        $scope = $this->myEleveIds();
        if ($scope !== null) {
            $filters = $filters->withEleveIds($scope);
        }

        $result  = $this->service->paginate($filters);

        $this->view->render('VieScolaire::retards/index', [
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
        $eleves  = (new \App\Models\EleveModel())->findAll('nom', 'ASC');

        $this->view->render('VieScolaire::retards/create', [
            'classes' => $classes,
            'eleves'  => $eleves,
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
            $this->redirect('/v2/vie-scolaire/retards/create');
            exit;
        }

        try {
            $retardId = $this->service->enregistrerManuellement($dto, (int)$user['id']);
            \Core\Session::flash('success', 'Retard enregistré avec succès.');
            $this->redirect("/v2/vie-scolaire/retards/{$retardId}");
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect('/v2/vie-scolaire/retards/create');
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
            $this->view->render('errors/404', [], 'none');
            return;
        }
        $this->assertOwnEleve((int)$retard['eleve_id']);

        $repo     = new \App\Modules\VieScolaire\Retards\Repositories\LateRepository();
        $justif   = $repo->findJustificationByRetard((int)$id);

        $this->view->render('VieScolaire::retards/show', [
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
            $this->view->render('errors/404', [], 'none');
            return;
        }

        if (!$this->policy->canModifyRetard($user, $retard)) {
            \Core\Session::flash('error', 'Ce retard ne peut plus être modifié.');
            $this->redirect("/v2/vie-scolaire/retards/{$id}");
            exit;
        }

        $classes = (new \App\Models\ClasseModel())->findAll();

        $this->view->render('VieScolaire::retards/edit', [
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
            $this->view->render('errors/404', [], 'none');
            return;
        }

        if (!$this->policy->canModifyRetard($user, $retard)) {
            \Core\Session::flash('error', 'Ce retard ne peut plus être modifié.');
            $this->redirect("/v2/vie-scolaire/retards/{$id}");
            exit;
        }

        try {
            $this->service->mettreAJour((int)$id, $_POST, (int)$user['id']);
            \Core\Session::flash('success', 'Retard mis à jour.');
            $this->redirect("/v2/vie-scolaire/retards/{$id}");
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect("/v2/vie-scolaire/retards/{$id}/edit");
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
            $this->view->render('errors/404', [], 'none');
            return;
        }
        $this->assertOwnEleve((int)$retard['eleve_id']);

        $this->view->render('VieScolaire::retards/justifier', [
            'retard' => $retard,
            'user'   => $user,
        ]);
    }

    public function storeJustification(string $id): void
    {
        $this->requirePermission('late.justify');
        $this->verifyCsrf();

        $user   = $this->currentUser();
        $retard = $this->service->findById((int)$id);
        if ($retard === null) {
            http_response_code(404);
            $this->view->render('errors/404', [], 'none');
            return;
        }
        $this->assertOwnEleve((int)$retard['eleve_id']);

        $uploadedFile = null;
        if (!empty($_FILES['fichier_justificatif']['tmp_name'])) {
            $validation = $this->uploadService->validate($_FILES['fichier_justificatif'], 'justification_retard');
            if (!$validation['ok']) {
                $_SESSION['errors'] = ['fichier_justificatif' => $validation['errors']];
                $this->redirect("/v2/vie-scolaire/retards/{$id}/justifier");
                exit;
            }
            try {
                $uploadedFile = $this->uploadService->upload($_FILES['fichier_justificatif'], 'justification_retard', 'retard_' . (int)$id . '_');
            } catch (\Throwable $e) {
                $_SESSION['errors'] = ['fichier_justificatif' => [$e->getMessage()]];
                $this->redirect("/v2/vie-scolaire/retards/{$id}/justifier");
                exit;
            }
        }

        $dto    = LateJustificationDTO::fromRequest($_POST, $uploadedFile);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect("/v2/vie-scolaire/retards/{$id}/justifier");
            exit;
        }

        try {
            $this->service->soumettreJustification((int)$id, $dto, (int)$user['id']);
            \Core\Session::flash('success', 'Justification soumise avec succès.');
            $this->redirect("/v2/vie-scolaire/retards/{$id}");
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
            $this->redirect("/v2/vie-scolaire/retards/{$id}/justifier");
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
            \Core\Session::flash('success', 'Justification validée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/vie-scolaire/retards/{$id}");
        exit;
    }

    public function refuser(string $id): void
    {
        $this->requirePermission('late.validate');
        $this->verifyCsrf();

        $user       = $this->currentUser();
        $motifRefus = trim($_POST['motif_refus'] ?? '');

        if (empty($motifRefus)) {
            \Core\Session::flash('error', 'Le motif de refus est obligatoire.');
            $this->redirect("/v2/vie-scolaire/retards/{$id}");
            exit;
        }

        try {
            $this->service->refuserJustification((int)$id, $motifRefus, (int)$user['id']);
            \Core\Session::flash('success', 'Justification refusée.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $this->redirect("/v2/vie-scolaire/retards/{$id}");
        exit;
    }

    public function destroy(string $id): void
    {
        $this->requirePermission('late.update');
        $this->verifyCsrf();

        $user = $this->currentUser();

        try {
            $this->service->archiver((int)$id, (int)$user['id']);
            \Core\Session::flash('success', 'Retard archivé.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', $e->getMessage());
        }

        $this->redirect('/v2/vie-scolaire/retards');
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

        $this->view->render('VieScolaire::retards/statistiques', [
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
