<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Session;

class AuthMiddleware implements Middleware
{
    public function handle(): void
    {
        if (!Session::isLogged()) {
            Session::flash('error', 'Vous devez être connecté pour accéder à cette page.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }
}
