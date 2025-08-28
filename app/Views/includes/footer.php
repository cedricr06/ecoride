<footer class="footer navbar navbar-dark fixed-bottom">
  <div class="container d-flex align-items-center justify-content-between py-2">
    <!-- Gauche : logo + copyright -->
    <div class="d-flex align-items-center gap-2 brand">
      <img class="footer-logo" src="/Projet_ecoride/public/assets/img/EcoRide.png" alt="Logo EcoRide">
      <p class="mb-0">© 2025 EcoRide - Tous droits réservés</p>
    </div>

    <!-- Desktop (≥992px) : menu à droite -->
    <ul class="navbar-nav ms-auto gap-1 flex-row desktop">
      <li class="nav-item"><a class="nav-link" href="mention.php">Mentions légales</a></li>
      <li class="nav-item"><a class="nav-link" href="condition.php">Conditions Générales d'Utilisation</a></li>
      <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
    </ul>

    <!-- Mobile/Tablet (<992px) : bouton hamburger -->
    <button
      class="navbar-toggler d-lg-none"
      type="button"
      data-bs-toggle="offcanvas"
      data-bs-target="#footerMenu"
      aria-controls="footerMenu"
      aria-label="Ouvrir le menu"
    >
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>

  <!-- Offcanvas (mobile/tablette) -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="footerMenu" aria-labelledby="footerMenuLabel">
    <div class="offcanvas-header footerMenu">
      <h5 class="offcanvas-title" id="footerMenuLabel">Menu</h5>
      <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body">
      <ul class="navbar-nav d-flex flex-column align-items-start text-start gap-3">
        <li class="nav-item"><a class="nav-link" href="mention.php" data-bs-dismiss="offcanvas">Mentions légales</a></li>
        <li class="nav-item"><a class="nav-link" href="condition.php" data-bs-dismiss="offcanvas">CGU</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php" data-bs-dismiss="offcanvas">Contact</a></li>
      </ul>
    </div>
  </div>
</footer>


<!-- JS bootstrap -->
<script src="/Projet_ecoride/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

<!-- Js personnel-->
<script src="/Projet_ecoride/public/assets/JS/script.js"></script>

</body>
</html>
