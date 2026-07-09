<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Academique;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class BulletinResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'               => $this->int('id'),
            'eleve_id'         => $this->int('eleve_id'),
            'eleve_nom'        => $this->str('eleve_nom'),
            'classe_nom'       => $this->str('classe_nom'),
            'periode_nom'      => $this->str('periode_nom'),
            'annee_scolaire'   => $this->str('annee_scolaire'),
            'moyenne_generale' => $this->float('moyenne_generale'),
            'rang'             => $this->int('rang'),
            'mention'          => $this->str('mention'),
            'statut'           => $this->str('statut'),
            'created_at'       => $this->date('created_at'),
        ];
    }
}
