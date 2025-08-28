<header>

<nav class="navbar navbar-expand-lg bg-light fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="#"><img src="/Projet_ecoride/public/assets/img/EcoRide.png" alt=""></a>

    <!-- Bouton hamburger -->
    <button class="navbar-toggler" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#mainMenu"
            aria-controls="mainMenu" aria-label="Ouvrir le menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Un SEUL bloc de menu -->
    <div class="offcanvas offcanvas-end offcanvas-lg" tabindex="-1" id="mainMenu" aria-labelledby="mainMenuLabel">
      <div class="offcanvas-header barre-menu">
        <h5 class="offcanvas-title " id="mainMenuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav ms-auto align-items-center gap-3">
          <li class="nav-item"><a class="nav-link" href="index.php" data-bs-dismiss="offcanvas">Accueil</a></li>
          <li class="nav-item"><a class="nav-link" href="trajets.php" data-bs-dismiss="offcanvas">Trajets</a></li>
          <li class="nav-item"><a class="nav-link" href="proposerTrajet.php" data-bs-dismiss="offcanvas">Proposer un trajet</a></li>
          <li class="nav-item"><a class="nav-link" href="contact.php" data-bs-dismiss="offcanvas">Contact</a></li>
          <li class="nav-item mt-2 mt-md-0">
            <a class="btn btn-success w-100 d-block" href="connexion.php" data-bs-dismiss="offcanvas">Se connecter</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>

</header>