<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Events;

use Core\Event;

class ContractRenewed extends Event
{
    public function __construct(
        public readonly int    $ancienContratId,
        public readonly int    $nouveauContratId,
        public readonly string $nouveauNumero,
        public readonly int    $employeId,
        public readonly string $nouvelleDateDebut,
        public readonly ?string $nouvelleDateFin,
        public readonly int    $renewedBy
    ) {}

    public function toArray(): array
    {
        return [
            'ancien_contrat_id'  => $this->ancienContratId,
            'nouveau_contrat_id' => $this->nouveauContratId,
            'nouveau_numero'     => $this->nouveauNumero,
            'employe_id'         => $this->employeId,
            'nouvelle_date_debut'=> $this->nouvelleDateDebut,
            'nouvelle_date_fin'  => $this->nouvelleDateFin,
            'renewed_by'         => $this->renewedBy,
            'fired_at'           => $this->firedAt(),
        ];
    }
}
