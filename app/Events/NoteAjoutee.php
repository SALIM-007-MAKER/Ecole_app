<?php

namespace App\Events;

use Core\Event;

class NoteAjoutee extends Event
{
    /**
     * @param int   $eleveId         0 = saisie groupée (toute une classe pour un contrôle)
     * @param int   $nbNotes         Nombre de notes saisies (utilisé en mode groupé)
     * @param bool  $periodePubliee  true → NotificationHandler enverra la notification parent/élève
     */
    public function __construct(
        public readonly int    $controleId,
        public readonly int    $saisieParId,
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly int    $matiereId,
        public readonly int    $eleveId         = 0,
        public readonly float  $valeur          = 0.0,
        public readonly int    $nbNotes         = 0,
        public readonly float  $moyenneMatiere  = 0.0,
        public readonly float  $moyenneGenerale = 0.0,
        public readonly bool   $periodePubliee  = false,
    ) {
        parent::__construct();
    }

    public function isBatch(): bool
    {
        return $this->eleveId === 0;
    }

    public function toArray(): array
    {
        return [
            'controle_id'      => $this->controleId,
            'eleve_id'         => $this->eleveId,
            'classe_id'        => $this->classeId,
            'periode_id'       => $this->periodeId,
            'matiere_id'       => $this->matiereId,
            'valeur'           => $this->valeur,
            'nb_notes'         => $this->nbNotes,
            'moyenne_matiere'  => $this->moyenneMatiere,
            'moyenne_generale' => $this->moyenneGenerale,
            'periode_publiee'  => $this->periodePubliee,
        ];
    }
}
