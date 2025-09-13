<?php include_once __DIR__ . "/../includes/header.php"; ?>

<!-- Section recherche -->

<section class="head">
    <div class="container py-3">
        <div class="text-center mb-3">
            <h1 class="titre-recherche d-inline-block px-3 py-2">Rechercher votre trajet</h1>
        </div>

        <form action="covoiturages.php" method="get" class="forms-wrap">
            <div class="row g-4 justify-content-center align-items-stretch flex-lg-nowrap">

                <!-- Colonne gauche -->
                <div class="col-12 col-lg-5 d-flex">
                    <div class="form-box w-100 h-100">
                        <h6 class="form-box-title">Infos trajet</h6>

                        <div class="mb-3">
                            <label class="form-label">Ville de départ :</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10" />
                                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6" />
                                    </svg>
                                </span>
                                <input type="text" name="depart" class="form-control" placeholder="Ex: Toulouse" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Destination :</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10" />
                                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6" />
                                    </svg>
                                </span>
                                <input type="text" name="arrivee" class="form-control" placeholder="Ex: Bordeaux" required>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Date :</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                    </div>
                </div>

                <!-- Colonne du milieu -->
                <div class="col-12 col-lg-auto d-flex align-items-center justify-content-center mid-col">
                    <button type="submit" class="btn btn-success btn-lg search-btn px-4">
                        Rechercher
                    </button>

                    <!-- Bouton de toggle visible < lg -->
                    <button class="btn btn-filtre d-lg-none ms-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#filtersCollapse"
                        aria-expanded="false"
                        aria-controls="filtersCollapse">
                        Filtres avancés
                    </button>
                </div>

                <!-- Colonne droite -->
                <div class="col-12 col-lg-5">
                    <div id="filtersCollapse" class="collapse d-lg-flex">
                        <div class="form-box w-100 h-100">
                            <h6 class="form-box-title">Filtres avancés</h6>

                            <div class="mb-3">
                                <label class="form-label">Type de motorisation :</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fuel-pump" viewBox="0 0 16 16">
                                            <path d="M3 2.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 .5.5v5a.5.5 0 0 1-.5.5h-5a.5.5 0 0 1-.5-.5z" />
                                            <path d="M1 2a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v8a2 2 0 0 1 2 2v.5a.5.5 0 0 0 1 0V8h-.5a.5.5 0 0 1-.5-.5V4.375a.5.5 0 0 1 .5-.5h1.495c-.011-.476-.053-.894-.201-1.222a.97.97 0 0 0-.394-.458c-.184-.11-.464-.195-.9-.195a.5.5 0 0 1 0-1q.846-.002 1.412.336c.383.228.634.551.794.907.295.655.294 1.465.294 2.081v3.175a.5.5 0 0 1-.5.501H15v4.5a1.5 1.5 0 0 1-3 0V12a1 1 0 0 0-1-1v4h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1zm9 0a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v13h8z" />
                                        </svg>
                                    </span>
                                    <select name="motorisation" class="form-select">
                                        <option value="">Indifférent</option>
                                        <option>Essence</option>
                                        <option>Diesel</option>
                                        <option>Hybride</option>
                                        <option>Électrique</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="prix" class="form-label">Prix max :</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cash" viewBox="0 0 16 16">
                                            <path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4" />
                                            <path d="M0 4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm3 0a2 2 0 0 1-2 2v4a2 2 0 0 1 2 2h10a2 2 0 0 1 2-2V6a2 2 0 0 1-2-2z" />
                                        </svg>
                                    </span>
                                    <input type="number" name="prix" class="form-control" placeholder="Ex: 20">
                                    <span class="input-group-text">€</span>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label for="fumeur" class="form-label">Fumeur :</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M2 17h14v2H2v-2zm16 0h4v2h-4v-2zm1-6h2v4h-2v-4zm-3 0h2v4h-2v-4zm-2.38-2.83a2 2 0 0 1 0-2.83l.71-.71a1 1 0 0 0 0-1.41l-1.42-1.41l-1.41 1.41a4 4 0 0 0 0 5.66L13.62 12H15v2h2v-2c0-1.06-.42-2.09-1.17-2.83z" />
                                        </svg>
                                    </span>
                                    <select id="fumeur" name="fumeur" class="form-select">
                                        <option value="">Indifférent</option>
                                        <option value="non">Non-fumeur</option>
                                        <option value="oui">Fumeur</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</section>












<?php include_once __DIR__ . "/../includes/footer.php"; ?>