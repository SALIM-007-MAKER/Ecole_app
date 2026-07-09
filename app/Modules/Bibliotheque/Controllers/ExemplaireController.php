<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\DTO\ExemplaireDTO;
use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\ExemplaireService;
use Core\Controller;

class ExemplaireController extends Controller
{
    private ExemplaireService $service;
    private BiblioPolicy      $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ExemplaireService();
        $this->policy  = new BiblioPolicy();
    }

    public function index(int $ouvrageId): void
    {
        $this->requirePermission('biblio.manage_exemplaires');
        $exemplaires = $this->service->listerParOuvrage($ouvrageId);

        $this->render('Bibliotheque::exemplaires/index', [
            'ouvrageId'  => $ouvrageId,
            'exemplaires'=> $exemplaires,
            'titre'      => 'Exemplaires',
        ]);
    }

    public function store(int $ouvrageId): void
    {
        $this->requirePermission('biblio.manage_exemplaires');
        $this->verifyCsrf();

        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $dto = ExemplaireDTO::fromRequest($_POST, $ouvrageId);
        $id  = $this->service->ajouter($dto, (int)$this->user['id'], $etablissementId);

        $this->json(['success' => true, 'id' => $id]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('biblio.manage_exemplaires');
        $this->verifyCsrf();

        $exemplaire = $this->service->trouver($id);
        if ($exemplaire === null) { $this->json(['success' => false], 404); return; }

        $dto = ExemplaireDTO::fromRequest($_POST, (int)$exemplaire['ouvrage_id']);
        $this->service->modifier($id, $dto, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function archive(int $id): void
    {
        $this->requirePermission('biblio.manage_exemplaires');
        $this->verifyCsrf();

        $this->service->archiver($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function changerStatut(int $id): void
    {
        $this->requirePermission('biblio.manage_exemplaires');
        $this->verifyCsrf();

        $statut = $_POST['statut'] ?? '';
        $this->service->changerStatut($id, $statut, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function qrCode(int $id): void
    {
        $this->requirePermission('biblio.view');
        $svg = $this->service->genererQrSvg($id);
        header('Content-Type: image/svg+xml');
        echo $svg;
    }

    public function barcode(int $id): void
    {
        $this->requirePermission('biblio.view');
        $svg = $this->service->genererBarcodeSvg($id);
        header('Content-Type: image/svg+xml');
        echo $svg;
    }
}
