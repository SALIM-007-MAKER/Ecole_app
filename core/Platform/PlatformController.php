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
 *
 * Étape 1 SaaS : l'identité de l'opérateur est celle de `platform_operators` (aucun lien avec `users`). Un refus d'accès par niveau est
 * inscrit dans le journal d'audit global. Tant que l'opérateur doit changer son mot de passe, seul le contrôleur de compte est accessible.
 */
abstract class PlatformController extends Controller
{
    /** Vrai uniquement pour la page de changement de mot de passe (sinon l'opérateur y est redirigé tant qu'il doit le changer). */
    protected bool $accessibleAvecMotDePasseAChanger = false;
    /** Vrai pour les pages permettant d'activer la double authentification (accessibles tant qu'elle est obligatoire et pas encore activée). */
    protected bool $accessibleAvecDeuxFacteursARegler = false;

    protected function requirePlatformAuth(): void
    {
        if (!PlatformAuth::isLogged()) {
            Session::flash('error', 'Veuillez vous connecter au portail plateforme.');
            $this->redirect(BASE_URL . '/platform/login');
        }
        if (PlatformAuth::mustChangePassword() && !$this->accessibleAvecMotDePasseAChanger) {
            Session::flash('error', 'Vous devez choisir un nouveau mot de passe avant de continuer.');
            $this->redirect(BASE_URL . '/platform/compte/mot-de-passe');
        }
        if (PlatformAuth::mustSetupTwoFactor() && !$this->accessibleAvecDeuxFacteursARegler) {
            Session::flash('error', 'La double authentification est obligatoire : activez-la pour continuer.');
            $this->redirect(BASE_URL . '/platform/compte/2fa');
        }
    }

    /** Limitation des tentatives (dossier surchargeable par PLATFORM_THROTTLE_DIR, réservé aux tests). */
    protected function throttle(): \App\Shared\Http\LoginThrottle
    {
        $dir = getenv('PLATFORM_THROTTLE_DIR');
        return new \App\Shared\Http\LoginThrottle($dir !== false && $dir !== '' ? $dir : ROOT_PATH . '/storage/cache/throttle');
    }

    /** Interrompt si l'opérateur connecté n'a pas l'un des niveaux requis (ex: 'admin','super_admin'). */
    protected function requirePlatformLevel(string ...$levels): void
    {
        $this->requirePlatformAuth();
        if (!PlatformAuth::hasLevel(...$levels)) {
            \Core\Logger::security('PLATFORM_ACCESS_DENIED', 'Niveau requis : ' . implode('|', $levels));
            $this->audit('access.denied', ['resultat' => 'denied', 'apres' => ['uri' => (string)($_SERVER['REQUEST_URI'] ?? ''), 'niveaux_requis' => $levels]]);
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Niveau opérateur insuffisant'], 'none');
            exit;
        }
    }

    /** Identifiant de l'opérateur connecté (platform_operators.id), 0 si aucun. */
    protected function operatorId(): int
    {
        return (int)(PlatformAuth::current()['id'] ?? 0);
    }

    /** Écrit dans le journal d'audit global. Renvoie false si l'écriture a échoué (repli sur le fichier de secours, avertissement affiché). */
    protected function audit(string $action, array $options = []): bool
    {
        return PlatformAuditService::make()->record($action, $options);
    }
}
