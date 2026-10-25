<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Models;

use Core\Model;
use Core\Tenant\ReferentielPropre;

class PlageHoraireModel extends Model
{
    use ReferentielPropre;   // référentiel propre à l'établissement (Lot 3)

    protected string $table = 'vs_edt_plages_horaires';
}
