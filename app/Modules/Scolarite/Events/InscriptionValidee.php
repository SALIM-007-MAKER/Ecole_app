<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

/**
 * Événement V2 — Inscription validée.
 *
 * Dispatché par InscriptionService::valider().
 * Déclencheurs : mise à jour classe_id de l'élève, notification parent.
 */
class InscriptionValidee extends Event
{
    public function __construct(
        public readonly int $inscriptionId,
        public readonly int $eleveId,
        public readonly int $classeId,
        public readonly int $valideParId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'inscription_id' => $this->inscriptionId,
            'eleve_id'       => $this->eleveId,
            'classe_id'      => $this->classeId,
        ];
    }
}
