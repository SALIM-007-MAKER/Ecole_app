<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

class MessageModel
{
    const CANAUX  = ['email', 'sms', 'push', 'internal'];
    const STATUTS = ['pending', 'processing', 'sent', 'failed', 'cancelled'];

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'pending'    => 'En attente',
            'processing' => 'En traitement',
            'sent'       => 'Envoyé',
            'failed'     => 'Échoué',
            'cancelled'  => 'Annulé',
            default      => $statut,
        };
    }

    /** Délai de retry en secondes : 60s, 5min, 30min */
    public static function delaiRetry(int $tentative): int
    {
        return match (true) {
            $tentative <= 1 => 60,
            $tentative <= 2 => 300,
            default         => 1800,
        };
    }
}
