<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\VieScolaire;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class AbsenceResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'                => $this->int('id'),
            'date_absence'      => $this->date('date_absence'),
            'type'              => $this->str('type'),
            'duree_heures'      => $this->float('duree_heures'),
            'motif'             => $this->str('motif'),
            'justifiee'         => $this->bool('justifiee'),
            'eleve_user_id'     => $this->int('eleve_user_id'),
            'eleve_nom'         => $this->str('eleve_nom'),
            'classe_nom'        => $this->str('classe_nom'),
            'saisie_par'        => $this->int('saisie_par'),
            'etablissement_id'  => $this->int('etablissement_id'),
            'created_at'        => $this->date('created_at'),
        ];
    }
}
