<?php

namespace App\Events;

use Core\Event;

/**
 * Changement de l'état « actif » d'un élève (désactivation ou réactivation). Désactiver un élève le retire de
 * l'effectif exigé pour la complétude de la Conduite : le changement doit donc être tracé.
 */
class EleveActifChanged extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly bool   $ancienActif,
        public readonly bool   $nouvelActif,
        public readonly int    $changedById,
        public readonly string $source,          // 'modification' | 'reinscription'
        public readonly ?int   $classeId = null,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'eleve.actif_changed';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'     => $this->eleveId,
            'ancien_actif' => $this->ancienActif ? 1 : 0,
            'nouvel_actif' => $this->nouvelActif ? 1 : 0,
            'source'       => $this->source,
            'classe_id'    => $this->classeId,
        ];
    }
}