<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class BusinessRuleException extends ApiException
{
    public function __construct(string $message, string $code = 'business_rule_violated')
    {
        parent::__construct($message, $code, 422);
    }
}
