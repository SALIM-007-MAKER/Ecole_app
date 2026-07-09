<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\DTO;

class AttendanceFiltersDTO
{
    public function __construct(
        public readonly ?string $q,
        public readonly ?string $statut,
        public readonly ?string $statutValidation,
        public readonly ?int    $employeId,
        public readonly ?int    $departementId,
        public readonly ?string $dateDebut,
        public readonly ?string $dateFin,
        public readonly ?string $datePresence,
        public readonly ?string $mode,
        public readonly bool    $includeArch,
        public readonly int     $page,
        public readonly int     $perPage,
    ) {}

    public static function fromRequest(array $get): self
    {
        return new self(
            q:                ($get['q']                ?? '') !== '' ? trim($get['q']) : null,
            statut:           ($get['statut']           ?? '') !== '' ? trim($get['statut']) : null,
            statutValidation: ($get['statut_validation']?? '') !== '' ? trim($get['statut_validation']) : null,
            employeId:        ($get['employe_id']       ?? '') !== '' ? (int)$get['employe_id'] : null,
            departementId:    ($get['departement_id']   ?? '') !== '' ? (int)$get['departement_id'] : null,
            dateDebut:        ($get['date_debut']       ?? '') !== '' ? trim($get['date_debut']) : null,
            dateFin:          ($get['date_fin']         ?? '') !== '' ? trim($get['date_fin']) : null,
            datePresence:     ($get['date_presence']    ?? '') !== '' ? trim($get['date_presence']) : null,
            mode:             ($get['mode']             ?? '') !== '' ? trim($get['mode']) : null,
            includeArch:      isset($get['arch']) && $get['arch'] === '1',
            page:             max(1, (int)($get['page'] ?? 1)),
            perPage:          25,
        );
    }
}
