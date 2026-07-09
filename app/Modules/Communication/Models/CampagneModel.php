<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

class CampagneModel
{
    const STATUTS = ['brouillon', 'planifiee', 'en_cours', 'terminee', 'annulee'];
    const CIBLES  = ['groupe', 'role', 'classe', 'niveau', 'tous'];

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'brouillon'  => 'Brouillon',
            'planifiee'  => 'Planifiée',
            'en_cours'   => 'En cours',
            'terminee'   => 'Terminée',
            'annulee'    => 'Annulée',
            default      => $statut,
        };
    }

    public static function peutEtreModifiee(string $statut): bool
    {
        return in_array($statut, ['brouillon', 'planifiee'], true);
    }
}
