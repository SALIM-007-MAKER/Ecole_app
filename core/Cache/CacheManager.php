<?php

declare(strict_types=1);

namespace Core\Cache;

final class CacheManager
{
    private static ?CacheInterface $instance = null;

    public static function driver(): CacheInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require ROOT_PATH . '/config/cache.php';

        self::$instance = match ($config['driver']) {
            'redis' => new RedisCache((string)$config['redis']['host'], (int)$config['redis']['port']),
            default => new FileCache((string)$config['file']['dir']),
        };

        return self::$instance;
    }

    /** Réinitialise l'instance mémorisée — utilisé par les tests pour injecter un répertoire différent. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /** Permet aux tests d'injecter un driver précis sans passer par config/cache.php. */
    public static function setDriver(CacheInterface $driver): void
    {
        self::$instance = $driver;
    }
}
