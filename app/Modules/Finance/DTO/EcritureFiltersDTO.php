<?php

namespace App\Modules\Finance\DTO;

class EcritureFiltersDTO
{
    public function __construct(
        public readonly int    $exerciceId  = 0,
        public readonly int    $periodeId   = 0,
        public readonly string $journalCode = '',
        public readonly string $compteCode  = '',
        public readonly string $statut      = '',
        public readonly string $source      = '',
        public readonly string $q           = '',
        public readonly string $dateDebut   = '',
        public readonly string $dateFin     = '',
        public readonly int    $page        = 1,
        public readonly int    $perPage     = 50,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            exerciceId:  (int)($data['exercice_id']  ?? 0),
            periodeId:   (int)($data['periode_id']   ?? 0),
            journalCode: trim($data['journal_code']  ?? ''),
            compteCode:  trim($data['compte_code']   ?? ''),
            statut:      trim($data['statut']        ?? ''),
            source:      trim($data['source']        ?? ''),
            q:           trim($data['q']             ?? ''),
            dateDebut:   trim($data['date_debut']    ?? ''),
            dateFin:     trim($data['date_fin']      ?? ''),
            page:        max(1, (int)($data['page']     ?? 1)),
            perPage:     min(200, max(10, (int)($data['per_page'] ?? 50))),
        );
    }
}
