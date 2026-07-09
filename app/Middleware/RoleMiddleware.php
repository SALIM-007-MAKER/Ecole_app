<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Session;
use Core\View;

class RoleMiddleware implements Middleware
{
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(): void
    {
        (new AuthMiddleware())->handle();

        $user = Session::getUser();
        if (!in_array($user['role'] ?? '', $this->roles, true)) {
            http_response_code(403);
            (new View())->render('errors/403', ['title' => 'Accès refusé'], 'main');
            exit;
        }
    }
}
