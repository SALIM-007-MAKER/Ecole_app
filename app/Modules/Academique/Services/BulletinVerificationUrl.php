<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

/**
 * URL absolue encodée dans le QR code d'un bulletin OFFICIEL.
 *
 * Construite à l'impression à partir de la configuration (APP_URL) et du jeton de la colonne — jamais figée dans
 * le JSON du bulletin : un changement de domaine ne rend pas les bulletins à venir inutilisables, et une URL
 * relative ou locale ne peut plus finir imprimée sur un document officiel.
 *
 * Règles : schéma http(s), hôte présent, ni identifiants, ni paramètres, ni fragment dans la base. En production
 * (APP_ENV=production), HTTPS est obligatoire et l'hôte ne peut pas être local (localhost, 127.x, ::1, *.local).
 */
final class BulletinVerificationUrl
{
    public const PATH = '/v2/academique/bulletins/verify/';

    /**
     * @param string $token  jeton complet (64 hexadécimaux minuscules)
     * @param string $appUrl valeur de APP_URL
     * @param string $env    valeur de APP_ENV
     * @throws \InvalidArgumentException si l'URL de base est inutilisable pour un document officiel
     */
    public static function build(string $token, string $appUrl, string $env = 'production'): string
    {
        if (!BulletinTokenService::isWellFormed($token)) {
            throw new \InvalidArgumentException('Jeton de vérification mal formé.');
        }
        $appUrl = trim($appUrl);
        $parts = parse_url($appUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('APP_URL doit être une URL http(s) absolue, sans identifiants, paramètres ni fragment.');
        }

        if (strtolower($env) === 'production') {
            $host = strtolower($parts['host']);
            if (strtolower($parts['scheme']) !== 'https') {
                throw new \InvalidArgumentException('En production, APP_URL doit utiliser HTTPS pour un QR officiel.');
            }
            if (self::isLocalHost($host)) {
                throw new \InvalidArgumentException('En production, APP_URL ne peut pas pointer vers un hôte local.');
            }
        }

        return rtrim($appUrl, '/') . self::PATH . $token;
    }

    private static function isLocalHost(string $host): bool
    {
        $host = trim($host, '[]');
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || $host === '::1') {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }
        return false;
    }
}
