<?php

/**
 * Configuration Multi-Tenant — Phases 14.2 (Infrastructure) & 14.3 (Tables métier)
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md.
 *
 * IMPORTANT : 'enabled' reste à false tant que la Phase 14.4 (RBAC
 * multi-tenant + activation du TenantMiddleware) n'est pas validée GO.
 * Ce flag ne fait rien pour l'instant, il documente l'intention pour la
 * phase suivante qui l'utilisera lors de l'activation réelle du pipeline.
 *
 * 'default_id' : tant que TenantMiddleware n'est pas activé, TenantContext
 * n'est JAMAIS positionné par le pipeline HTTP réel. Les modèles Phase 14.3
 * (tenantScoped=true) utilisent alors cette valeur de repli pour continuer
 * à lire/écrire exactement comme avant (l'application reste mono-tenant en
 * pratique : un seul établissement — 'edunova-demo', id=1 — existe
 * aujourd'hui). Zéro changement de comportement observable tant qu'un
 * deuxième établissement n'existe pas et que le contexte n'est pas activé.
 */
return [
    'enabled'     => filter_var($_ENV['TENANT_MODE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),

    // Établissement de repli utilisé par Core\Model quand un modèle est
    // tenant-scopé mais qu'aucun TenantContext n'est positionné.
    'default_id'  => (int)($_ENV['TENANT_DEFAULT_ID'] ?? 1),

    // Domaine de base pour la résolution par sous-domaine :
    // {slug}.{base_domain} — ex: lycee-ibn-badis.scolaris.app
    'base_domain' => $_ENV['TENANT_BASE_DOMAIN'] ?? null,

    // Stratégies de résolution actives, par ordre de priorité décroissante.
    // (documentaire pour l'instant — TenantResolver essaie toujours les 5
    // stratégies non-JWT dans l'ordre du blueprint §6.2)
    'strategies'  => ['session', 'subdomain', 'custom_domain', 'path', 'header'],
];
