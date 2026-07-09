<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Request;

/**
 * Détermine l'établissement (tenant) courant depuis la requête HTTP.
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §6.1 et §6.2.
 *
 * Ordre de résolution : Session → Sous-domaine → Domaine personnalisé →
 * Chemin URL (/s/{slug}/...) → Header X-Tenant-Slug.
 *
 * La résolution par claim JWT (priorité 1 dans le blueprint) est un no-op
 * pour l'instant : elle dépend du LoginController multi-tenant qui est du
 * ressort de la Phase 14.6 (hors périmètre 14.2).
 *
 * NOTE (Phase 14.2) : ce résolveur est un composant fondation, testable en
 * isolation. Il n'est appelé par aucun code de production tant que
 * TenantMiddleware n'est pas activé dans le pipeline HTTP (Phase 14.3+).
 */
final class TenantResolver
{
    public function __construct(
        private readonly TenantRepository $repo,
        private readonly array $config = [],
    ) {
    }

    public function resolve(Request $request): ?Etablissement
    {
        // 1. JWT claim 'etab' — hors périmètre Phase 14.2, volontairement no-op.

        // 2. Session '__etab_slug'
        $sessionSlug = $this->sessionSlug();
        if ($sessionSlug !== null) {
            $etab = $this->repo->findBySlug($sessionSlug);
            if ($etab !== null) {
                return $etab;
            }
        }

        $host = $this->currentHost();

        // 3. Sous-domaine
        $subdomainSlug = self::extractSubdomainSlug($host, $this->config['base_domain'] ?? null);
        if ($subdomainSlug !== null) {
            $etab = $this->repo->findBySlug($subdomainSlug);
            if ($etab !== null) {
                return $etab;
            }
        }

        // 4. Domaine personnalisé
        if ($host !== null) {
            $etab = $this->repo->findByDomain($host);
            if ($etab !== null) {
                return $etab;
            }
        }

        // 5. Chemin URL /s/{slug}/...
        $pathSlug = self::extractPathSlug($request->getUri());
        if ($pathSlug !== null) {
            $etab = $this->repo->findBySlug($pathSlug);
            if ($etab !== null) {
                return $etab;
            }
        }

        // 6. Header X-Tenant-Slug (API serveur-à-serveur)
        $headerSlug = self::extractHeaderSlug();
        if ($headerSlug !== null) {
            $etab = $this->repo->findBySlug($headerSlug);
            if ($etab !== null) {
                return $etab;
            }
        }

        return null;
    }

    /**
     * Extrait le slug depuis un sous-domaine, ex :
     * 'lycee-ibn-badis.scolaris.app' + baseDomain 'scolaris.app' → 'lycee-ibn-badis'.
     * Retourne null si le host ne correspond pas à baseDomain, si aucun
     * baseDomain n'est configuré, ou si le sous-domaine est 'www'/'api'.
     */
    public static function extractSubdomainSlug(?string $host, ?string $baseDomain): ?string
    {
        if ($host === null || $baseDomain === null || $baseDomain === '') {
            return null;
        }

        $host = strtolower(explode(':', $host, 2)[0]); // retire le port éventuel
        $baseDomain = strtolower($baseDomain);

        $suffix = '.' . $baseDomain;
        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));
        if ($slug === '' || str_contains($slug, '.')) {
            // Pas de sous-domaine, ou sous-domaine à plusieurs niveaux (non supporté)
            return null;
        }

        if (in_array($slug, ['www', 'api', 'admin', 'platform'], true)) {
            return null;
        }

        return $slug;
    }

    /**
     * Extrait le slug depuis un chemin de la forme /s/{slug}/... ou /s/{slug}.
     */
    public static function extractPathSlug(string $uri): ?string
    {
        if (preg_match('#/s/([a-z0-9][a-z0-9\-]{0,58}[a-z0-9]|[a-z0-9])(?:/|$)#i', $uri, $m) === 1) {
            return strtolower($m[1]);
        }
        return null;
    }

    /**
     * Extrait le slug depuis le header HTTP X-Tenant-Slug.
     */
    public static function extractHeaderSlug(): ?string
    {
        $value = $_SERVER['HTTP_X_TENANT_SLUG'] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        return $value !== '' ? $value : null;
    }

    private function sessionSlug(): ?string
    {
        $value = $_SESSION['__etab_slug'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Retourne le Host courant UNIQUEMENT s'il a un format d'hôte valide
     * (défense en profondeur contre les attaques Host Header — Phase 14.7,
     * blueprint §"Sécurité") : un Host malformé, trop long, ou contenant
     * des caractères illégaux est rejeté ICI, avant même d'atteindre une
     * requête SQL ou un lookup DNS. Sans effet sur la sécurité de la
     * résolution elle-même (déjà saine : correspondance exacte contre une
     * liste blanche en base, jamais de confiance générique dans le Host) —
     * ce filtre élimine simplement le bruit/les tentatives grossières le
     * plus tôt possible.
     */
    private function currentHost(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
        if (!is_string($host) || $host === '') {
            return null;
        }

        $hostOnly = strtolower(explode(':', $host, 2)[0]);

        // Autorise aussi localhost / IP (environnements de dev), en plus
        // des noms d'hôte RFC 1123 complets (production).
        $isLocal = $hostOnly === 'localhost' || filter_var($hostOnly, FILTER_VALIDATE_IP) !== false;
        $isRfc1123 = strlen($hostOnly) <= 253
            && preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*\.[a-z]{2,63}$/', $hostOnly) === 1;

        if (!$isLocal && !$isRfc1123) {
            return null;
        }

        return $host;
    }
}
