<?php
declare(strict_types=1);

namespace App\Shared\Api;

use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Exceptions\RateLimitException;
use App\Modules\Api\Repositories\RateLimitRepository;

class RateLimiter
{
    private array $config;

    public function __construct(private readonly RateLimitRepository $repo)
    {
        $this->config = require ROOT_PATH . '/config/api.php';
    }

    /**
     * Vérifie et consomme un token de rate limit.
     * Lève RateLimitException si le quota est dépassé.
     * Injecte les headers X-RateLimit-* dans la réponse.
     */
    public function check(string $group, ?AuthContext $ctx, string $ip): void
    {
        [$limit, $window] = $this->getLimitForGroup($group, $ctx);
        $bucketKey = $this->buildKey($group, $ctx, $ip);

        $result = $this->repo->consume($bucketKey, $limit, $window);

        $resetTimestamp = strtotime($result['reset_at'] ?? 'now +60 seconds') ?: (time() + $window);

        if (!headers_sent()) {
            header("X-RateLimit-Limit: $limit");
            header("X-RateLimit-Remaining: " . max(0, $result['remaining']));
            header("X-RateLimit-Reset: $resetTimestamp");
        }

        if (!$result['allowed']) {
            $retryAfter = max(0, $resetTimestamp - time());
            throw new RateLimitException($retryAfter);
        }
    }

    private function getLimitForGroup(string $group, ?AuthContext $ctx): array
    {
        if ($group === 'auth') {
            $cfg = $this->config['rate_limit']['auth'];
        } elseif ($group === 'upload') {
            $cfg = $this->config['rate_limit']['upload'];
        } elseif ($ctx?->authMethod === 'api_key') {
            $cfg = $this->config['rate_limit']['api_key_read'];
        } else {
            $cfg = $this->config['rate_limit']['default'];
        }

        return [$cfg['requests'], $cfg['window']];
    }

    private function buildKey(string $group, ?AuthContext $ctx, string $ip): string
    {
        $actor = $ctx !== null ? "u{$ctx->userId}" : "ip_$ip";
        return hash('sha256', "$group:$actor");
    }
}
