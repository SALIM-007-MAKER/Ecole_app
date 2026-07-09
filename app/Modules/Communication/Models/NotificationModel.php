<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

class NotificationModel
{
    const TYPES     = ['info', 'success', 'warning', 'alert', 'message', 'system'];
    const PRIORITES = ['basse', 'normale', 'haute', 'critique'];

    public static function iconePourType(string $type): string
    {
        return match ($type) {
            'success'  => 'check-circle',
            'warning'  => 'alert-triangle',
            'alert'    => 'alert-circle',
            'message'  => 'message-circle',
            'system'   => 'settings',
            default    => 'bell',
        };
    }

    public static function couleurPourPriorite(string $priorite): string
    {
        return match ($priorite) {
            'critique' => 'red',
            'haute'    => 'orange',
            'basse'    => 'gray',
            default    => 'blue',
        };
    }

    public static function isExpired(?string $expireAt): bool
    {
        if ($expireAt === null) {
            return false;
        }
        return strtotime($expireAt) < time();
    }
}
