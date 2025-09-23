<?php
function db(): PDO {
  static $pdo=null; if ($pdo instanceof PDO) return $pdo;
  $env = fn($k,$d='') => $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $d;

  // 0) Tes variables DB_* (ecoride)
  $host=$env('DB_HOST'); $port=$env('DB_PORT'); $db=$env('DB_NAME');
  $user=$env('DB_USER'); $pass=$env('DB_PASS');

  // 1) Sinon URL publique / interne
  if(!$host||!$port||!$db||!$user){
    $url=$env('MYSQL_PUBLIC_URL') ?: $env('MYSQL_URL');
    if($url){
      $p=parse_url($url);
      $host=$host ?: ($p['host']??'');
      $port=$port ?: (isset($p['port'])?(string)$p['port']:'');
      $db  =$db   ?: (isset($p['path'])?ltrim($p['path'],'/'):'');
      $user=$user ?: ($p['user']??'');
      $pass=$pass ?: ($p['pass']??'');
    }
  }

  // 2) Sinon variables MYSQL_*
  if(!$host||!$port||!$db||!$user){
    $host=$host ?: $env('MYSQLHOST');
    $port=$port ?: $env('MYSQLPORT');
    $db  =$db   ?: $env('MYSQLDATABASE');
    $user=$user ?: $env('MYSQLUSER');
    $pass=$pass ?: $env('MYSQLPASSWORD');
  }

  // 3) Fallback local
  if(!$host||!$port||!$db||!$user){
    $host='127.0.0.1'; $port='3306'; $db='ecoride'; $user='root'; $pass='';
  }

  $dsn="mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
  $opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES=>false];

  try { return $pdo=new PDO($dsn,$user,$pass,$opts); }
  catch(Throwable $e){ error_log('[DB] '.$e->getMessage()); http_response_code(500); exit('Erreur de connexion à la base de données.'); }
}
