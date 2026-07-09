<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Events;

use Core\Event;

class OuvrageArchive extends Event
{
    public function __construct(
        public readonly int    $ouvrageId,
        public readonly string $titre,
        public readonly int    $userId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'ouvrage_id' => $this->ouvrageId,
            'titre'      => $this->titre,
            'user_id'    => $this->userId,
        ];
    }
}
