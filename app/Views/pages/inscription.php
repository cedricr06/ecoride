<?php require BASE_PATH . '/app/Controllers/_inscription.ctrl.php'; ?>
<?php include_once __DIR__ . "/../includes/header.php"; ?>


<section class="head-inscription d-flex align-items-center justify-content-center min-vh-100 text-white">
    <div class="form-container justify-content-center text-center bg-dark bg-opacity-75 p-4 rounded">
        <h1 class="titre-formulaire">Inscription</h1>
        <form action="<?= BASE_URL ?>/inscription" method="post" novalidate class="text-start">
            <?= csrf_field() ?>

            <!-- Pseudo -->
            <div class="mb-3">
                <label for="pseudo" class="form-label">Pseudo</label>
                <input type="text" id="pseudo" name="pseudo" class="form-control" required
                    minlength="3" maxlength="10" autocomplete="nickname" value="<?= e($data['pseudo'] ?? '') ?>">
                <?php if (isset($errors['pseudo'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['pseudo']) ?></div>
                <?php else: ?>
                    <div class="invalid-feedback">Choisis un pseudo (3–10 caractères).</div>
                <?php endif; ?>
            </div>

        
            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email" id="email" name="email" class="form-control"
                    required autocomplete="email" inputmode="email"
                    value="<?= e($data['email'] ?? '') ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['email']) ?></div>
                <?php else: ?>
                    <div class="invalid-feedback">Entre un email valide.</div>
                <?php endif; ?>
            </div>

            <!-- Mot de passe -->
            <div class="password-wrap mb-3">
                <label for="password" class="form-label">Mot de passe <small class="">≥ 8 caractères</small></label>
                <input
                    type="password" id="password" name="password" class="form-control"
                    required autocomplete="new-password" minlength="8" maxlength="72">
                <button type="button" class="eye-btn" data-target="password" aria-label="Afficher / masquer"></button>
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Mot de passe confirmation -->
            <div class="password-wrap mb-3">
                <label for="password2" class="form-label">Confirmer votre mot de passe</label>
                <input
                    type="password" id="password2" name="password2" class="form-control"
                    required autocomplete="new-password" minlength="8" maxlength="72">
                <button type="button" class="eye-btn" data-target="password2" aria-label="Afficher / masquer"></button>
                <?php if (isset($errors['password2'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['password2']) ?></div>
                <?php endif; ?>
            </div>


            <!-- Nom / Prénom : -->
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="prenom" class="form-label">Prénom</label>
                    <input
                        type="text" id="prenom" name="prenom" class="form-control"
                        required autocomplete="given-name" value="<?= e($data['prenom'] ?? '') ?>">
                    <?php if (isset($errors['prenom'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['prenom']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="nom" class="form-label">Nom</label>
                    <input
                        type="text" id="nom" name="nom" class="form-control"
                        required autocomplete="family-name" value="<?= e($data['nom'] ?? '') ?>">
                    <?php if (isset($errors['nom'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['nom']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CGU / consentement -->
            <div class="form-check my-3">
                <input class="form-check-input" type="checkbox" id="cgu" name="cgu" required <?= !empty($data['cgu']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="cgu">
                    J’accepte les <a href="<?= url('condition') ?>" class="link-light text-decoration-underline">CGU & Politique de confidentialité</a>.
                </label>
                <?php if (isset($errors['cgu'])): ?>
                    <div class="invalid-feedback d-block"><?= e($errors['cgu']) ?></div>
                <?php else: ?>
                    <div class="invalid-feedback">Tu dois accepter pour créer un compte.</div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-success w-100">Créer mon compte</button>
        </form>

    </div>
</section>


<?php include_once __DIR__ . "/../includes/footer.php"; ?>