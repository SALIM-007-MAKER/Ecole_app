<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class CommandeRecue extends Event
{
    public function __construct(
        public readonly int    $commandeId,
        public readonly int    $receptionId,
        public readonly bool   $complete,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'commande_id'      => $this->commandeId,
            'reception_id'     => $this->receptionId,
            'complete'         => $this->complete,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
