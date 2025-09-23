<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
header('Content-Type: text/plain; charset=utf-8');

echo "BASE_PATH=".BASE_PATH."\n";
echo ".env existe? ".(file_exists(BASE_PATH.'/.env')?'OUI':'NON')."\n";
echo ".env chemin: ".(realpath(BASE_PATH.'/.env') ?: '(introuvable)')."\n\n";

require BASE_PATH.'/vendor/autoload.php';

// Charge Dotenv (MUTABLE pour exposer getenv)
if (class_exists(\Dotenv\Dotenv::class)) {
  $dotenv = \Dotenv\Dotenv::createMutable(BASE_PATH);
  $dotenv->safeLoad();
  echo "Dotenv chargé.\n";
} else {
  echo "Dotenv NON disponible (install composer?).\n";
}

// Montre les 6 clés critiques (masquées)
$keys = ['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_FROM','SMTP_DEBUG'];

echo "\n[getenv]\n";
foreach ($keys as $k) {
  $v = getenv($k);
  echo $k.'='.($v!==false ? ($k==='SMTP_PASS'?'(*** masqué ***)':$v) : '(vide)')."\n";
}

echo "\n[\$_ENV]\n";
foreach ($keys as $k) {
  $has = array_key_exists($k, $_ENV);
  $v = $has ? $_ENV[$k] : null;
  echo $k.'='.($has ? ($k==='SMTP_PASS'?'(*** masqué ***)':$v) : '(absent)')."\n";
}


