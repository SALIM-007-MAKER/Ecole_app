<?php

namespace App\Events;

use Core\Event;

class EleveCreated extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $matricule,
        public readonly int    $classeId,
        public readonly int    $createdById,
        public readonly bool   $fromCsvImport = false,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'        => $this->eleveId,
            'nom'             => $this->nom,
            'prenom'          => $this->prenom,
            'matricule'       => $this->matricule,
            'classe_id'       => $this->classeId,
            'from_csv_import' => $this->fromCsvImport,
        ];
    }
}
