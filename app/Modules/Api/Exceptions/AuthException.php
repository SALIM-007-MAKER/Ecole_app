<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class AuthException extends ApiException
{
    public function __construct(string $message = 'Authentification requise.', string $code = 'auth_required')
    {
        parent::__construct($message, $code, 401);
    }
}
