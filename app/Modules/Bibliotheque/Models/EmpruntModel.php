<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Models;

class EmpruntModel
{
    public static function estEnRetard(string $dateRetourPrevue, ?string $dateRetourEffectif = null): bool
    {
        $reference = $dateRetourEffectif ?? date('Y-m-d');
        return $reference > $dateRetourPrevue;
    }

    public static function joursRetard(string $dateRetourPrevue, ?string $dateRetourEffectif = null): int
    {
        $reference = $dateRetourEffectif ?? date('Y-m-d');
        if ($reference <= $dateRetourPrevue) return 0;
        $diff = (new \DateTime($reference))->diff(new \DateTime($dateRetourPrevue));
        return $diff->days;
    }

    public static function montantPenaliteEstime(int $joursRetard, float $tarifJournalier): float
    {
        return round($joursRetard * $tarifJournalier, 2);
    }

    public static function labelStatut(string $statut): string
    {
        return match ($statut) {
            'en_cours'  => 'En cours',
            'en_retard' => 'En retard',
            'retourne'  => 'Retourné',
            'perdu'     => 'Perdu',
            default     => ucfirst($statut),
        };
    }

    public static function peutEtreProlonge(int $prolongations, int $maxProlongations): bool
    {
        return $prolongations < $maxProlongations;
    }

    public static function couleurStatut(string $statut): string
    {
        return match ($statut) {
            'en_cours'  => 'blue',
            'en_retard' => 'red',
            'retourne'  => 'green',
            'perdu'     => 'gray',
            default     => 'gray',
        };
    }
}
