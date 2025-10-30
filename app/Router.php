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

        // Handle dynamic /avis/{token} route
        if (preg_match('/^avis\/([a-f0-9]{64})$/', $uri, $matches)) {
            $token = $matches[1];
            // Expose global variables for the controller
            if (isset($GLOBALS['db']))       { $db = $GLOBALS['db']; }
            if (isset($GLOBALS['authUser'])) { $authUser = $GLOBALS['authUser']; }
            require_once BASE_PATH . '/app/Controllers/_ReviewsController.php';
            $controller = new \App\Controllers\ReviewsController();
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $controller->submit($token);
            } else {
                $controller->showForm($token);
            }
            return;
        }

// Handle POST /profil/voyages/{id}/valider
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && preg_match('/^profil\/voyages\/(\d+)\/valider$/', $uri, $m)) {

    $voyageId = (int)$m[1];

    // Récup contexte si exposé en global
    if (isset($GLOBALS['db']))       { $db = $GLOBALS['db']; }
    if (isset($GLOBALS['authUser'])) { $authUser = $GLOBALS['authUser']; }
    $uid = (int)($authUser['id'] ?? 0);

    require_once BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();

    // Déclenche la machine à états (démarrer/arrivee). En cas d'arrivée,
    // profile_voyage_accept crée les tokens et envoie les emails via App\Services\Mailer
    profile_voyage_accept($db, $voyageId, $uid);

    header('Location: ' . url('profil'));
    return;
}

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
