<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class ConflictException extends ApiException
{
    public function __construct(string $message = 'Conflit de données.', string $code = 'conflict')
    {
        parent::__construct($message, $code, 409);
    }
}
