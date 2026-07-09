<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Session;
use Core\View;

class PermissionMiddleware implements Middleware
{
    private string $permission;

    public function __construct(string $permission)
    {
        $this->permission = $permission;
    }

    public function handle(): void
    {
        (new AuthMiddleware())->handle();

        $user = Session::getUser();
        if (!in_array($this->permission, $user['permissions'] ?? [], true)) {
            http_response_code(403);
            (new View())->render('errors/403', ['title' => 'Permission insuffisante'], 'main');
            exit;
        }
    }
}
