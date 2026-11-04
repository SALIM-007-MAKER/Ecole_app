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
        Session::delete(self::MFA_KEY);
    }

    // ── Double authentification : état « mot de passe validé, second facteur attendu » (aucun accès tant qu'il n'est pas levé) ──

    private const MFA_KEY = '_platform_mfa_pending';
    /** Délai accordé pour saisir le second facteur après le mot de passe. */
    public const MFA_TTL = 300;
    /** Essais de second facteur avant d'exiger de nouveau le mot de passe. */
    public const MFA_MAX_ESSAIS = 5;

    /** @param array<string,mixed> $operator opérateur dont le mot de passe vient d'être validé */
    public static function beginMfa(array $operator): void
    {
        Session::regenerate();
        Session::set(self::MFA_KEY, ['op' => $operator, 'at' => time(), 'essais' => 0]);
    }

    /** @return ?array<string,mixed> opérateur en attente de second facteur (null si absent ou expiré) */
    public static function pendingMfa(): ?array
    {
        $p = Session::get(self::MFA_KEY);
        if (!is_array($p) || !isset($p['op']) || time() - (int)($p['at'] ?? 0) > self::MFA_TTL) {
            Session::delete(self::MFA_KEY);
            return null;
        }
        return $p['op'];
    }

    /** Enregistre un échec ; au-delà de MFA_MAX_ESSAIS l'état est supprimé (le mot de passe est à ressaisir). Renvoie les essais restants. */
    public static function mfaFailure(): int
    {
        $p = Session::get(self::MFA_KEY);
        if (!is_array($p)) {
            return 0;
        }
        $p['essais'] = (int)($p['essais'] ?? 0) + 1;
        if ($p['essais'] >= self::MFA_MAX_ESSAIS) {
            Session::delete(self::MFA_KEY);
            return 0;
        }
        Session::set(self::MFA_KEY, $p);
        return self::MFA_MAX_ESSAIS - $p['essais'];
    }

    /** Second facteur validé : l'opérateur est connecté (nouvel identifiant de session). */
    public static function completeMfa(string $methode): void
    {
        $op = self::pendingMfa();
        Session::delete(self::MFA_KEY);
        if ($op !== null) {
            self::login($op + ['mfa' => $methode]);
        }
    }

    /** Vrai tant que l'opérateur doit activer sa double authentification (obligatoire pour la plateforme et pas encore activée). */
    public static function mustSetupTwoFactor(): bool
    {
        return !empty(self::current()['must_2fa']);
    }

    public static function twoFactorSetupDone(): void
    {
        $op = Session::get(self::SESSION_KEY);
        if (is_array($op)) {
            $op['must_2fa'] = false;
            $op['mfa'] = 'totp';
            Session::set(self::SESSION_KEY, $op);
        }
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
