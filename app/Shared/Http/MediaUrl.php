<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Adresse d'affichage d'une image enregistrée en base (photo de profil, d'élève…).
 *
 * Les fichiers du dossier privé « storage/uploads/{type}/{fichier} » ne sont pas publiés par le serveur web
 * (seul public/ l'est) : ils passent par la route authentifiée /uploads/serve/{type}/{fichier}. Les chemins
 * déjà publics (ex. « uploads/professeurs/… ») restent servis directement.
 */
final class MediaUrl
{
    public static function photo(?string $chemin): string
    {
        $chemin = trim((string)$chemin);
        if ($chemin === '') {
            return '';
        }
        if (str_starts_with($chemin, 'storage/uploads/')) {
            $parts   = explode('/', $chemin);
            $fichier = rawurlencode((string)array_pop($parts));
            $type    = rawurlencode((string)array_pop($parts));
            return BASE_URL . '/uploads/serve/' . $type . '/' . $fichier;
        }
        return BASE_URL . '/' . ltrim($chemin, '/');
    }
}
