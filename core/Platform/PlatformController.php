<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Controller;
use Core\Session;

/**
 * Base des contrôleurs du portail Super-Admin SaaS (Phase 14.10).
 * Garde d'accès dédiée (PlatformAuth) — jamais requirePermission()/can()
 * (RBAC établissement) hérités de Core\Controller, volontairement inutilisés
 * ici pour garantir l'isolation totale des deux modèles de permission.
 */
abstract class PlatformController extends Controller
{
    protected function requirePlatformAuth(): void
    {
        if (!PlatformAuth::isLogged()) {
            Session::flash('error', 'Veuillez vous connecter au portail plateforme.');
            $this->redirect(BASE_URL . '/platform/login');
        }
    }

    /** Interrompt si l'opérateur connecté n'a pas l'un des niveaux requis (ex: 'admin','super_admin'). */
    protected function requirePlatformLevel(string ...$levels): void
    {
        $this->requirePlatformAuth();
        if (!PlatformAuth::hasLevel(...$levels)) {
            \Core\Logger::security('PLATFORM_ACCESS_DENIED', 'Niveau requis : ' . implode('|', $levels));
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Niveau opérateur insuffisant'], 'none');
            exit;
        }
    }
}
