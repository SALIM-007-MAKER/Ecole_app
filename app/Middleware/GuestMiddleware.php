<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Session;

class GuestMiddleware implements Middleware
{
    public function handle(): void
    {
        if (Session::isLogged()) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }
}
