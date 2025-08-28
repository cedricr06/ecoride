<?php include_once __DIR__ . "/../includes/header.php"; ?>

<!--<section class="hero d-flex">
    <h1 class="container-titre-hero">Partagez vos trajets, économisez et préservez la planète</h1>
    <p class="container-p-hero">Trouvez un covoiturage écologique et économique enquelques clics. Conducteurs notés, trajets sécurisés.</p>


    <form class="formulaire-hero" action="submit" method="post">
        <div class="vstack">
            <label for="ville de depart">Ville de départ :</label>
            <input class="form-control" type="text" id="ville_de_depart" name="ville_de_depart" required>

            <label for="ville de destination">destination :</label>
            <input class="form-control" type="text" id="ville_de_depart" name="ville_de_depart" required>


            <label for="date">Date :</label>
            <div class="input-group flex-nowrap">
                <input class="form-control" type="date" id="date" name="date" required>
                <button class="btn btn-success btn-rechercher" type="submit">Rechercher</button>
            </div>
        </div>
    </form>
</section>-->

<section class="hero d-flex align-items-center text-white">
  <div class="container">
    <div class="row gy-4 align-items-stretch"><!-- align-items-stretch = colonnes mêmes hauteurs -->

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
        <form class="overlay-card p-4 formulaire-hero ms-lg-auto h-100 w-100 d-flex flex-column justify-content-center" style="max-width:520px;">
          <div class="mb-3">
            <label class="form-label" for="from">Ville de départ :</label>
            <input id="from" type="text" class="form-control" placeholder="Ex: Toulouse">
          </div>

          <div class="mb-3">
            <label class="form-label" for="to">Destination :</label>
            <input id="to" type="text" class="form-control" placeholder="Ex: Bordeaux">
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







<?php include_once __DIR__ . "/../includes/footer.php"; ?>