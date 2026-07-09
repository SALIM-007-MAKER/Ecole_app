<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(array $errors, string $message = 'Les données fournies sont invalides.')
    {
        parent::__construct($message, 'validation_failed', 422, $errors);
    }
}
