<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use App\Services\ReviewToken;



class ReviewsController
{
    private PDO $db;
    private ?array $authUser;

    public function __construct()
    {
        // Access global $db and $authUser
        if (!isset($GLOBALS['db'])) {
            throw new \RuntimeException("Database connection not available.");
        }
        $this->db = $GLOBALS['db'];
        $this->authUser = $GLOBALS['authUser'] ?? null;

        // Ensure necessary services are loaded
        require_once BASE_PATH . '/app/Services/ReviewToken.php';
        require_once BASE_PATH . '/app/core/db.php'; // For user_fetch, if not autoloaded
        require_once BASE_PATH . '/app/core/security.php'; // For csrf_field, verify_csrf
    }

    /**
     * Helper to get MongoDB client.
     */
    private function mongo(): Client
    {
        $uri = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
        return new Client($uri, [], ['typeMap' => ['array' => 'array', 'document' => 'array', 'root' => 'array']]);
    }

    public function showForm(string $token): void
    {
        $context = ReviewToken::getContext($this->db, $token);

        if (!$context) {
            http_response_code(410); // Gone
            echo "<h1>Lien invalide ou expiré</h1>";
            echo "<p>Ce lien d'avis est invalide, a déjà été utilisé ou a expiré.</p>";
            return;
        }

        $driverId = (int)$context['driver_id'];
        $driver = user_fetch($this->db, $driverId); // Assuming user_fetch is available

        // CSRF token for the form
        $csrfToken = function_exists('csrf_field') ? csrf_field() : '';

        // Pass data to the view
        $data = [
            'driver' => $driver,
            'token' => $token,
            'csrf_field' => $csrfToken,
        ];

        // Render the view
        extract($data); // Make $driver, $token, $csrf_field available in the view
        require_once BASE_PATH . '/app/Views/pages/avis_form.php';
    }

    public function submit(string $token): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405); // Method Not Allowed
            echo "<h1>Méthode non autorisée</h1>";
            return;
        }

        if (function_exists('verify_csrf')) {
            try {
                verify_csrf();
            } catch (\RuntimeException $e) {
                http_response_code(403); // Forbidden
                echo "<h1>Erreur CSRF</h1>";
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                return;
            }
        }

        $context = ReviewToken::getContext($this->db, $token);

        if (!$context) {
            http_response_code(410); // Gone
            echo "<h1>Lien invalide ou expiré</h1>";
            echo "<p>Ce lien d'avis est invalide, a déjà été utilisé ou a expiré.</p>";
            return;
        }

        $note = (int)($_POST['note'] ?? 0);
        $commentaire = trim((string)($_POST['commentaire'] ?? ''));

        // Validate note (1-5)
        if ($note < 1 || $note > 5) {
            // In a real app, you'd redirect back with an error flash message
            http_response_code(400); // Bad Request
            echo "<h1>Note invalide</h1>";
            echo "<p>La note doit être comprise entre 1 et 5.</p>";
            return;
        }

        // Validate comment length (max 1000 characters)
        if (mb_strlen($commentaire) > 1000) {
            $commentaire = mb_substr($commentaire, 0, 1000);
        }

        try {
            $mongoClient = $this->mongo();
            $dbName = getenv('MONGODB_DB') ?: 'ecoride';
            $collection = $mongoClient->selectCollection($dbName, 'avis');

            $document = [
                'driver_id'    => (int)$context['driver_id'],
                'rider_id'     => (int)$context['rider_id'],
                'voyage_id'    => (int)$context['voyage_id'],
                'note'         => $note,
                'commentaire'  => $commentaire,
                'source'       => 'email_link',
                'created_at'   => new UTCDateTime(),
            ];

            $collection->insertOne($document);

            // Mark token as used in MySQL
            ReviewToken::markUsed($this->db, (int)$context['id']);

            // Success page or redirect
            echo "<h1>Merci pour votre avis !</h1>";
            echo "<p>Votre avis a été enregistré avec succès.</p>";
            // Optionally redirect to a confirmation page or home
            // header('Location: ' . BASE_URL . '/avis/confirmation');
            // exit;

        } catch (\Exception $e) {
            error_log("Error submitting review: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo "<h1>Erreur lors de l'enregistrement de votre avis</h1>";
            echo "<p>Une erreur est survenue. Veuillez réessayer plus tard.</p>";
        }
    }
}
