<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Résolution et mise à jour du branding par établissement.
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §9, §10.
 *
 * Isolation stricte : chaque méthode reçoit l'établissement EXPLICITEMENT
 * en paramètre (jamais de dépendance implicite à un état global partagé
 * entre requêtes) — même discipline que TenantAuthContext (Phase 14.4),
 * pour la même raison : ne jamais risquer de servir le branding d'un
 * tenant à un autre par erreur d'état partagé.
 *
 * Cache (Phase 14.9) : TenantCache (catégorie 'branding'), persistant
 * inter-requêtes — remplace la mémoïsation statique en mémoire de la Phase
 * 14.5 (qui ne survivait pas d'une requête à l'autre en l'absence de
 * Redis/APCu dans cet environnement). Isolation stricte par construction :
 * TenantCache préfixe systématiquement chaque clé par l'établissement,
 * save() invalide explicitement (forgetCategory) la catégorie 'branding' de
 * CE seul établissement.
 */
final class BrandingService
{
    private readonly TenantCache $cache;

    public function __construct(
        private readonly PDO $pdo,
        ?TenantCache $cache = null,
    ) {
        $this->cache = $cache ?? TenantCache::make();
    }

    /**
     * Résout le branding applicable à la requête HTTP courante, pour les
     * vues qui n'ont pas encore de session utilisateur (écran de connexion
     * et pages publiques). Ordre de résolution :
     *   1. Session utilisateur déjà connectée (etablissement_id)
     *   2. TenantResolver — sous-domaine / chemin / header (lecture seule,
     *      n'active AUCUN middleware global, cf. Phase 14.2 TenantMiddleware
     *      toujours dormant)
     *   3. Établissement par défaut (config/tenant.php) — comportement
     *      identique à avant Phase 14.5 tant qu'un seul établissement existe
     *
     * Centralise cette résolution pour que `layouts/auth.php` et
     * `auth/login.php` (qui calculaient chacun indépendamment leur propre
     * chemin de logo avant cette phase) partagent EXACTEMENT la même
     * logique, sans jamais mélanger le branding de deux établissements.
     */
    public static function forCurrentRequest(): BrandingData
    {
        $service = self::make();
        $config  = require ROOT_PATH . '/config/tenant.php';
        $defaultId = (int)($config['default_id'] ?? 1);

        $sessionUser = \Core\Session::getUser();
        if (isset($sessionUser['etablissement_id']) && $sessionUser['etablissement_id'] !== null) {
            return $service->get((int)$sessionUser['etablissement_id']);
        }

        try {
            $resolver = new TenantResolver(new TenantRepository(), $config);
            $etab = $resolver->resolve(new \Core\Request());
            if ($etab !== null) {
                return $service->get($etab->id);
            }
        } catch (\Throwable) {
            // Résolution indisponible → repli sur l'établissement par défaut
        }

        return $service->get($defaultId);
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    public function get(int $etablissementId): BrandingData
    {
        $cached = $this->cache->get($etablissementId, 'branding', 'data');
        if (is_array($cached)) {
            return $this->hydrate($cached);
        }

        try {
            $row = $this->fetchBrandingRow($etablissementId);
            $settings = $this->fetchSettings($etablissementId);
        } catch (\Throwable) {
            // Table absente ou erreur DB → repli sur les valeurs par défaut,
            // jamais sur celles d'un autre établissement. Non mis en cache
            // (erreur transitoire probable — on retente à la requête suivante).
            return BrandingData::defaults($etablissementId);
        }

        $defaults = BrandingData::defaults($etablissementId);

        $data = new BrandingData(
            etablissementId: $etablissementId,
            logoUrl: $row['logo_url'] ?? $defaults->logoUrl,
            logoDarkUrl: $row['logo_dark_url'] ?? $defaults->logoDarkUrl,
            faviconUrl: $row['favicon_url'] ?? $defaults->faviconUrl,
            primaryColor: $row['primary_color'] ?? $defaults->primaryColor,
            secondaryColor: $row['secondary_color'] ?? $defaults->secondaryColor,
            appName: $row['app_name'] ?? $defaults->appName,
            welcomeMessage: $row['welcome_message'] ?? $defaults->welcomeMessage,
            fontFamily: $settings['font_family'] ?? $defaults->fontFamily,
            themeMode: $settings['theme_mode'] ?? $defaults->themeMode,
            loginImageUrl: $this->nullIfEmpty($settings['login_image_url'] ?? null),
            contactPhone: $this->nullIfEmpty($settings['contact_phone'] ?? null),
            contactEmail: $this->nullIfEmpty($settings['contact_email'] ?? null),
            contactAddress: $this->nullIfEmpty($settings['contact_address'] ?? null),
            footerText: $settings['footer_text'] ?? $defaults->footerText,
            showBreadcrumbs: isset($settings['show_breadcrumbs']) ? (bool)(int)$settings['show_breadcrumbs'] : $defaults->showBreadcrumbs,
        );

        $config = require ROOT_PATH . '/config/cache.php';
        $this->cache->put($etablissementId, 'branding', 'data', (array)$data, (int)($config['ttl']['branding'] ?? 3600));

        return $data;
    }

    private function hydrate(array $row): BrandingData
    {
        return new BrandingData(
            etablissementId: (int)$row['etablissementId'],
            logoUrl: $row['logoUrl'],
            logoDarkUrl: $row['logoDarkUrl'],
            faviconUrl: $row['faviconUrl'],
            primaryColor: $row['primaryColor'],
            secondaryColor: $row['secondaryColor'],
            appName: $row['appName'],
            welcomeMessage: $row['welcomeMessage'],
            fontFamily: $row['fontFamily'],
            themeMode: $row['themeMode'],
            loginImageUrl: $row['loginImageUrl'],
            contactPhone: $row['contactPhone'],
            contactEmail: $row['contactEmail'],
            contactAddress: $row['contactAddress'],
            footerText: $row['footerText'],
            showBreadcrumbs: (bool)$row['showBreadcrumbs'],
        );
    }

    /**
     * Met à jour le branding d'un établissement (cache dénormalisé +
     * paramètres étendus) et invalide le cache mémoire local.
     *
     * @param array{
     *   logo_url?:?string, logo_dark_url?:?string, favicon_url?:?string,
     *   primary_color?:string, secondary_color?:string, app_name?:string,
     *   welcome_message?:?string, font_family?:string, theme_mode?:string,
     *   login_image_url?:?string, contact_phone?:?string, contact_email?:?string,
     *   contact_address?:?string, footer_text?:?string, show_breadcrumbs?:bool
     * } $fields
     */
    public function save(int $etablissementId, array $fields): void
    {
        $current = $this->get($etablissementId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO etablissement_branding
                (etablissement_id, logo_url, logo_dark_url, favicon_url, primary_color, secondary_color, app_name, welcome_message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                logo_url = VALUES(logo_url), logo_dark_url = VALUES(logo_dark_url),
                favicon_url = VALUES(favicon_url), primary_color = VALUES(primary_color),
                secondary_color = VALUES(secondary_color), app_name = VALUES(app_name),
                welcome_message = VALUES(welcome_message)"
        );
        $stmt->execute([
            $etablissementId,
            $fields['logo_url'] ?? $current->logoUrl,
            $fields['logo_dark_url'] ?? $current->logoDarkUrl,
            $fields['favicon_url'] ?? $current->faviconUrl,
            $this->validColor($fields['primary_color'] ?? null) ?? $current->primaryColor,
            $this->validColor($fields['secondary_color'] ?? null) ?? $current->secondaryColor,
            $fields['app_name'] ?? $current->appName,
            $fields['welcome_message'] ?? $current->welcomeMessage,
        ]);

        $settingsMap = [
            'font_family'      => $fields['font_family']      ?? $current->fontFamily,
            'theme_mode'       => $fields['theme_mode']        ?? $current->themeMode,
            'login_image_url'  => $fields['login_image_url']   ?? $current->loginImageUrl ?? '',
            'contact_phone'    => $fields['contact_phone']     ?? $current->contactPhone ?? '',
            'contact_email'    => $fields['contact_email']     ?? $current->contactEmail ?? '',
            'contact_address'  => $fields['contact_address']   ?? $current->contactAddress ?? '',
            'footer_text'      => $fields['footer_text']       ?? $current->footerText,
            'show_breadcrumbs' => isset($fields['show_breadcrumbs']) ? (string)(int)$fields['show_breadcrumbs'] : (string)(int)$current->showBreadcrumbs,
        ];

        $stmtSet = $this->pdo->prepare(
            "INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type)
             VALUES (?, 'branding', ?, ?, 'string')
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
        );
        foreach ($settingsMap as $cle => $valeur) {
            $stmtSet->execute([$etablissementId, $cle, $valeur]);
        }

        $this->cache->forgetCategory($etablissementId, 'branding');
    }

    /** Réservé aux tests — invalide le cache branding de CE seul établissement (jamais les autres). */
    public function clearCacheFor(int $etablissementId): void
    {
        $this->cache->forgetCategory($etablissementId, 'branding');
    }

    /** Réservé aux tests — repartir d'un cache totalement vide (tous établissements) entre deux scénarios. */
    public static function clearCache(): void
    {
        TenantCache::make()->flushAllForTests();
    }

    private function fetchBrandingRow(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM etablissement_branding WHERE etablissement_id = ?");
        $stmt->execute([$etablissementId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private function fetchSettings(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cle, valeur FROM etab_settings WHERE etablissement_id = ? AND section = 'branding'"
        );
        $stmt->execute([$etablissementId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['cle']] = $row['valeur'];
        }
        return $out;
    }

    private function nullIfEmpty(?string $v): ?string
    {
        return ($v === null || $v === '') ? null : $v;
    }

    private function validColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : null;
    }
}
