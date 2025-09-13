<?php include_once __DIR__ . "/../includes/header.php"; ?>


<!-- section hero -->
<section class="hero d-flex align-items-center text-white">
  <div class="container">
    <div class="row gy-4 align-items-stretch"> <!-- colonnes mêmes hauteurs -->

      <!-- Texte en 2 blocs (desktop à gauche, empilé en mobile) -->
      <div class="col-12 col-md-7 col-lg-7 d-flex">
        <div class="vstack gap-2 hero-titre overlay-card h-100 w-100 d-flex flex-column justify-content-center text-center p-4">
          <h1 class="mb-3">Partagez vos trajets, économisez et préservez la planète</h1>
          <p class="mb-0 texte-hero">
            Trouvez un covoiturage écologique et économique en quelques clics.
            Conducteurs notés, trajets sécurisés.
          </p>
        </div>
      </div>

      <!-- Formulaire (à droite en ≥992px / en dessous en mobile) -->
      <div class="col-12 col-md-5 col-lg-5 d-flex">
        <form class="overlay-card p-4 formulaire-hero ms-lg-auto h-100 w-100 d-flex flex-column justify-content-center" action="" method="get" style="max-width:520px;">
          <div class="mb-3">
            <label class="form-label" for="depart">Ville de départ :</label>
            <input id="depart" type="text" class="form-control" placeholder="Ex: Toulouse" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="destination">Destination :</label>
            <input id="destination" type="text" class="form-control" placeholder="Ex: Bordeaux" required>
          </div>

          <div>
            <label class="form-label" for="date">Date :</label>
            <div class="input-group flex-nowrap">
              <input id="date" type="date" class="form-control" required>
              <button class="btn btn-success btn-rechercher" type="submit">Rechercher</button>
            </div>
          </div>
        </form>
      </div>

    </div>
  </div>
</section>


<!-- section présentation -->

<!-- section avantage -->
<section class="presentation">
  <div class=" text-center">
    <div class="row gx-0 justify-content-center">
      <div class="col-12 col-md-4 mb-4 avantage px-3  ">
        <div class="icon-wrapper">
          <img class="icon-avantage" src="/Projet_ecoride/public/assets/img/icon_accueil/globe-americas-fill.svg" alt="icon globe">
        </div>
        <h2>Ecologique</h2>
        <p class="texte-avantage">Favorisez les trajets en voiture éléctrique et réduisez votre empreinte carbonne.</p>
      </div>

      <div class="col-12 col-md-4 mb-4 avantage">
        <div class="icon-wrapper">
          <img class="icon-avantage" src="/Projet_ecoride/public/assets/img/icon_accueil/wallet2.svg" alt="icon portefeuille">
        </div>
        <h2>Economique</h2>
        <p class="texte-avantage">Partagez vos frais de transport et voyagez à petit prix.</p>
      </div>

      <div class="col-12 col-md-4 mb-4 avantage">
        <div class="icon-wrapper">
          <img class="icon-avantage" src="/Projet_ecoride/public/assets/img/icon_accueil/check-square-fill.svg" alt="icon valider">
        </div>
        <h2>Fiable</h2>
        <p class="texte-avantage">Conducteurs notés, trajets vérifiés, paiement sécurisé.</p>
      </div>
    </div>
  </div>

</section>

<!-- section Qui sommes-nous -->


<section class="presentation-section">
  <div class="container d-flex align-items-center">
    <div class="row align-items-center justify-content-center w-100 g-4 text-start ">
      <div class="col-12 col-md-6 text-center mb-4 mb-md-0 ">
        <img src="/Projet_ecoride/public/assets/img/electric-car.jpg" alt="voiture electrique" class="img-fluid presentation-img">
      </div>
      <div class="col-12 col-md-6 mb-4 mb-md-0  presentation-content ">
        <h2>Qui sommes<span style="color:#27AE60">-nous ?</span></h2>
        <p>EcoRide est une plateforme de covoiturage moderne qui met en avant l’écologie, la sécurité et l’économie. Notre mission est de faciliter vos trajets du quotidien tout en réduisant l’impact environnemental.</p>
        <ul>
          <div class="liste-avantage-presentation ">
            <div class="icon-wrapper">
              <li> Simplicité d'utilisation</li>
              <li>Sécurité des trajets</li>
              <li>Impact écoogique positif</li>
            </div>
          </div>
        </ul>
      </div>
    </div>

</section>





<?php include_once __DIR__ . "/../includes/footer.php"; ?>