<?php require BASE_PATH . '/app/Controllers/_connexion.ctrl.php'; ?>
<?php include_once __DIR__ . "/../includes/header.php"; ?>


<section class="head-connecter d-flex align-items-center justify-content-center min-vh-100 text-white">
    <div class="justify-content-center text-center bg-dark bg-opacity-75 p-4 rounded">
        <h1 class="titre-formulaire">Se connecter</h1>
        <?php if (!empty($errors['global'])): ?>
            <div class="alert alert-danger text-start"><?= e($errors['global']) ?></div>
        <?php endif; ?>
        <form action="<?= url('connexion') ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3 text-start">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email" class="form-control" id="email" name="email"
                    value="<?= e($data['email'] ?? '') ?>" autocomplete="email" required autofocus>
                <?php if (!empty($errors['email'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>
            <div class="mb-3 text-start password-wrap">
                <label for="password" class="form-label">Mot de passe</label>
                <input
                    type="password" class="form-control" id="password" name="password"
                    required autocomplete="current-password">
                <button type="button" class="eye-btn" data-target="password" aria-label="Afficher / masquer"></button>
                <?php if (!empty($errors['password'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                <?php endif; ?>
                <button type="button" class="eye-btn" data-target="password" aria-label="Afficher / masquer"></button>
            </div>
            <div class="btn-row d-flex justify-content-center ">
                <button type="submit" class="btn btn-success btn-connexion">Se connecter</button>
                <a href="<?= BASE_URL ?>/inscription" class="btn btn-success btn-connexion">S'inscrire</a>
            </div>

        </form>
    </div>
</section>


<?php include_once __DIR__ . "/../includes/footer.php"; ?>