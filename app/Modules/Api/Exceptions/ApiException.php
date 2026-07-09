<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'api_error',
        public readonly int    $httpStatus = 500,
        public readonly array  $details = [],
    ) {
        parent::__construct($message);
    }
}
