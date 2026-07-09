<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Finance;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class PaiementResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'               => $this->int('id'),
            'reference'        => $this->str('reference'),
            'montant'          => $this->float('montant'),
            'mode_paiement'    => $this->str('mode_paiement'),
            'date_paiement'    => $this->date('date_paiement'),
            'statut'           => $this->str('statut'),
            'facture_id'       => $this->int('facture_id'),
            'facture_numero'   => $this->str('facture_numero'),
            'eleve_nom'        => $this->str('eleve_nom'),
            'recu_numero'      => $this->str('recu_numero'),
            'etablissement_id' => $this->int('etablissement_id'),
            'created_at'       => $this->date('created_at'),
        ];
    }
}
