<?php

namespace Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require ROOT_PATH . '/config/app.php';

        $basePath = parse_url($config['url'] ?? '/', PHP_URL_PATH) ?: '/';

        session_name($config['session']['name']);
        session_set_cookie_params([
            'lifetime' => 0,          // Cookie de session (supprimé à la fermeture du navigateur)
            'path'     => $basePath,  // Limité au répertoire de l'app
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
        self::$started = true;

        // Régénérer l'ID périodiquement pour prévenir la fixation
        if (!isset($_SESSION['_last_regen'])) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        } elseif (time() - $_SESSION['_last_regen'] > 300) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flush(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    // Flash messages (affichés une seule fois)
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($_SESSION['_flash']) || empty($_SESSION['_flash'])) {
            return $default;
        }

        // If no key provided, return the first flash message as ['type' => key, 'message' => value]
        if ($key === null) {
            $firstKey = array_key_first($_SESSION['_flash']);
            $value = $_SESSION['_flash'][$firstKey] ?? $default;
            unset($_SESSION['_flash'][$firstKey]);
            if ($value === $default) {
                return $default;
            }
            return ['type' => (string)$firstKey, 'message' => $value];
        }

        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    // Token CSRF
    public static function getCsrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }

    // Utilisateur connecté
    public static function setUser(array $user): void
    {
        self::set('_auth_user', $user);
    }

    public static function getUser(): ?array
    {
        return self::get('_auth_user');
    }

    public static function isLogged(): bool
    {
        return self::has('_auth_user');
    }

    public static function logout(): void
    {
        self::delete('_auth_user');
        session_regenerate_id(true);
    }
}
