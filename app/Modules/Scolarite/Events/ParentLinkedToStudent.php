<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

class ParentLinkedToStudent extends Event
{
    public function __construct(
        public readonly int    $familleId,
        public readonly int    $eleveId,
        public readonly string $lienParente,
        public readonly int    $linkedById,
    ) {}

    public function getName(): string { return 'famille.linked_to_student'; }

    public function toArray(): array
    {
        return [
            'famille_id'  => $this->familleId,
            'eleve_id'    => $this->eleveId,
            'lien_parente'=> $this->lienParente,
            'linked_by'   => $this->linkedById,
            'fired_at'    => $this->getFiredAt(),
        ];
    }
}
