<?php

namespace Core;

class Router
{
    private array $routes = [];
    private array $namedRoutes = [];
    private string $basePath = '';

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, string $handler, ?string $name = null): static
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, string $handler, ?string $name = null): static
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, string $handler, ?string $name = null): static
    {
        return $this->addRoute('PUT', $path, $handler, $name);
    }

    public function patch(string $path, string $handler, ?string $name = null): static
    {
        return $this->addRoute('PATCH', $path, $handler, $name);
    }

    public function delete(string $path, string $handler, ?string $name = null): static
    {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }

    private function addRoute(string $method, string $path, string $handler, ?string $name): static
    {
        $pattern = $this->pathToRegex($path);
        $this->routes[] = compact('method', 'path', 'pattern', 'handler');

        if ($name !== null) {
            $this->namedRoutes[$name] = $path;
        }

        return $this;
    }

    private function pathToRegex(string $path): string
    {
        // Convertir {param} en groupe de capture nommé
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#u';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri    = $this->stripBasePath($request->getUri());

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        $this->handleNotFound();
    }

    private function callHandler(string $handler, array $params): void
    {
        [$controllerName, $method] = explode('@', $handler, 2);

        // Support des sous-namespaces (Api\EleveApiController)
        $controllerName = str_replace('/', '\\', $controllerName);

        // Handler déjà pleinement qualifié (ex: Controller::class . '@method', qui
        // produit directement 'App\Modules\Api\Controllers\V1\HealthController' —
        // convention utilisée par app/Modules/Api/routes.php) : utiliser tel quel,
        // sans re-préfixer. Sans cette garde, 'App\Modules\' était concaténé une
        // seconde fois ('App\Modules\App\Modules\...'), rendant TOUTES les routes
        // de l'API Platform (122 routes, Phase 13.x) introuvables (404) — trouvé
        // et corrigé Phase 15.1 (RC1), voir RELEASE_CANDIDATE_RC1_REPORT.md.
        if (str_starts_with($controllerName, 'App\\')) {
            $class = $controllerName;
        } else {
            // Les handlers de modules V2 contiennent un backslash (ex: Finance\Controllers\FraisController)
            // Les handlers V1 sont de simples noms de classe (ex: ComptabiliteController) — INF-C-002
            $class = str_contains($controllerName, '\\')
                ? 'App\\Modules\\' . $controllerName
                : 'App\\Controllers\\' . $controllerName;
        }

        if (!class_exists($class)) {
            Logger::error("Contrôleur introuvable : {$class}");
            $this->handleNotFound();
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            Logger::error("Méthode introuvable : {$class}::{$method}");
            $this->handleNotFound();
            return;
        }

        call_user_func_array([$controller, $method], $params);
    }

    private function stripBasePath(string $uri): string
    {
        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }
        return '/' . ltrim($uri, '/');
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        $view = new View();
        $view->render('errors/404', [], 'main');
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route nommée '{$name}' introuvable.");
        }

        $url = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }

        return $this->basePath . $url;
    }
}
