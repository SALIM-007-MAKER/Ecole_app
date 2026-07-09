<?php

namespace App\Modules\VieScolaire\Activites\Models;

use Core\Model;

class ActivityModel extends Model
{
    protected string $table = 'vs_activites';
    protected bool   $softDelete = true;
}
