<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;

/**
 * Contrôle de santé pour l'exploitation (supervision, vérification après déploiement ou retour arrière).
 * Public, sans session, sans donnée sensible : état global et accessibilité de la base seulement.
 */
class HealthController extends Controller
{
    public function index(): void
    {
        $dbOk = false;
        try {
            $dbOk = (int)Database::getInstance()->getConnection()->query('SELECT 1')->fetchColumn() === 1;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        http_response_code($dbOk ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['status' => $dbOk ? 'healthy' : 'unhealthy', 'database' => $dbOk ? 'ok' : 'erreur'], JSON_UNESCAPED_SLASHES);
    }
}