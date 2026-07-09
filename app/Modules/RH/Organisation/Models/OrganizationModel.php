<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Models;

class OrganizationModel
{
    // Niveaux hiérarchiques postes
    public const NIVEAUX = [
        1 => 'Junior / Opérationnel',
        2 => 'Confirmé / Encadrement',
        3 => 'Senior / Responsable',
        4 => 'Direction / Chef',
    ];

    // Couleurs par catégorie (classes Tailwind)
    public const CATEGORIE_COLORS = [
        'direction'     => 'bg-purple-100 text-purple-800',
        'enseignant'    => 'bg-blue-100 text-blue-800',
        'administratif' => 'bg-slate-100 text-slate-800',
        'support'       => 'bg-amber-100 text-amber-800',
        'technique'     => 'bg-green-100 text-green-800',
    ];

    public const PERIMETRE_LABELS = [
        'etablissement' => 'Établissement',
        'departement'   => 'Département',
        'service'       => 'Service',
        'transversal'   => 'Transversal',
    ];

    public static function niveauLabel(int $n): string
    {
        return self::NIVEAUX[$n] ?? 'Niveau ' . $n;
    }

    public static function categorieColor(string $cat): string
    {
        return self::CATEGORIE_COLORS[$cat] ?? 'bg-gray-100 text-gray-700';
    }
}
