<?php
declare(strict_types=1);

namespace App\Modules\Api\Webhooks;

final class WebhookSigner
{
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $secret, string $signature): bool
    {
        $expected = self::sign($payload, $secret);
        return hash_equals($expected, $signature);
    }
}
