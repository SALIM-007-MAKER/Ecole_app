<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Middleware;
use Core\Request;

/**
 * Résout le tenant courant et alimente TenantContext, ou interrompt la
 * requête avec le code HTTP approprié. Voir MULTI_TENANT_V2_BLUEPRINT.md §6.2
 * et §6.3 (ordre du pipeline : Cors → TenantResolution → Auth → RateLimit → ...).
 *
 * NON ACTIVÉ EN PHASE 14.2 : cette classe est un composant fondation prêt à
 * l'emploi, mais elle n'est appelée depuis aucun point du bootstrap
 * (core/Application.php) ni d'aucune route. L'activer maintenant casserait
 * l'application entière : les tables V1 (users, classes, eleves, ...) ne
 * portent pas encore etablissement_id (Phase 14.3) et aucun établissement
 * de démo n'est résolvable en mode subdomain/path en environnement local.
 * Point d'intégration prévu : Core\Application::run(), juste après
 * `$request = new Request();` et avant le chargement des routes.
 */
final class TenantMiddleware implements Middleware
{
    public function __construct(
        private readonly ?TenantResolver $resolver = null,
        private readonly ?Request $request = null,
    ) {
    }

    public function handle(): void
    {
        $resolver = $this->resolver ?? new TenantResolver(new TenantRepository(), require ROOT_PATH . '/config/tenant.php');
        $request  = $this->request ?? new Request();

        $etablissement = $resolver->resolve($request);

        if ($etablissement === null) {
            http_response_code(404);
            throw TenantException::notFound($request->getUri());
        }

        if ($etablissement->isSuspended()) {
            http_response_code(503);
            throw TenantException::suspended($etablissement->slug);
        }

        if ($etablissement->isCancelled() || $etablissement->isPlanExpired()) {
            http_response_code(402);
            throw TenantException::planExpired($etablissement->slug);
        }

        TenantContext::set($etablissement->id, $etablissement);
    }
}
