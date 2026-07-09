<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class AvoirModel extends Model
{
    protected string $table = 'finance_avoirs';

    public const STATUTS = [
        'emis'      => 'Émis',
        'utilise'   => 'Utilisé',
        'rembourse' => 'Remboursé',
        'annule'    => 'Annulé',
    ];
}
