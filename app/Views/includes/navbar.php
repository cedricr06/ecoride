<?php $user = $_SESSION['user'] ?? null;  ?>

<header>

  <nav class="navbar navbar-expand-lg bg-light fixed-top">
    <div class="container-fluid">
      <!-- Logo -->
      <a class="navbar-brand" href="<?= BASE_URL ?>/">
        <img src="<?= BASE_URL ?>/assets/img/EcoRide.png" alt="EcoRide">
      </a>

      <!-- Compte (EN DEHORS du hamburger) -->
      <div class="account-area ms-auto">
        <?php if (!empty($user)): ?>
          <span class="welcome navbar-text fw-semibold">
            <span class="welcome-label d-none d-sm-inline">Bienvenue, </span>
            <strong class="welcome-name"><?= e($user['pseudo'] ?? $user['prenom'] ?? '') ?></strong>
          </span>
          <span class="badge bg-credits" title="Crédits disponibles">
            🪙 <?= (int)($user['credits'] ?? 0) ?>
          </span>

          <div class="dropdown">
            <button class="btn user-toggle dropdown-toggle p-0"
              type="button" data-bs-toggle="dropdown"
              aria-expanded="false" aria-label="Compte">
              <!-- Icône avatar -->
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"></circle>
                <path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
              </svg>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <?php if (($user['role'] ?? '') !== 'administrateur'): ?>
                <li><a class="dropdown-item" href="<?= url('proposer-trajet') ?>">Proposer un trajet</a></li>
              <?php endif; ?>
              <?php if (($user['role'] ?? '') === 'administrateur'): ?>
                <li><a class="dropdown-item" href="<?= url('admin') ?>">Administration</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?= url('profil') ?>">Profil</a></li>
              <?php endif; ?>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li class="px-3 py-1">
                <form action="<?= BASE_URL ?>/deconnexion" method="post">
                  <?= csrf_field() ?>
                  <button class="btn btn-success w-100" type="submit">Se déconnecter</button>
                </form>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <div class="dropdown">
            <button class="btn user-toggle dropdown-toggle p-0"
              type="button" data-bs-toggle="dropdown"
              aria-expanded="false" aria-label="Compte">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"></circle>
                <path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
              </svg>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/connexion">Se connecter</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/inscription">Créer un compte</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>

      <!-- Hamburger -->
      <button class="navbar-toggler ms-2" type="button"
        data-bs-toggle="offcanvas" data-bs-target="#mainMenu"
        aria-controls="mainMenu" aria-label="Ouvrir le menu">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- OFFCANVAS: uniquement les liens -->
      <div class="offcanvas offcanvas-end offcanvas-lg" tabindex="-1" id="mainMenu" aria-labelledby="mainMenuLabel">
        <div class="offcanvas-header barre-menu d-lg-none">
          <h5 class="offcanvas-title text-white" id="mainMenuLabel">Menu</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="navbar-nav align-items-lg-center gap-lg-3">
            <li class="nav-item"><a class="nav-link" href="<?= url('/') ?>">Accueil</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= url('trajets') ?>">Covoiturages</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= url('contact') ?>">Contact</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>



</header>
