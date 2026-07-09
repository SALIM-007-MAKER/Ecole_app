<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

class PermissionException extends ApiException
{
    public function __construct(string $permission = '')
    {
        $msg = $permission ? "Permission requise : $permission" : 'Accès refusé.';
        parent::__construct($msg, 'permission_denied', 403);
    }
}
