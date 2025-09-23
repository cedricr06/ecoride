<?php

/** 
 * Connexion PDO
 * 
 */

function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (!function_exists('envv')) {
        function envv(string $k, $def = '')
        {
            return $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $def;
        }
    }

    // 1) PROD (Railway) via env
    $host = envv('DB_HOST');
    $port = envv('DB_PORT');
    $db   = envv('DB_NAME');
    $user = envv('DB_USER');
    $pass = envv('DB_PASS');

    // 2) Fallback LOCAL si pas d'env
    if (!$host || !$port || !$db || !$user) {
        $host = '127.0.0.1';
        $port = '3306';
        $db   = 'ecoride';
        $user = 'root';
        $pass = '';
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $opts);
    } catch (Throwable $e) {
        error_log('[DB] ' . $e->getMessage());
        http_response_code(500);
        exit('Erreur de connexion à la base de données.');
    }

    return $pdo;
}
