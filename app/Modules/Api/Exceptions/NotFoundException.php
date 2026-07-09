<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(string $resource = 'Ressource')
    {
        parent::__construct("$resource introuvable.", 'resource_not_found', 404);
    }
}
