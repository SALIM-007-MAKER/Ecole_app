<?php

namespace Core;

abstract class Controller
{
    protected View $view;
    protected Request $request;

    public function __construct()
    {
        $this->view    = new View();
        $this->request = new Request();
        $this->sendSecurityHeaders();
    }

    private function sendSecurityHeaders(): void
    {
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdn.tailwindcss.com fonts.googleapis.com; img-src 'self' data:; font-src 'self' cdn.jsdelivr.net fonts.gstatic.com data:");
        }
    }

    protected function safe(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function currentAnnee(): string
    {
        $m = (int)date('m');
        $y = (int)date('Y');
        return $m >= 9 ? "$y-" . ($y + 1) : ($y - 1) . "-$y";
    }

    /**
     * Rendre une vue avec layout
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $this->view->render($view, $data, $layout);
    }

    /**
     * Répondre en JSON (pour PWA/API)
     */
    protected function json(mixed $data, int $status = 200): void
    {
        View::json($data, $status);
    }

    /**
     * Redirection HTTP.
     *
     * Un chemin relatif à la racine (ex: '/dashboard') est automatiquement
     * préfixé par BASE_URL (ex: 'http://localhost/ecole_app') — l'app étant
     * servie depuis un sous-dossier, un Location: '/dashboard' brut renvoie
     * le navigateur vers localhost/dashboard au lieu de localhost/ecole_app/dashboard.
     * Les URLs déjà absolues (http://, https://, //) ou vides passent inchangées.
     */
    protected function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $this->absoluteUrl($url), true, $status);
        exit;
    }

    /**
     * Résout un chemin en URL absolue sous BASE_URL si nécessaire.
     */
    protected function absoluteUrl(string $url): string
    {
        if ($url === '' || preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, BASE_URL)) {
            return $url;
        }
        if ($url[0] === '/') {
            return rtrim(BASE_URL, '/') . $url;
        }
        return $url;
    }

    /**
     * Retour en arrière (referer)
     */
    protected function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    protected function requireAuth(): void
    {
        if (!Session::isLogged()) {
            Session::flash('error', 'Vous devez être connecté pour accéder à cette page.');
            $this->redirect(BASE_URL . '/login');
        }
    }

    /**
     * Vérifier le rôle de l'utilisateur
     */
    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        $user = Session::getUser();
        if (!in_array($user['role'] ?? '', $roles, true)) {
            http_response_code(403);
            $this->render('errors/403', [], 'none');
            exit;
        }
    }

    /**
     * Valider le token CSRF — redirige en arrière avec un message d'erreur si invalide.
     * Un token manquant/expiré est traité comme une session expirée, pas une attaque silencieuse.
     */
    protected function verifyCsrf(): void
    {
        $token = $this->request->post('_csrf_token', '');
        if (!Session::verifyCsrf($token)) {
            Logger::security('CSRF_INVALID', 'Token rejeté sur ' . $this->request->getUri());
            Session::flash('error', 'Votre session a expiré ou le formulaire est invalide. Veuillez réessayer.');
            $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/dashboard');
            $this->redirect($referer);
        }
    }

    /**
     * Retourner l'utilisateur connecté depuis la session
     */
    protected function currentUser(): ?array
    {
        return Session::getUser();
    }

    /**
     * Vérifier si l'utilisateur possède une permission
     */
    protected function can(string $permission): bool
    {
        $user = Session::getUser();
        return $user !== null && in_array($permission, $user['permissions'] ?? [], true);
    }

    /**
     * Interrompre si la permission est absente
     */
    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        if (!$this->can($permission)) {
            Logger::security('ACCESS_DENIED', "Permission requise : {$permission}");
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Permission insuffisante'], 'none');
            exit;
        }
    }

    /**
     * Validation simple des données
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && (empty($value) && $value !== '0')) {
                    $errors[$field][] = "Le champ {$field} est obligatoire.";
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (strlen((string)$value) < $min) {
                        $errors[$field][] = "Le champ {$field} doit contenir au moins {$min} caractères.";
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (strlen((string)$value) > $max) {
                        $errors[$field][] = "Le champ {$field} ne doit pas dépasser {$max} caractères.";
                    }
                } elseif ($rule === 'email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "Le champ {$field} doit être un email valide.";
                    }
                } elseif ($rule === 'numeric') {
                    if (!is_numeric($value)) {
                        $errors[$field][] = "Le champ {$field} doit être un nombre.";
                    }
                }
            }
        }

        return $errors;
    }
}
