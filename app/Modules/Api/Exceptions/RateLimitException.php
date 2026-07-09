<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class RateLimitException extends ApiException
{
    public function __construct(public readonly int $retryAfter = 60)
    {
        parent::__construct('Quota de requêtes dépassé.', 'rate_limit_exceeded', 429);
    }
}
