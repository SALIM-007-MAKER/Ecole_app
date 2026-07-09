<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

class TemplateModel
{
    const CANAUX  = ['email', 'sms', 'push', 'internal'];
    const LANGUES = ['fr', 'ar', 'en'];

    const VARIABLES_COMMUNES = [
        'nom_complet',
        'prenom',
        'nom_etablissement',
        'date',
        'url_plateforme',
    ];
}
