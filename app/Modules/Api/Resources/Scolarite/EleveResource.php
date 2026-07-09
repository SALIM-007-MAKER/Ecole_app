<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Scolarite;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class EleveResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'                => $this->int('id'),
            'matricule'         => $this->str('matricule'),
            'nom'               => $this->str('nom'),
            'prenom'            => $this->str('prenom'),
            'date_naissance'    => $this->date('date_naissance'),
            'sexe'              => $this->str('sexe'),
            'statut'            => $this->str('statut'),
            'photo_url'         => $this->str('photo_url'),
            'classe'            => $this->whenLoaded('classe_nom', [
                'id'  => $this->int('classe_id'),
                'nom' => $this->str('classe_nom'),
            ]),
            'etablissement_id'  => $this->int('etablissement_id'),
            'created_at'        => $this->date('created_at'),
            'updated_at'        => $this->date('updated_at'),
        ];
    }
}
