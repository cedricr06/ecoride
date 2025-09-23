<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class)) \Dotenv\Dotenv::createMutable(BASE_PATH)->safeLoad();

$mailer = new \App\Services\Mailer();
$ok = $mailer->send('ton.email@exemple.com', 'Test SMTP', '<p>OK '.date('c').'</p>');
header('Content-Type: text/plain; charset=utf-8');
echo 'SEND=' . ($ok ? 'OK' : 'FAIL');