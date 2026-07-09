<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class EmpruntEnRetard extends Event
{
    public function __construct(
        public readonly int    $empruntId,
        public readonly int    $exemplaireId,
        public readonly int    $ouvrageId,
        public readonly int    $userId,
        public readonly string $dateRetourPrevue,
        public readonly int    $joursRetard,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'emprunt_id'        => $this->empruntId,
            'exemplaire_id'     => $this->exemplaireId,
            'ouvrage_id'        => $this->ouvrageId,
            'user_id'           => $this->userId,
            'date_retour_prevue'=> $this->dateRetourPrevue,
            'jours_retard'      => $this->joursRetard,
            'etablissement_id'  => $this->etablissementId,
        ];
    }
}
