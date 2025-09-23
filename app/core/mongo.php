<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

if (!extension_loaded('mongodb')) {
    throw new RuntimeException("Extension PHP 'mongodb' non chargée (pecl install mongodb + extension=mongodb dans php.ini).");
}
if (!class_exists(\MongoDB\Client::class)) {
    throw new RuntimeException("Paquet composer mongodb/mongodb manquant (composer require mongodb/mongodb).");
}

/**
 * Retourne [Database $db, Collection $avis, Collection $driver_stats]
 * Connexion mise en cache.
 */
function mongo(): array
{
    static $cached = null;
    if ($cached) return $cached;

    // 1) Essaye getenv, puis $_SERVER (SetEnv), puis apache_getenv
    $uri = getenv('MONGO_URI') ?: ($_SERVER['MONGO_URI'] ?? null);
    if (!$uri && function_exists('apache_getenv')) {
        $uri = apache_getenv('MONGO_URI', true) ?: null;
    }
    if (!$uri || trim($uri) === '') {
        throw new RuntimeException("MONGO_URI introuvable (définis-la dans .htaccess ou vhost).");
    }

    $client = new \MongoDB\Client($uri, [], [
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
    ]);

    $db    = $client->selectDatabase('ecoride');
    $avis  = $db->selectCollection('avis');
    $stats = $db->selectCollection('driver_stats');

    return $cached = [$db, $avis, $stats];
}
