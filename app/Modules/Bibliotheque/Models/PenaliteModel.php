<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Models;

class PenaliteModel
{
    public static function estPayee(string $statut): bool
    {
        return $statut === 'payee';
    }

    public static function labelType(string $type): string
    {
        return match ($type) {
            'retard'      => 'Retard',
            'perte'       => 'Perte',
            'degradation' => 'Dégradation',
            default       => ucfirst($type),
        };
    }

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'en_attente' => 'En attente',
            'payee'      => 'Payée',
            'annulee'    => 'Annulée',
            default      => ucfirst($statut),
        };
    }

    public static function montantFormate(float $montant): string
    {
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    public static function couleurStatut(string $statut): string
    {
        return match ($statut) {
            'en_attente' => 'red',
            'payee'      => 'green',
            'annulee'    => 'gray',
            default      => 'gray',
        };
    }
}
