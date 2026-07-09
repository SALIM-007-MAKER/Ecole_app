<?php

namespace Core;

class Logger
{
    private static string $logPath = '';

    private static function getPath(): string
    {
        if (self::$logPath === '') {
            self::$logPath = ROOT_PATH . '/storage/logs/';
        }
        return self::$logPath;
    }

    private static function write(string $level, string $message): void
    {
        $date  = date('Y-m-d');
        $time  = date('H:i:s');
        $file  = self::getPath() . "app-{$date}.log";
        $entry = "[{$time}] [{$level}] {$message}" . PHP_EOL;

        if (!is_dir(self::getPath())) {
            mkdir(self::getPath(), 0755, true);
        }

        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    private static function context(): string
    {
        $ip     = $_SERVER['REMOTE_ADDR']   ?? 'cli';
        $method = $_SERVER['REQUEST_METHOD'] ?? '-';
        $uri    = $_SERVER['REQUEST_URI']    ?? '-';
        return "[{$ip}] [{$method} {$uri}]";
    }

    public static function info(string $message): void
    {
        self::write('INFO', self::context() . ' ' . $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', self::context() . ' ' . $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', self::context() . ' ' . $message);
    }

    public static function critical(string $message): void
    {
        self::write('CRITICAL', self::context() . ' ' . $message);
    }

    public static function debug(string $message): void
    {
        $config = require ROOT_PATH . '/config/app.php';
        if ($config['debug']) {
            self::write('DEBUG', self::context() . ' ' . $message);
        }
    }

    /**
     * Log d'un événement de sécurité (accès refusé, CSRF, tentatives suspectes, etc.)
     * Écrit dans un fichier security-YYYY-MM-DD.log séparé.
     */
    public static function security(string $event, string $detail = ''): void
    {
        $ctx  = self::context();
        $user = 'guest';
        if (class_exists(Session::class, false) && Session::isLogged()) {
            $u    = Session::getUser();
            $user = ($u['email'] ?? $u['nom'] ?? 'unknown') . '/' . ($u['role'] ?? '?');
        }
        $message = "[USER:{$user}] {$ctx} {$event}" . ($detail !== '' ? " — {$detail}" : '');

        $date  = date('Y-m-d');
        $time  = date('H:i:s');
        $file  = self::getPath() . "security-{$date}.log";

        if (!is_dir(self::getPath())) {
            mkdir(self::getPath(), 0755, true);
        }

        file_put_contents($file, "[{$time}] [SECURITY] {$message}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
