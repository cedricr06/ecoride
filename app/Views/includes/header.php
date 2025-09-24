<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <base href="<?= e(BASE_URL) ?>/">
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EcoRide</title>
    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css')?>">

    <!-- CSS personnel -->
    <link rel="stylesheet" href="<?= asset('css/style.css?v=<?= time()')?>">

    <!-- Optimisation : pré-connexion -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Poppins + Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,200..900;1,200..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <script src="<?= asset('js/profil.js')?>" defer></script>
</head>

<body>

    <?php include_once __DIR__ . '/navbar.php'; ?> <!--Navbar-->