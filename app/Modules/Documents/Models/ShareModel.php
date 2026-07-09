<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

class ShareModel
{
    const DESTINATAIRE_TYPES = ['user', 'role', 'module', 'externe'];
    const PERMISSIONS        = ['lecture', 'telechargement', 'commentaire'];

    const PERMISSION_LABELS = [
        'lecture'         => 'Lecture seule',
        'telechargement'  => 'Téléchargement',
        'commentaire'     => 'Commentaire',
    ];
}
