<?php

namespace Core;

class View
{
    private string $viewPath;
    private string $layoutPath;

    public function __construct()
    {
        $this->viewPath   = ROOT_PATH . '/app/Views/';
        $this->layoutPath = ROOT_PATH . '/app/Views/layouts/';
    }

    /**
     * Rendre une vue dans un layout
     *
     * @param string $view    Chemin relatif de la vue (ex: 'home/index')
     * @param array  $data    Variables à passer à la vue
     * @param string $layout  Layout à utiliser ('main', 'auth', 'none')
     */
    public function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);

        // Capturer le contenu de la vue
        ob_start();
        if (str_contains($view, '::')) {
            [$module, $path] = explode('::', $view, 2);
            $viewFile = ROOT_PATH . '/app/Modules/' . $module . '/Views/' . $path . '.php';
        } else {
            $viewFile = $this->viewPath . $view . '.php';
        }

        if (!file_exists($viewFile)) {
            ob_end_clean();
            throw new \RuntimeException("Vue introuvable : {$viewFile}");
        }

        include $viewFile;
        $content = ob_get_clean();

        if ($layout === 'none') {
            // If this is an AJAX or JSON request, return raw content as intended.
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
            if ($isAjax || $acceptsJson) {
                echo $content;
                return;
            }

            // For standard HTML requests, wrap content inside the main layout so
            // pages using 'none' still inherit the dashboard/design system.
            $layoutFile = $this->layoutPath . 'main.php';
            if (!file_exists($layoutFile)) {
                // Fallback to raw content if main layout is missing.
                echo $content;
                return;
            }

            include $layoutFile;
            return;
        }

        $layoutFile = $this->layoutPath . $layout . '.php';

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout introuvable : {$layoutFile}");
        }

        include $layoutFile;
    }

    /**
     * Rendre une vue partielle (sans layout)
     */
    public function partial(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        $viewFile = $this->viewPath . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Partielle introuvable : {$viewFile}");
        }

        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    /**
     * Réponse JSON (pour les endpoints API/PWA)
     */
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
