<?php
declare(strict_types=1);

namespace App\Shared\Api;

/**
 * Contexte statique de la requête API courante (request_id, timing).
 */
class ApiRequestContext
{
    private static ?string $requestId = null;
    private static float   $startTime = 0.0;

    public static function init(): void
    {
        self::$requestId = sprintf('%04x%04x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffffffffffff)
        );
        self::$startTime = microtime(true);
    }

    public static function getRequestId(): ?string
    {
        return self::$requestId;
    }

    public static function getDurationMs(): int
    {
        return (int)((microtime(true) - self::$startTime) * 1000);
    }

    public static function getStartTime(): float
    {
        return self::$startTime;
    }
}
