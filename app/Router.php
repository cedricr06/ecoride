<?php

class Router
{
    private array $routes = [];

    /**
     * Mappe une URL "propre" vers un fichier handler (vue ou route).
     * Exemple: $router->add('/profil', BASE_PATH . '/app/Views/pages/profil.php');
     */
    public function add(string $url, $handler): void
    {
        // On normalise les URL: sans trailing slash, sans leading slash (style de ton index)
        $url = trim($url, '/');
        $this->routes[$url] = $handler;
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
    }

    public function dispatch(): void
    {
        // Ton routeur utilise ?url=... → on garde ce comportement
        $uri = $_GET['url'] ?? '';
        $uri = trim($uri, '/');

        if (!array_key_exists($uri, $this->routes)) {
            $this->handleNotFound();
            return;
        }

        $handler = $this->routes[$uri];

        // 1) Si on t’a passé un callable (au cas où), on le gère.
        if (is_callable($handler)) {
            // Expose les variables globales dont les vues/handlers ont besoin
            if (isset($GLOBALS['db']))       { $db = $GLOBALS['db']; }
            if (isset($GLOBALS['authUser'])) { $authUser = $GLOBALS['authUser']; }
            $handler(); // exécute la closure
            return;
        }

        // 2) Sinon on s’attend à un chemin de fichier
        $handlerPath = (string) $handler;
        if (!file_exists($handlerPath)) {
            http_response_code(500);
            echo "<h1>Erreur 500 - Fichier de la route introuvable</h1>";
            echo "<p>Le fichier <code>" . htmlspecialchars($handlerPath) . "</code> pour la route <code>/" . htmlspecialchars($uri) . "</code> est introuvable.</p>";
            return;
        }

        // Expose $db / $authUser AVANT d'inclure la vue/route (solution B)
        if (isset($GLOBALS['db']))       { $db = $GLOBALS['db']; }
        if (isset($GLOBALS['authUser'])) { $authUser = $GLOBALS['authUser']; }

        require $handlerPath; // la vue (ex: profil.php) a maintenant $db et $authUser
    }
}
