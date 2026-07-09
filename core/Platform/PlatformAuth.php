<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Session;

/**
 * Authentification des opérateurs de la plateforme (SaaS Super-Admin) —
 * Phase 14.10, MULTI_TENANT_V2_BLUEPRINT.md §4.5 (`platform_operators`).
 *
 * ISOLATION STRICTE PAR CONSTRUCTION : cette classe est le SEUL point
 * d'entrée de l'état "opérateur connecté" — elle stocke sous une clé de
 * session totalement distincte (`_platform_operator`) de celle utilisée par
 * l'authentification établissement (`Core\Session::setUser()` → `_auth_user`).
 * Aucun contrôleur Platform n'appelle jamais `Session::getUser()`/
 * `Controller::requirePermission()`/`can()` (RBAC établissement) — et
 * réciproquement, aucun contrôleur établissement ne lit `_platform_operator`.
 * Un même utilisateur peut être un `user` connecté à son école ET un
 * opérateur plateforme dans le même navigateur sans que les deux états ne
 * se mélangent jamais : deux clés de session indépendantes, deux modèles de
 * permission indépendants (etab_role_permissions vs platform_operators.niveau).
 *
 * Réutilise Core\Session pour les mécanismes génériques (démarrage de
 * session, CSRF, flash) — ceux-ci ne sont pas spécifiques à l'établissement
 * (voir Core\Session::getCsrfToken()/verifyCsrf(), qui n'inspectent jamais
 * `_auth_user`).
 */
final class PlatformAuth
{
    private const SESSION_KEY = '_platform_operator';

    /** @param array{id:int, user_id:int, email:string, nom:string, niveau:string} $operator */
    public static function login(array $operator): void
    {
        Session::set(self::SESSION_KEY, $operator);
    }

    public static function logout(): void
    {
        Session::delete(self::SESSION_KEY);
    }

    public static function isLogged(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /** @return array{id:int, user_id:int, email:string, nom:string, niveau:string}|null */
    public static function current(): ?array
    {
        return Session::get(self::SESSION_KEY);
    }

    public static function hasLevel(string ...$levels): bool
    {
        $op = self::current();
        return $op !== null && in_array($op['niveau'], $levels, true);
    }
}
