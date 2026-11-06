<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;

/**
 * Identité d'un établissement pour l'en-tête de SES documents imprimés (bulletins, reçus, factures, relevés, rapports…).
 *
 * Règles :
 *  - le logo est le logo PROPRE de l'établissement (BrandingData::logoPourDocuments) ; sans logo propre, aucun logo n'est imprimé :
 *    le logo EduNova reste celui de la plateforme et n'apparaît jamais sur un document d'établissement ;
 *  - le logo n'est retourné que si son fichier existe réellement (jamais d'image cassée) ;
 *  - le nom est le nom d'affichage de l'établissement, sinon son nom officiel (jamais « EduNova » comme nom d'école) ;
 *  - l'établissement est toujours celui de la requête courante (session / résolveur / défaut) : aucune donnée d'une autre école.
 */
final class IdentiteDocument
{
    private const NOM_PLATEFORME = 'EduNova';

    /** Identité de l'établissement de la requête courante. @return array{nom:string, adresse:?string, telephone:?string, email:?string, logo:?string, logo_src:?string} */
    public static function courante(): array
    {
        return self::pourBranding(BrandingService::forCurrentRequest());
    }

    /** Identité d'un établissement précis. @return array{nom:string, adresse:?string, telephone:?string, email:?string, logo:?string, logo_src:?string} */
    public static function pour(int $etablissementId): array
    {
        return self::pourBranding(BrandingService::make()->get($etablissementId));
    }

    /**
     * @return array{nom:string, adresse:?string, telephone:?string, email:?string, logo:?string, logo_src:?string}
     *         logo = chemin racine-relatif stocké ; logo_src = URL prête pour <img src> (BASE_URL inclus), ou null
     */
    public static function pourBranding(BrandingData $branding): array
    {
        $nom = trim($branding->appName);
        if ($nom === '' || strcasecmp($nom, self::NOM_PLATEFORME) === 0) {
            $nom = self::nomOfficiel($branding->etablissementId) ?? $nom;
        }

        $logo = $branding->logoPourDocuments();
        $src = null;
        if ($logo !== null) {
            if (preg_match('#^(https?:)?//#i', $logo)) {
                $src = $logo;
            } elseif (is_file(ROOT_PATH . '/public' . $logo)) {
                $src = (defined('BASE_URL') ? BASE_URL : '') . $logo;
            }
        }

        return [
            'nom'       => $nom,
            'adresse'   => $branding->contactAddress,
            'telephone' => $branding->contactPhone,
            'email'     => $branding->contactEmail,
            'logo'      => $logo,
            'logo_src'  => $src,
        ];
    }

    private static function nomOfficiel(int $etablissementId): ?string
    {
        try {
            $s = Database::getInstance()->getConnection()->prepare('SELECT nom FROM etablissements WHERE id = ?');
            $s->execute([$etablissementId]);
            $nom = $s->fetchColumn();
            return $nom !== false && trim((string)$nom) !== '' ? (string)$nom : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
