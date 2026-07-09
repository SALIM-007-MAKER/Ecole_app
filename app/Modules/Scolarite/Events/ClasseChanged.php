<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ClasseChanged extends Event
{
    public function __construct(
        public readonly int  $inscriptionId,
        public readonly int  $eleveId,
        public readonly ?int $ancienneClasseId,
        public readonly int  $nouvelleClasseId,
        public readonly int  $changedById,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id'    => $this->inscriptionId,
            'eleve_id'          => $this->eleveId,
            'ancienne_classe_id'=> $this->ancienneClasseId,
            'nouvelle_classe_id'=> $this->nouvelleClasseId,
            'changed_by_id'     => $this->changedById,
            'fired_at'          => $this->getFiredAt(),
        ];
    }
}
