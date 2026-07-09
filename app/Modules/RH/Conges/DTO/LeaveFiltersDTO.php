<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\DTO;

class LeaveFiltersDTO
{
    public function __construct(
        public readonly string  $q             = '',
        public readonly string  $statut        = '',
        public readonly string  $typeCode      = '',
        public readonly ?int    $employeId     = null,
        public readonly ?int    $departementId = null,
        public readonly string  $dateDebut     = '',
        public readonly string  $dateFin       = '',
        public readonly string  $annee         = '',
        public readonly bool    $includeArch   = false,
        public readonly int     $page          = 1,
        public readonly int     $perPage       = 25,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            q:             trim($get['q']             ?? ''),
            statut:        trim($get['statut']        ?? ''),
            typeCode:      trim($get['type_code']     ?? ''),
            employeId:     ($get['employe_id'] ?? '') !== '' ? (int)$get['employe_id'] : null,
            departementId: ($get['departement_id'] ?? '') !== '' ? (int)$get['departement_id'] : null,
            dateDebut:     trim($get['date_debut']    ?? ''),
            dateFin:       trim($get['date_fin']      ?? ''),
            annee:         trim($get['annee']         ?? ''),
            includeArch:   !empty($get['archived']),
            page:          max(1, (int)($get['page']  ?? 1)),
            perPage:       25,
        );
    }
}
