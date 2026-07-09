<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\RH;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class EmployeResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'               => $this->int('id'),
            'matricule'        => $this->str('matricule'),
            'nom'              => $this->str('nom'),
            'prenom'           => $this->str('prenom'),
            'email'            => $this->str('email'),
            'telephone'        => $this->str('telephone'),
            'poste'            => $this->str('poste'),
            'departement'      => $this->str('departement'),
            'type_contrat'     => $this->str('type_contrat'),
            'statut'           => $this->str('statut'),
            'date_embauche'    => $this->date('date_embauche'),
            'etablissement_id' => $this->int('etablissement_id'),
            'created_at'       => $this->date('created_at'),
        ];
    }
}
