<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class ReservationCree extends Event
{
    public function __construct(
        public readonly int $reservationId,
        public readonly int $ouvrageId,
        public readonly int $userId,
        public readonly int $positionFile,
        public readonly int $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'reservation_id'   => $this->reservationId,
            'ouvrage_id'       => $this->ouvrageId,
            'user_id'          => $this->userId,
            'position_file'    => $this->positionFile,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
