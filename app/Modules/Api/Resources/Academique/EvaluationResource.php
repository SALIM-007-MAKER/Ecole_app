<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources\Academique;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Resources\ApiResource;

class EvaluationResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array
    {
        return [
            'id'               => $this->int('id'),
            'titre'            => $this->str('titre'),
            'type'             => $this->str('type'),
            'bareme'           => $this->float('bareme'),
            'coefficient'      => $this->float('coefficient'),
            'date_evaluation'  => $this->date('date_evaluation'),
            'matiere_nom'      => $this->str('matiere_nom'),
            'classe_nom'       => $this->str('classe_nom'),
            'periode_nom'      => $this->str('periode_nom'),
            'etablissement_id' => $this->int('etablissement_id'),
            'created_at'       => $this->date('created_at'),
        ];
    }
}
