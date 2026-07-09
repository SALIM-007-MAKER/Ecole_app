<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class ReservationAnnulee extends Event
{
    public function __construct(
        public readonly int    $reservationId,
        public readonly int    $ouvrageId,
        public readonly int    $userId,
        public readonly string $raison,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'reservation_id' => $this->reservationId,
            'ouvrage_id'     => $this->ouvrageId,
            'user_id'        => $this->userId,
            'raison'         => $this->raison,
        ];
    }
}
