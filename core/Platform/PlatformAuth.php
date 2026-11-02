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

    /** Inactivité maximale d'une session opérateur (plus stricte que la session d'établissement). */
    public const IDLE_SECONDS = 1800;
    /** Durée de vie absolue d'une session opérateur, quelle que soit l'activité. */
    public const ABSOLUTE_SECONDS = 28800;

    /** @param array{id:int, email:string, nom:string, niveau:string, must_change?:bool} $operator  id = platform_operators.id */
    public static function login(array $operator): void
    {
        Session::regenerate();   // anti-fixation : nouvel identifiant à la connexion opérateur
        $operator['_at']   = time();
        $operator['_seen'] = time();
        Session::set(self::SESSION_KEY, $operator);
    }

    public static function logout(): void
    {
        Session::delete(self::SESSION_KEY);
    }

    public static function isLogged(): bool
    {
        return self::current() !== null;
    }

    /**
     * Opérateur connecté, ou null. Une session inactive depuis IDLE_SECONDS ou ouverte depuis plus de ABSOLUTE_SECONDS est fermée ici.
     *
     * @return array{id:int, email:string, nom:string, niveau:string, must_change?:bool}|null
     */
    public static function current(): ?array
    {
        $op = Session::get(self::SESSION_KEY);
        if (!is_array($op)) {
            return null;
        }
        $now = time();
        $ouverte = (int)($op['_at'] ?? $now);
        $vue     = (int)($op['_seen'] ?? $now);
        if ($now - $vue > self::IDLE_SECONDS || $now - $ouverte > self::ABSOLUTE_SECONDS) {
            Session::delete(self::SESSION_KEY);
            Session::flash('error', 'Votre session opérateur a expiré. Veuillez vous reconnecter.');
            return null;
        }
        if ($now - $vue >= 30) {            // rafraîchit l'activité sans réécrire la session à chaque appel
            $op['_seen'] = $now;
            Session::set(self::SESSION_KEY, $op);
        }
        return $op;
    }

    public static function hasLevel(string ...$levels): bool
    {
        $op = self::current();
        return $op !== null && in_array($op['niveau'], $levels, true);
    }

    /** Vrai tant que l'opérateur doit changer son mot de passe (premier mot de passe généré, ou réinitialisation de secours). */
    public static function mustChangePassword(): bool
    {
        return !empty(self::current()['must_change']);
    }

    public static function passwordChanged(): void
    {
        $op = Session::get(self::SESSION_KEY);
        if (is_array($op)) {
            $op['must_change'] = false;
            Session::set(self::SESSION_KEY, $op);
        }
    }
}
