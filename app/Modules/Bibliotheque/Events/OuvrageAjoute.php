<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class OuvrageAjoute extends Event
{
    public function __construct(
        public readonly int     $ouvrageId,
        public readonly string  $titre,
        public readonly ?string $isbn,
        public readonly int     $userId,
        public readonly int     $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'ouvrage_id'       => $this->ouvrageId,
            'titre'            => $this->titre,
            'isbn'             => $this->isbn,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
