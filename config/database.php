<?php

$host = "127.0.0.1";
$dbname = "ecoride";
$username = "root";
$password = "";

try{
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données.";
}catch(PDOException $e){
    echo " Erreur " . $e->getMessage();
}
