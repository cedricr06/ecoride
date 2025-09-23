<?php require BASE_PATH . '/app/Controllers/_contact.ctrl.php'; ?>
<?php include_once __DIR__ . "/../includes/header.php"; ?>

<section class="head-inscription d-flex align-items-center justify-content-center min-vh-100 text-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="bg-dark bg-opacity-75 p-4 rounded">
                    <h1 class="titre-formulaire text-center mb-4">Contactez-nous</h1>
                    <form method="post" action="">
                        <?= csrf_field() ?>
                        <!-- Honeypot anti-spam -->
                        <input type="text" name="website" autocomplete="off" style="position:absolute;left:-9999px" tabindex="-1">

                        <div class="mb-3">
                            <label for="fullname" class="form-label">Nom complet</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" autocomplete="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="cgu" name="cgu" required>
                            <label class="form-check-label" for="cgu">
                                J'accepte les
                                <a href="<?= BASE_URL ?>/condition"
                                    class="link-light text-decoration-underline"
                                    onclick="event.stopPropagation();">
                                    conditions d'utilisation
                                </a>
                            </label>
                            <div class="invalid-feedback">Vous devez accepter les conditions.</div>
                        </div>

                        <div class="text-center mt-2">
                            <button type="submit" class="btn btn-success btn-connexion">Envoyer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>


<?php include_once __DIR__ . "/../includes/footer.php"; ?>