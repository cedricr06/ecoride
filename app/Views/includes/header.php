<?php 
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>EcoRide</title>
    <link rel="stylesheet" href="/Projet_ecoride/node_modules/bootstrap/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="/Projet_ecoride/public/assets/css/style.css?v=<?= time() ?>">
    
</head>
<body>

<?php include_once __DIR__ . '/navbar.php'; ?>  <!--Navbar-->