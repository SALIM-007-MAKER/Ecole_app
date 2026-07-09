<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Policies;

class EmpruntPolicy
{
    private const MAX_EMPRUNTS_SIMULTANES = 3;
    private const MAX_PENALITES_BLOQUANTES = 3;

    public function canBorrow(array $user, array $exemplaire, int $nbEmpruntsEnCours, int $nbPenalitesImpayees): bool
    {
        if ($exemplaire['statut'] !== 'disponible') return false;
        if ($nbEmpruntsEnCours >= self::MAX_EMPRUNTS_SIMULTANES) return false;
        if ($nbPenalitesImpayees >= self::MAX_PENALITES_BLOQUANTES) return false;
        return true;
    }

    public function canReserve(array $user, bool $aDejaReservation, int $nbPenalitesImpayees): bool
    {
        if ($aDejaReservation) return false;
        if ($nbPenalitesImpayees >= self::MAX_PENALITES_BLOQUANTES) return false;
        return true;
    }

    public function canProlonger(array $emprunt, int $maxProlongations = 2): bool
    {
        return (int)$emprunt['prolongations'] < $maxProlongations
            && in_array($emprunt['statut'], ['en_cours', 'en_retard'], true);
    }
}
