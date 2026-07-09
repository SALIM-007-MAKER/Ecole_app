<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\DTO\ReservationDTO;
use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\ReservationService;
use Core\Controller;

class ReservationController extends Controller
{
    private ReservationService $service;
    private BiblioPolicy       $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ReservationService();
        $this->policy  = new BiblioPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('biblio.manage_loans');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $statut = $_GET['statut'] ?? null;
        $page   = (int)($_GET['page'] ?? 1);
        $reservations = $this->service->lister($etablissementId, $statut, $page);

        $this->render('Bibliotheque::reservations/index', [
            'reservations' => $reservations,
            'statut'       => $statut,
            'page'         => $page,
            'titre'        => 'Réservations',
        ]);
    }

    public function mes(): void
    {
        $this->requirePermission('biblio.reserve');
        $reservations = $this->service->listerParUser((int)$this->user['id']);

        $this->render('Bibliotheque::reservations/index', [
            'reservations' => $reservations,
            'mode'         => 'mes',
            'titre'        => 'Mes réservations',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('biblio.reserve');
        $this->verifyCsrf();

        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $dto = ReservationDTO::fromRequest($_POST, $etablissementId);

        try {
            $id = $this->service->creerReservation($dto);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): void
    {
        $this->requirePermission('biblio.reserve');
        $reservation = $this->service->trouver($id);
        if ($reservation === null) {
            $this->redirect('/v2/bibliotheque/reservations/mes');
            return;
        }
        $this->json($reservation);
    }

    public function confirmer(int $id): void
    {
        $this->requirePermission('biblio.reserve');
        $this->verifyCsrf();

        $this->service->confirmerReservation($id, (int)$this->user['id']);
        $this->json(['success' => true]);
    }

    public function annuler(int $id): void
    {
        $this->requirePermission('biblio.reserve');
        $this->verifyCsrf();

        $raison = $_POST['raison'] ?? 'annulation_user';
        $this->service->annulerReservation($id, (int)$this->user['id'], $raison);
        $this->json(['success' => true]);
    }

    public function cronExpirer(): void
    {
        $etablissementId = (int)($_GET['etablissement_id'] ?? 1);
        $nb = $this->service->expirerReservations($etablissementId);
        $this->json(['success' => true, 'expired' => $nb]);
    }
}
