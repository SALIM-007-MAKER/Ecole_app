<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Models;

class ExemplaireModel
{
    public static function estDisponible(string $statut): bool
    {
        return $statut === 'disponible';
    }

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'disponible'   => 'Disponible',
            'emprunte'     => 'Emprunté',
            'reserve'      => 'Réservé',
            'en_reparation'=> 'En réparation',
            'perdu'        => 'Perdu',
            'retire'       => 'Retiré',
            default        => ucfirst($statut),
        };
    }

    public static function labelEtat(string $etat): string
    {
        return match ($etat) {
            'bon'       => 'Bon état',
            'use'       => 'Usé',
            'deteriore' => 'Détérioré',
            default     => ucfirst($etat),
        };
    }

    public static function genererNumeroInventaire(int $etablissementId, int $sequence): string
    {
        return sprintf('BIB-%d-%05d', date('Y'), $sequence);
    }

    public static function couleurStatut(string $statut): string
    {
        return match ($statut) {
            'disponible'   => 'green',
            'emprunte'     => 'blue',
            'reserve'      => 'yellow',
            'en_reparation'=> 'orange',
            'perdu'        => 'red',
            'retire'       => 'gray',
            default        => 'gray',
        };
    }
}
