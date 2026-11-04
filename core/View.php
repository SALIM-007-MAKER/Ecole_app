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
        // Le paramètre s'appelle $data : sans ce détour, EXTR_SKIP ignorait la clé 'data' des variables de la vue
        // (la variable existait déjà) et la vue recevait tout le tableau au lieu de la liste attendue.
        $__vars = $data;
        unset($data);
        extract($__vars, EXTR_SKIP);
        unset($__vars);

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
            // 'none' renvoie TOUJOURS le contenu brut, jamais enveloppé dans
            // main.php — que la requête soit AJAX ou une navigation normale.
            // Chaque vue qui passe 'none' (bulletins/imprimer, errors/403,
            // errors/404, home/index...) fournit déjà son propre document
            // HTML complet (<!DOCTYPE html><html>...). L'envelopper en plus
            // dans main.php imbriquait deux documents HTML l'un dans l'autre :
            // la barre latérale/le profil/le bandeau d'installation PWA du
            // tableau de bord restaient visibles (et imprimables/exportables
            // en PDF) par-dessus la page censée être autonome, en plus de
            // faire perdre le <title> propre de la vue (écrasé par celui de
            // main.php).
            echo $content;
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
        $__vars = $data;
        unset($data);
        extract($__vars, EXTR_SKIP);
        unset($__vars);
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
