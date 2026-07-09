<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\DTO\ReservationDTO;
use App\Modules\Bibliotheque\Events\ReservationCree;
use App\Modules\Bibliotheque\Events\ReservationDisponible;
use App\Modules\Bibliotheque\Events\ReservationConfirmee;
use App\Modules\Bibliotheque\Events\ReservationAnnulee;
use App\Modules\Bibliotheque\Events\ReservationExpiree;
use App\Modules\Bibliotheque\Repositories\ExemplaireRepository;
use App\Modules\Bibliotheque\Repositories\ReservationRepository;
use Core\EventDispatcher;

class ReservationService
{
    private ReservationRepository $repo;
    private ExemplaireRepository  $exemplaires;

    private const DELAI_CONFIRMATION_H = 48;

    public function __construct()
    {
        $this->repo        = new ReservationRepository();
        $this->exemplaires = new ExemplaireRepository();
    }

    public function creerReservation(ReservationDTO $dto): int
    {
        if ($this->repo->hasActiveReservation($dto->ouvrageId, $dto->userId)) {
            throw new \RuntimeException('Une réservation active existe déjà pour cet ouvrage.');
        }

        $nbDisponibles = $this->exemplaires->countDisponibles($dto->ouvrageId);
        $statut        = $nbDisponibles > 0 ? 'disponible' : 'en_attente';
        $expiration    = $statut === 'disponible'
            ? date('Y-m-d H:i:s', strtotime('+' . self::DELAI_CONFIRMATION_H . ' hours'))
            : null;

        $position = $this->repo->countInQueue($dto->ouvrageId) + 1;

        $id = $this->repo->insert([
            'ouvrage_id'       => $dto->ouvrageId,
            'user_id'          => $dto->userId,
            'position_file'    => $position,
            'statut'           => $statut,
            'date_expiration'  => $expiration,
            'notes'            => $dto->notes,
            'etablissement_id' => $dto->etablissementId,
        ]);

        EventDispatcher::dispatch(new ReservationCree($id, $dto->ouvrageId, $dto->userId, $position, $dto->etablissementId));

        if ($statut === 'disponible') {
            EventDispatcher::dispatch(new ReservationDisponible($id, $dto->ouvrageId, $dto->userId, $expiration, $dto->etablissementId));
        }

        return $id;
    }

    public function confirmerReservation(int $id, int $userId): void
    {
        $reservation = $this->repo->findById($id);
        if ($reservation === null) return;
        $this->repo->updateStatut($id, 'confirmee');
        EventDispatcher::dispatch(new ReservationConfirmee($id, (int)$reservation['ouvrage_id'], $userId));
    }

    public function annulerReservation(int $id, int $userId, string $raison = 'annulation'): void
    {
        $reservation = $this->repo->findById($id);
        if ($reservation === null) return;
        $this->repo->updateStatut($id, 'annulee');
        EventDispatcher::dispatch(new ReservationAnnulee($id, (int)$reservation['ouvrage_id'], $userId, $raison));
    }

    public function notifierProchainEnFile(int $ouvrageId, int $etablissementId): void
    {
        $next = $this->repo->findNextInQueue($ouvrageId);
        if ($next === null) return;

        $expiration = date('Y-m-d H:i:s', strtotime('+' . self::DELAI_CONFIRMATION_H . ' hours'));
        $this->repo->updateStatut((int)$next['id'], 'disponible', $expiration);

        EventDispatcher::dispatch(new ReservationDisponible(
            (int)$next['id'],
            $ouvrageId,
            (int)$next['user_id'],
            $expiration,
            $etablissementId
        ));
    }

    public function expirerReservations(int $etablissementId): int
    {
        $expired = $this->repo->findExpired($etablissementId);
        foreach ($expired as $r) {
            $this->repo->updateStatut((int)$r['id'], 'expiree');
            EventDispatcher::dispatch(new ReservationExpiree((int)$r['id'], (int)$r['ouvrage_id'], (int)$r['user_id']));
            $this->notifierProchainEnFile((int)$r['ouvrage_id'], $etablissementId);
        }
        return count($expired);
    }

    public function trouver(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function listerParUser(int $userId): array
    {
        return $this->repo->findByUser($userId);
    }

    public function listerParOuvrage(int $ouvrageId): array
    {
        return $this->repo->findByOuvrage($ouvrageId);
    }

    public function lister(int $etablissementId, ?string $statut = null, int $page = 1): array
    {
        return $this->repo->lister($etablissementId, $statut, $page);
    }
}
