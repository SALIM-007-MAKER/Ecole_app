<?php

namespace App\Modules\VieScolaire\Recompenses\Models;

use Core\Model;
use Core\Tenant\ReferentielPropre;

class RewardCategoryModel extends Model
{
    use ReferentielPropre;   // référentiel propre à l'établissement (Lot 3)

    protected string $table = 'vs_recompense_categories';
}
