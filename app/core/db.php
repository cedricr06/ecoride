<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $env = fn($k,$d='') => $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $d;

    // 1) URL publique (proxy Railway) -> la plus fiable
    $url = $env('MYSQL_PUBLIC_URL');
    if (!$url) $url = $env('MYSQL_URL');

    if ($url) {
        $p    = parse_url($url);                        // mysql://user:pass@host:port/db
        $host = $p['host'] ?? '';
        $port = isset($p['port']) ? (string)$p['port'] : '';
        $db   = isset($p['path']) ? ltrim($p['path'], '/') : '';
        $user = $p['user'] ?? '';
        $pass = $p['pass'] ?? '';
    } else {
        // 2) Vars MYSQL* classiques
        $host = $env('MYSQLHOST');
        $port = $env('MYSQLPORT');
        $db   = $env('MYSQLDATABASE');
        $user = $env('MYSQLUSER');
        $pass = $env('MYSQLPASSWORD');
    }

    // 3) Fallback local (dev)
    if (!$host || !$port || !$db || !$user) {
        $host = '127.0.0.1'; $port = '3306';
        $db   = 'ecoride';   $user = 'root'; $pass = '';
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try { $pdo = new PDO($dsn, $user, $pass, $opts); }
    catch (Throwable $e) { error_log('[DB] '.$e->getMessage()); http_response_code(500); exit('Erreur de connexion à la base de données.'); }
    return $pdo;
}
