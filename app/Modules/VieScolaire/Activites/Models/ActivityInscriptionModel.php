<?php

namespace App\Modules\VieScolaire\Activites\Models;

use Core\Model;

class ActivityInscriptionModel extends Model
{
    protected string $table    = 'vs_activite_inscriptions';
    protected bool   $softDelete = true;
}
