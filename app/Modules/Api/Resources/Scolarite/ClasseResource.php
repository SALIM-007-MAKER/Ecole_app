<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Scolarite;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class ClasseResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'              => $this->int('id'),
            'nom'             => $this->str('nom'),
            'niveau'          => $this->str('niveau'),
            'annee_scolaire'  => $this->str('annee_scolaire'),
            'effectif_max'    => $this->int('effectif_max'),
            'effectif_actuel' => $this->int('effectif_actuel'),
            'salle'           => $this->str('salle'),
            'filiere'         => $this->str('filiere'),
            'statut'          => $this->str('statut'),
            'etablissement_id'=> $this->int('etablissement_id'),
            'created_at'      => $this->date('created_at'),
        ];
    }
}
