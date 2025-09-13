<footer class="footer navbar navbar-dark">
  <div class="container d-flex align-items-center justify-content-between py-2">
    <!-- Gauche : logo + copyright -->
    <div class="d-flex align-items-center gap-2 brand">
      <a href="<?= BASE_URL ?>/"><img class="footer-logo" src="<?= BASE_URL ?>/assets/img/EcoRide.png" alt="Logo EcoRide"></a>
      <p class="mb-0">© 2025 EcoRide - Tous droits réservés</p>
    </div>

    <!-- Desktop (≥992px) : menu à droite -->
    <ul class="navbar-nav ms-auto gap-1 flex-row desktop">
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/mention">Mentions légales</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/condition">Conditions Générales d'Utilisation</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/contact">Contact</a></li>
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
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/mention">Mentions légales</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/condition">CGU</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/contact">Contact</a></li>
      </ul>
    </div>
  </div>
</footer>


<!-- JS bootstrap -->
<script src="<?= BASE_URL ?>/assets/JS/bootstrap.bundle.min.js"></script>

<!-- Js personnel-->
<script src="<?= BASE_URL ?>/assets/JS/script.js"></script>

</body>
</html>
