<?php

namespace App\Modules\VieScolaire\Activites\Models;

use Core\Model;
use Core\Tenant\ReferentielPropre;

class ActivityCategoryModel extends Model
{
    use ReferentielPropre;   // référentiel propre à l'établissement (Lot 3)

    protected string $table = 'vs_activite_categories';
}
