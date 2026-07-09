<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class TropPercuModel extends Model
{
    protected string $table = 'finance_trop_percus';

    public const STATUTS = [
        'en_attente' => 'En attente',
        'restitue'   => 'Restitué',
        'impute'     => 'Imputé sur facture',
        'annule'     => 'Annulé',
    ];
}
