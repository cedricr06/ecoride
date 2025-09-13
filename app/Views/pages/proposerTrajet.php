<?php require BASE_PATH . '/app/Controllers/_proposer.ctrl.php'; ?>
<?php include_once __DIR__ . "/../includes/header.php"; ?>

<section>
    <div>
        <form method="post" action="<?= url('proposer-trajet') ?>" class="<?= $form_class ?>" novalidate>
            <?= csrf_field() ?>

        </form>

    </div>
</section>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>