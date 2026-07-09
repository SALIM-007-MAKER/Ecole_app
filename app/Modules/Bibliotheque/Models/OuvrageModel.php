<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Models;

class OuvrageModel
{
    public static function statutDisponibilite(int $nbDisponibles, int $nbTotal, string $statut = 'actif'): string
    {
        if ($statut === 'archive') return 'archive';
        if ($nbTotal === 0) return 'aucun_exemplaire';
        if ($nbDisponibles > 0) return 'disponible';
        return 'indisponible';
    }

    public static function isbn13Formate(?string $isbn): string
    {
        if ($isbn === null || strlen($isbn) !== 13) return $isbn ?? '';
        return implode('-', [
            substr($isbn, 0, 3),
            substr($isbn, 3, 1),
            substr($isbn, 4, 2),
            substr($isbn, 6, 6),
            substr($isbn, 12, 1),
        ]);
    }

    public static function labelType(string $type): string
    {
        return match ($type) {
            'livre'      => 'Livre',
            'revue'      => 'Revue',
            'bd'         => 'Bande dessinée',
            'manuel'     => 'Manuel scolaire',
            'periodique' => 'Périodique',
            'numerique'  => 'Numérique',
            default      => 'Autre',
        };
    }

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'actif'   => 'Actif',
            'archive' => 'Archivé',
            default   => ucfirst($statut),
        };
    }
}
