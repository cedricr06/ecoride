<?php
function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $env = fn($k,$d='') => $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $d;

    // 1) Railway peut fournir directement MYSQL_URL (mysql://user:pass@host:port/db)
    $url = $env('MYSQL_URL');
    if ($url) {
        $p = parse_url($url);
        $host = $p['host'] ?? '';
        $port = (string)($p['port'] ?? '');
        $db   = ltrim($p['path'] ?? '', '/');
        $user = $p['user'] ?? '';
        $pass = $p['pass'] ?? '';
    } else {
        // 2) Lis d'abord TES variables MYSQL*, sinon les DB_* si tu en as
        $host = $env('MYSQLHOST') ?: $env('DB_HOST');
        $port = $env('MYSQLPORT') ?: $env('DB_PORT');
        $db   = $env('MYSQLDATABASE') ?: $env('DB_NAME');
        $user = $env('MYSQLUSER') ?: $env('DB_USER');
        $pass = $env('MYSQLPASSWORD') ?: $env('DB_PASS');
    }

    // 3) Fallback local si rien (pour dev)
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
    catch (Throwable $e) {
        error_log('[DB] '.$e->getMessage());
        http_response_code(500);
        exit('Erreur de connexion à la base de données.');
    }
    return $pdo;
}
