<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Controllers;

use Core\Controller;
use App\Modules\Inventaire\Services\ReceptionService;
use App\Modules\Inventaire\Services\CommandeService;
use App\Modules\Inventaire\Services\EmplacementService;
use App\Modules\Inventaire\DTO\ReceptionDTO;

class ReceptionController extends Controller
{
    private ReceptionService  $service;
    private CommandeService   $commandes;
    private EmplacementService $emplacements;

    public function __construct()
    {
        $this->service      = new ReceptionService();
        $this->commandes    = new CommandeService();
        $this->emplacements = new EmplacementService();
    }

    public function create(int $commandeId): void
    {
        $this->requirePermission('inventaire.commande.receive');
        $etab     = (int)($this->user['etablissement_id'] ?? 1);
        $commande = $this->commandes->trouver($commandeId);
        if (!$commande) { $this->redirect('/v2/inventaire/commandes'); return; }

        $this->render('Inventaire::receptions/form', [
            'commande'     => $commande,
            'emplacements' => $this->emplacements->lister($etab),
        ]);
    }

    public function store(int $commandeId): void
    {
        $this->requirePermission('inventaire.commande.receive');
        $this->verifyCsrf();
        $etab = (int)($this->user['etablissement_id'] ?? 1);
        try {
            $dto = ReceptionDTO::fromRequest(array_merge($_POST, ['commande_id' => $commandeId]));
            $this->service->traiterReception($dto, (int)$this->user['id'], $etab);
            $this->redirect("/v2/inventaire/commandes/{$commandeId}");
        } catch (\RuntimeException $e) {
            $commande = $this->commandes->trouver($commandeId);
            $this->render('Inventaire::receptions/form', [
                'error'        => $e->getMessage(),
                'commande'     => $commande,
                'emplacements' => $this->emplacements->lister($etab),
            ]);
        }
    }
}
