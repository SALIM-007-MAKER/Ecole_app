<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Finance;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class FactureResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'               => $this->int('id'),
            'numero'           => $this->str('numero'),
            'statut'           => $this->str('statut'),
            'montant_total'    => $this->float('montant_total'),
            'montant_paye'     => $this->float('montant_paye'),
            'reste_a_payer'    => $this->float('reste_a_payer'),
            'date_emission'    => $this->date('date_emission'),
            'date_echeance'    => $this->date('date_echeance'),
            'annee_scolaire'   => $this->str('annee_scolaire'),
            'eleve_id'         => $this->int('eleve_id'),
            'eleve_nom'        => $this->str('eleve_nom'),
            'etablissement_id' => $this->int('etablissement_id'),
            'created_at'       => $this->date('created_at'),
        ];
    }
}
