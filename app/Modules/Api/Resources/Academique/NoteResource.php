<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Academique;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class NoteResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'              => $this->int('id'),
            'valeur'          => $this->float('valeur'),
            'bareme'          => $this->float('bareme'),
            'appreciation'    => $this->str('appreciation'),
            'statut'          => $this->str('statut'),
            'eleve_user_id'   => $this->int('eleve_user_id'),
            'eleve_nom'       => $this->str('eleve_nom'),
            'evaluation_id'   => $this->int('evaluation_id'),
            'evaluation_titre'=> $this->str('evaluation_titre'),
            'matiere_nom'     => $this->str('matiere_nom'),
            'coefficient'     => $this->float('coefficient'),
            'saisie_par'      => $this->int('saisie_par'),
            'created_at'      => $this->date('created_at'),
            'updated_at'      => $this->date('updated_at'),
        ];
    }
}
