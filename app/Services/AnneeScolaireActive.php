<?php

declare(strict_types=1);

namespace App\Services;

use Core\Session;
use Core\Tenant\SettingsService;

/**
 * Année scolaire ACTIVE de l'établissement courant (Paramètres › Année scolaire : section « academique », clé « annee_scolaire_active »),
 * ou, à défaut, l'année calculée depuis la date (septembre → août). Source unique pour préremplir les formulaires.
 */
final class AnneeScolaireActive
{
    public static function pour(?int $etablissementId = null, ?int $timestamp = null): string
    {
        $etab = $etablissementId ?? (int)(Session::getUser()['etablissement_id'] ?? 0);
        if ($etab > 0) {
            try {
                $v = SettingsService::make()->get($etab, 'academique', 'annee_scolaire_active', null);
                if (is_string($v) && preg_match('/^(\d{4})-(\d{4})$/', $v, $m) && (int)$m[2] === (int)$m[1] + 1) {
                    return $v;
                }
            } catch (\Throwable) {
                // repli ci-dessous
            }
        }
        return EnseignantPortee::anneeScolaire($timestamp);
    }
}