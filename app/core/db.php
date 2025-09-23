<?php
/**
 * Connexion PDO unique, compatible Railway.
 * Priorité :
 *   1) MYSQL_URL (mysql://user:pass@host:port/db)
 *   2) Variables MYSQL* (MYSQLHOST, MYSQLPORT, MYSQLDATABASE, MYSQLUSER, MYSQLPASSWORD)
 *   3) Fallback local (127.0.0.1:3306 / ecoride / root / '')
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $env = fn(string $k, string $d = '') => $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $d;

    // 1) Railway peut fournir un MYSQL_URL direct
    $url = $env('MYSQL_URL');
    if ($url) {
        $p    = parse_url($url);
        $host = $p['host'] ?? '';
        $port = isset($p['port']) ? (string)$p['port'] : '';
        $db   = isset($p['path']) ? ltrim($p['path'], '/') : '';
        $user = $p['user'] ?? '';
        $pass = $p['pass'] ?? '';
    } else {
        // 2) Sinon, lire les variables MYSQL* exposées par Railway
        $host = $env('MYSQLHOST');
        $port = $env('MYSQLPORT');
        $db   = $env('MYSQLDATABASE');
        $user = $env('MYSQLUSER');
        $pass = $env('MYSQLPASSWORD');
    }

    // 3) Fallback LOCAL si rien n’est défini
    if (!$host || !$port || !$db || !$user) {
        $host = '127.0.0.1';
        $port = '3306';
        $db   = 'ecoride';
        $user = 'root';
        $pass = '';
    }

    // DSN TCP (pas de socket), charset explicite
    $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
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
