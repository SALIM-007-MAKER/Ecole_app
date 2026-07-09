<?php

namespace App\Modules\Scolarite\Events;

use Core\Event;

/**
 * Événement V2 — Élève inscrit dans une classe.
 *
 * Dispatché par InscriptionService::inscrire() après création de l'inscription.
 * Listeners attendus : AuditHandler, NotificationHandler (parent notifié).
 */
class EleveInscrit extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $anneeScolaireId,
        public readonly int    $inscritParId,
        public readonly string $statut = 'en_attente',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'          => $this->eleveId,
            'classe_id'         => $this->classeId,
            'annee_scolaire_id' => $this->anneeScolaireId,
            'statut'            => $this->statut,
        ];
    }
}
