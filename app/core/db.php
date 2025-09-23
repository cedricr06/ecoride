<?php
/**
 * Connexion PDO (singleton simple).
 * ⚠️ REMPLIS les identifiants ($host, $dbName, $user, $pass).
 */

<?php
/**
 * Connexion PDO (singleton simple).
 * ⚠️ REMPLIS les identifiants ($host, $dbName, $user, $pass).
 */

function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (!function_exists('envv')) {
        function envv(string $k, $def='') { return $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $def; }
    }

    // 1) On lit d'abord les variables d'environnement (Railway)
    $host = envv('DB_HOST');
    $port = envv('DB_PORT');
    $db   = envv('DB_NAME');
    $user = envv('DB_USER');
    $pass = envv('DB_PASS');

    // 2) Fallback pour ton local si l'env n'est pas défini
    if (!$host || !$port || !$db || !$user) {
        $host = 'localhost';
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
        error_log('[DB] '.$e->getMessage());
        http_response_code(500);
        exit('Erreur de connexion à la base de données.');
    }

    return $pdo;
}
<?php
/**
 * Connexion PDO (singleton simple).
 * ⚠️ REMPLIS les identifiants ($host, $dbName, $user, $pass).
 */

function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (!function_exists('envv')) {
        function envv(string $k, $def='') { return $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $def; }
    }

    // 1) On lit d'abord les variables d'environnement (Railway)
    $host = envv('DB_HOST');
    $port = envv('DB_PORT');
    $db   = envv('DB_NAME');
    $user = envv('DB_USER');
    $pass = envv('DB_PASS');

    // 2) Fallback pour ton local si l'env n'est pas défini
    if (!$host || !$port || !$db || !$user) {
        $host = 'localhost';
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
        error_log('[DB] '.$e->getMessage());
        http_response_code(500);
        exit('Erreur de connexion à la base de données.');
    }

    return $pdo;
}
