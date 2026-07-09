<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\DTO\CampagneDTO;
use App\Modules\Communication\Services\CampagneService;
use Core\Controller;

class CampagneController extends Controller
{
    private CampagneService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CampagneService();
    }

    public function index(): void
    {
        $this->requirePermission('communication.campaign');
        $page      = (int) ($_GET['page'] ?? 1);
        $campagnes = $this->service->lister((int) ($this->user['etablissement_id'] ?? 1), $page);

        $this->render('Communication::campagnes/index', [
            'campagnes' => $campagnes,
            'page'      => $page,
            'titre'     => 'Campagnes de communication',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('communication.campaign');
        $this->verifyCsrf();

        $dto    = CampagneDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $id = $this->service->creer($dto, (int) $this->user['id'], (int) ($this->user['etablissement_id'] ?? 1));
        $this->json(['success' => true, 'id' => $id]);
    }

    public function show(int $id): void
    {
        $this->requirePermission('communication.campaign');
        $campagne = $this->service->trouver($id);
        if ($campagne === null) {
            $this->redirect('/v2/communication/campagnes');
            return;
        }
        $stats          = $this->service->statistiques($id);
        $messagesSample = $this->service->dernierMessages($id, 20);

        $this->render('Communication::campagnes/show', [
            'campagne'        => $campagne,
            'stats'           => $stats,
            'messages_sample' => $messagesSample,
            'titre'           => 'Campagne : ' . ($campagne['nom'] ?? ''),
        ]);
    }

    public function launch(int $id): void
    {
        $this->requirePermission('communication.campaign');
        $this->verifyCsrf();
        $result = $this->service->lancer($id, (int) $this->user['id'], (int) ($this->user['etablissement_id'] ?? 1));
        $this->json(['success' => true, 'result' => $result]);
    }

    public function cancel(int $id): void
    {
        $this->requirePermission('communication.campaign');
        $this->verifyCsrf();
        $this->service->annuler($id, (int) $this->user['id']);
        $this->json(['success' => true]);
    }
}
