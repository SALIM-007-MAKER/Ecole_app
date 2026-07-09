<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Models;

class ReservationModel
{
    public static function estExpiree(?string $dateExpiration): bool
    {
        if ($dateExpiration === null) return false;
        return date('Y-m-d H:i:s') > $dateExpiration;
    }

    public static function delaiConfirmationRestant(?string $dateExpiration): string
    {
        if ($dateExpiration === null) return '';
        $now  = new \DateTime();
        $exp  = new \DateTime($dateExpiration);
        if ($now > $exp) return 'Expiré';
        $diff = $now->diff($exp);
        if ($diff->days > 0) return $diff->days . 'j ' . $diff->h . 'h';
        return $diff->h . 'h ' . $diff->i . 'm';
    }

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'en_attente' => 'En attente',
            'disponible' => 'Disponible',
            'confirmee'  => 'Confirmée',
            'annulee'    => 'Annulée',
            'expiree'    => 'Expirée',
            default      => ucfirst($statut),
        };
    }

    public static function couleurStatut(string $statut): string
    {
        return match ($statut) {
            'en_attente' => 'yellow',
            'disponible' => 'blue',
            'confirmee'  => 'green',
            'annulee'    => 'gray',
            'expiree'    => 'red',
            default      => 'gray',
        };
    }
}
