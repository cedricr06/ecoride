<?php
/**
 * Connexion PDO (singleton simple).
 * ⚠️ REMPLIS les identifiants ($host, $dbName, $user, $pass).
 */

if (!function_exists('db')) {
    function db() {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        // ← ADAPTE ICI À TON ENVIRONNEMENT
        $host    = 'localhost';
        $dbName  = 'ecoride';
        $user    = 'root';
        $pass    = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
        $opts = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );

        try {
            $pdo = new PDO($dsn, $user, $pass, $opts);
        } catch (Exception $e) {
            error_log('[DB] ' . $e->getMessage());
            http_response_code(500);
            exit('Erreur de connexion à la base de données.');
        }

        return $pdo;
    }
}