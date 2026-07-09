<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\DTO\EmpruntDTO;
use App\Modules\Bibliotheque\Events\EmpruntCree;
use App\Modules\Bibliotheque\Events\EmpruntRetourne;
use App\Modules\Bibliotheque\Events\EmpruntEnRetard;
use App\Modules\Bibliotheque\Events\EmpruntProlonge;
use App\Modules\Bibliotheque\Events\EmpruntPerdu;
use App\Modules\Bibliotheque\Models\EmpruntModel;
use App\Modules\Bibliotheque\Policies\EmpruntPolicy;
use App\Modules\Bibliotheque\Repositories\EmpruntRepository;
use App\Modules\Bibliotheque\Repositories\ExemplaireRepository;
use Core\EventDispatcher;

class EmpruntService
{
    private EmpruntRepository   $repo;
    private ExemplaireRepository $exemplaires;
    private EmpruntPolicy        $policy;
    private PenaliteService      $penalites;
    private ReservationService   $reservations;

    private const MAX_PROLONGATIONS  = 2;
    private const DUREE_PROLONGATION = 7;

    public function __construct()
    {
        $this->repo         = new EmpruntRepository();
        $this->exemplaires  = new ExemplaireRepository();
        $this->policy       = new EmpruntPolicy();
        $this->penalites    = new PenaliteService();
        $this->reservations = new ReservationService();
    }

    public function creerEmprunt(EmpruntDTO $dto, int $createdBy): int
    {
        $exemplaire          = $this->exemplaires->findById($dto->exemplaireId);
        $nbEnCours           = $this->repo->countEnCoursUser($dto->userId);
        $nbPenalitesImpayees = $this->penalites->countImpayees($dto->userId);

        if (!$this->policy->canBorrow([], $exemplaire ?? [], $nbEnCours, $nbPenalitesImpayees)) {
            throw new \RuntimeException('Emprunt non autorisé : quota atteint, exemplaire indisponible ou pénalités bloquantes.');
        }

        $id = $this->repo->insert([
            'exemplaire_id'     => $dto->exemplaireId,
            'user_id'           => $dto->userId,
            'date_emprunt'      => date('Y-m-d'),
            'date_retour_prevue'=> $dto->dateRetourPrevue,
            'notes'             => $dto->notes,
            'created_by'        => $createdBy,
            'etablissement_id'  => $dto->etablissementId,
        ]);

        $this->exemplaires->updateStatut($dto->exemplaireId, 'emprunte');

        EventDispatcher::dispatch(new EmpruntCree(
            $id,
            $dto->exemplaireId,
            $exemplaire['ouvrage_id'] ?? 0,
            $dto->userId,
            $dto->dateRetourPrevue,
            $createdBy,
            $dto->etablissementId
        ));

        return $id;
    }

    public function retournerEmprunt(int $empruntId, int $userId): void
    {
        $emprunt = $this->repo->findById($empruntId);
        if ($emprunt === null) return;

        $dateRetour  = date('Y-m-d');
        $joursRetard = EmpruntModel::joursRetard($emprunt['date_retour_prevue'], $dateRetour);

        $this->repo->updateRetour($empruntId, $dateRetour);
        $this->exemplaires->updateStatut($emprunt['exemplaire_id'], 'disponible');

        if ($joursRetard > 0) {
            $this->penalites->creerPenaliteRetard($empruntId, $emprunt['user_id'], $joursRetard, $userId, (int)$emprunt['etablissement_id']);
        }

        $this->reservations->notifierProchainEnFile((int)$emprunt['ouvrage_id'], (int)$emprunt['etablissement_id']);

        EventDispatcher::dispatch(new EmpruntRetourne(
            $empruntId,
            (int)$emprunt['exemplaire_id'],
            (int)$emprunt['ouvrage_id'],
            (int)$emprunt['user_id'],
            $dateRetour,
            $joursRetard,
            (int)$emprunt['etablissement_id']
        ));
    }

    public function declarerPerdu(int $empruntId, int $userId, ?string $notes = null): void
    {
        $emprunt = $this->repo->findById($empruntId);
        if ($emprunt === null) return;

        $this->repo->updateStatut($empruntId, 'perdu');
        $this->exemplaires->updateStatut($emprunt['exemplaire_id'], 'perdu');
        $this->penalites->creerPenalitePerte($empruntId, (int)$emprunt['user_id'], $userId, (int)$emprunt['etablissement_id']);

        EventDispatcher::dispatch(new EmpruntPerdu(
            $empruntId,
            (int)$emprunt['exemplaire_id'],
            (int)$emprunt['ouvrage_id'],
            (int)$emprunt['user_id'],
            $userId,
            (int)$emprunt['etablissement_id']
        ));
    }

    public function prolongerEmprunt(int $empruntId, int $userId): void
    {
        $emprunt = $this->repo->findById($empruntId);
        if ($emprunt === null) return;

        if (!$this->policy->canProlonger($emprunt, self::MAX_PROLONGATIONS)) {
            throw new \RuntimeException('Prolongation impossible : quota atteint ou statut incompatible.');
        }

        $nouvelleDateRetour = date('Y-m-d', strtotime($emprunt['date_retour_prevue'] . ' +' . self::DUREE_PROLONGATION . ' days'));
        $this->repo->incrementProlongation($empruntId, $nouvelleDateRetour);

        EventDispatcher::dispatch(new EmpruntProlonge(
            $empruntId,
            (int)$emprunt['user_id'],
            $nouvelleDateRetour,
            (int)$emprunt['prolongations'] + 1
        ));
    }

    public function detectionRetards(int $etablissementId): int
    {
        $overdues = $this->repo->markOverdue($etablissementId);
        foreach ($overdues as $emprunt) {
            $joursRetard = (int)date_diff(new \DateTime($emprunt['date_retour_prevue']), new \DateTime())->days;
            EventDispatcher::dispatch(new EmpruntEnRetard(
                (int)$emprunt['id'],
                (int)$emprunt['exemplaire_id'],
                (int)$emprunt['ouvrage_id'],
                (int)$emprunt['user_id'],
                $emprunt['date_retour_prevue'],
                $joursRetard,
                $etablissementId
            ));
        }
        return count($overdues);
    }

    public function trouver(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function listerEnCours(int $etablissementId, int $page = 1): array
    {
        return $this->repo->findEnCours($etablissementId, $page);
    }

    public function listerEnRetard(int $etablissementId): array
    {
        return $this->repo->findEnRetard($etablissementId);
    }

    public function historiqueUser(int $userId): array
    {
        return $this->repo->findByUser($userId);
    }

    public function rappels(int $etablissementId): array
    {
        return $this->repo->findRappels($etablissementId);
    }
}
