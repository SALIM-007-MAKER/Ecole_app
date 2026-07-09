<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\DTO\DiffusionDTO;
use App\Modules\Communication\DTO\GroupeDTO;
use App\Modules\Communication\Repositories\GroupeRepository;
use App\Modules\Communication\Services\DiffusionService;
use Core\Controller;

class DiffusionController extends Controller
{
    private DiffusionService  $service;
    private GroupeRepository  $groupeRepo;

    public function __construct()
    {
        parent::__construct();
        $this->service    = new DiffusionService();
        $this->groupeRepo = new GroupeRepository();
    }

    public function index(): void
    {
        $this->requirePermission('communication.broadcast');
        $groupes = $this->groupeRepo->findAll((int) ($this->user['etablissement_id'] ?? 1));

        $this->render('Communication::diffusion/index', [
            'groupes' => $groupes,
            'titre'   => 'Diffusion & Groupes',
        ]);
    }

    public function send(): void
    {
        $this->requirePermission('communication.broadcast');
        $this->verifyCsrf();

        $dto    = DiffusionDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $result = $this->service->diffuser($dto, (int) $this->user['id'], (int) ($this->user['etablissement_id'] ?? 1));
        $this->json(['success' => true, 'result' => $result]);
    }

    public function groupes(): void
    {
        $this->requirePermission('communication.manage_groups');
        $groupes = $this->groupeRepo->findAll((int) ($this->user['etablissement_id'] ?? 1));
        $this->json(['groupes' => $groupes]);
    }

    public function creerGroupe(): void
    {
        $this->requirePermission('communication.manage_groups');
        $this->verifyCsrf();

        $dto    = GroupeDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $id = $this->service->creerGroupe($dto, (int) $this->user['id'], (int) ($this->user['etablissement_id'] ?? 1));
        $this->json(['success' => true, 'id' => $id]);
    }

    public function ajouterMembres(int $groupeId): void
    {
        $this->requirePermission('communication.manage_groups');
        $this->verifyCsrf();

        $userIds = $_POST['user_ids'] ?? [];
        $this->service->ajouterMembres($groupeId, (array) $userIds, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function retirerMembre(int $groupeId, int $userId): void
    {
        $this->requirePermission('communication.manage_groups');
        $this->verifyCsrf();
        $this->service->retirerMembre($groupeId, $userId, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function supprimerGroupe(int $groupeId): void
    {
        $this->requirePermission('communication.manage_groups');
        $this->verifyCsrf();
        $this->service->supprimerGroupe($groupeId, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function composer(): void
    {
        $this->requirePermission('communication.broadcast');
        $groupes = $this->groupeRepo->findAll((int) ($this->user['etablissement_id'] ?? 1));
        $this->render('Communication::diffusion/composer', [
            'groupes' => $groupes,
            'titre'   => 'Composer une diffusion',
        ]);
    }
}
