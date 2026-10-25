<?php

namespace Core;

class Application
{
    private static ?Application $instance = null;
    private Router $router;
    private array $config;

    private function __construct()
    {
        $this->config = require ROOT_PATH . '/config/app.php';
        $this->bootstrap();
        $this->router = new Router($this->getBasePath());
    }

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    private function bootstrap(): void
    {
        // Timezone
        date_default_timezone_set($this->config['timezone']);

        // Gestion des erreurs selon l'environnement
        if ($this->config['debug']) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(0);
            set_error_handler([$this, 'handleError']);
            set_exception_handler([$this, 'handleException']);
        }

        // Démarrer la session
        Session::start();

        // Définir BASE_URL si pas encore défini
        if (!defined('BASE_URL')) {
            define('BASE_URL', $this->config['url']);
        }
    }

    private function getBasePath(): string
    {
        // Extraire le sous-répertoire depuis APP_URL (ex: /ecole_app)
        $path = parse_url($this->config['url'], PHP_URL_PATH);
        return rtrim($path ?? '', '/');
    }

    public function run(): void
    {
        $request = new Request();

        // Lot 3 : établissement courant déduit de la SESSION authentifiée (revérifié en base), avant toute route.
        $this->appliquerContexteEtablissement();

        // Charger les routes V1
        $router = $this->router;
        require ROOT_PATH . '/config/routes.php';

        // Charger les routes des modules V2 activés (INF-C-001)
        $modules = require ROOT_PATH . '/config/modules.php';
        foreach ($modules as $config) {
            if ($config['enabled'] && file_exists($config['routes'])) {
                require $config['routes'];
            }
        }

        // Enregistrer les listeners d'événements
        $events = require ROOT_PATH . '/config/events.php';
        foreach ($events as $eventClass => $listeners) {
            foreach ($listeners as $listener) {
                EventDispatcher::listen($eventClass, $listener);
            }
        }

        $this->router->dispatch($request);
    }

    /**
     * Positionne TenantContext depuis l'utilisateur connecté (voir Core\Tenant\SessionTenantGuard). Un contexte absent ou
     * invalide n'est JAMAIS remplacé par un établissement par défaut : la session est invalidée et l'utilisateur renvoyé à la
     * connexion. Visiteurs et routes /platform/* (PlatformAuth, règle distincte) ne sont pas concernés.
     */
    private function appliquerContexteEtablissement(): void
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if (Tenant\SessionTenantGuard::routeSansContexte($uri, $this->getBasePath())) {
            return;
        }
        $user = Session::getUser();
        if ($user === null) {
            return;
        }

        $decision = (new Tenant\SessionTenantGuard(Database::getInstance()->getConnection()))->evaluer($user);
        if ($decision['ok']) {
            Tenant\TenantContext::set((int)$decision['etablissement_id'], $decision['etablissement']);
            return;
        }

        Logger::security(
            'TENANT_CONTEXT_REFUSED',
            sprintf('raison=%s user_id=%d etablissement_id=%s uri=%s', $decision['raison'], (int)($user['id'] ?? 0),
                (string)($user['etablissement_id'] ?? 'null'), (string)parse_url($uri, PHP_URL_PATH))
        );
        Session::delete('_auth_user');
        Session::flash('error', 'Votre session n\'est plus valide pour cet établissement. Veuillez vous reconnecter.');
        http_response_code(302);
        header('Location: ' . rtrim((string)$this->config['url'], '/') . '/login');
        exit;
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $ctx = sprintf('[%s %s]', $_SERVER['REQUEST_METHOD'] ?? 'CLI', $_SERVER['REQUEST_URI'] ?? 'n/a');
        Logger::error("PHP Error [{$errno}] {$ctx}: {$errstr} in {$errfile}:{$errline}");
        return true;
    }

    public function handleException(\Throwable $e): void
    {
        $ctx = sprintf('[%s %s]', $_SERVER['REQUEST_METHOD'] ?? 'CLI', $_SERVER['REQUEST_URI'] ?? 'n/a');
        Logger::critical(sprintf(
            '%s %s — %s in %s:%d | Trace: %s',
            $ctx,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            str_replace("\n", ' | ', $e->getTraceAsString())
        ));
        http_response_code(500);
        $view = new View();
        $view->render('errors/500', [
            'message' => $e->getMessage(),
            'debug'   => $this->config['debug'],
        ], 'main');
    }

    private function __clone() {}
}
