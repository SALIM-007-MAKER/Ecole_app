<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\Services\EmpruntService;
use App\Modules\Bibliotheque\Services\PenaliteService;
use App\Modules\Bibliotheque\Services\ReservationService;
use Core\Controller;

class MonCompteController extends Controller
{
    private EmpruntService     $emprunts;
    private ReservationService $reservations;
    private PenaliteService    $penalites;

    public function __construct()
    {
        parent::__construct();
        $this->emprunts     = new EmpruntService();
        $this->reservations = new ReservationService();
        $this->penalites    = new PenaliteService();
    }

    public function index(): void
    {
        $this->requirePermission('biblio.borrow');
        $userId = (int)$this->user['id'];

        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $enCours = $this->emprunts->listerEnCours($etablissementId, 1);

        $this->render('Bibliotheque::mon-compte/index', [
            'emprunts_en_cours' => array_filter($enCours['data'] ?? $enCours, fn($e) => (int)$e['user_id'] === $userId),
            'reservations'      => $this->reservations->listerParUser($userId),
            'penalites'         => $this->penalites->listerParUser($userId),
            'nb_impayees'       => $this->penalites->countImpayees($userId),
            'titre'             => 'Mon compte bibliothèque',
        ]);
    }

    public function historique(): void
    {
        $this->requirePermission('biblio.borrow');
        $historique = $this->emprunts->historiqueUser((int)$this->user['id']);

        $this->render('Bibliotheque::mon-compte/index', [
            'historique' => $historique,
            'mode'       => 'historique',
            'titre'      => 'Mon historique d\'emprunts',
        ]);
    }

    public function mesPenalites(): void
    {
        $this->requirePermission('biblio.borrow');
        $penalites = $this->penalites->listerParUser((int)$this->user['id']);

        $this->render('Bibliotheque::penalites/index', [
            'penalites' => $penalites,
            'mode'      => 'mes',
            'titre'     => 'Mes pénalités',
        ]);
    }
}
