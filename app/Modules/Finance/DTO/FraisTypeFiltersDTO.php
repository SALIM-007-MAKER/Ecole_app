<?php

namespace App\Modules\Finance\DTO;

class FraisTypeFiltersDTO
{
    public function __construct(
        public readonly string $q             = '',
        public readonly string $statut        = 'actif',
        public readonly string $periodicite   = '',
        public readonly string $estObligatoire= '',
        public readonly int    $categorieId   = 0,
        public readonly string $anneeScolaire = '',
        public readonly string $niveau        = '',
        public readonly int    $page          = 1,
        public readonly int    $perPage       = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            q:             trim($get['q'] ?? ''),
            statut:        trim($get['statut'] ?? 'actif'),
            periodicite:   trim($get['periodicite'] ?? ''),
            estObligatoire:trim($get['est_obligatoire'] ?? ''),
            categorieId:   (int)($get['categorie_id'] ?? 0),
            anneeScolaire: trim($get['annee_scolaire'] ?? ''),
            niveau:        trim($get['niveau'] ?? ''),
            page:          max(1, (int)($get['page'] ?? 1)),
            perPage:       min(100, max(10, (int)($get['per_page'] ?? 25))),
        );
    }
}
