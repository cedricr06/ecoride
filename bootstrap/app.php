<?php 
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv -> load();

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dname=%s;charset=utf8mb4',
    $ENV['DB_HOST'],
    $ENV['DB_PORT'],
    $ENV['DB_DATABASE']
    ),
    $ENV['DB_USERNAME'],
    $ENV['DB_PASSWORD'] ?? ''
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

return['pdo' => $pdo];

