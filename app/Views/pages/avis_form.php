<?php
declare(strict_types=1);

$token = $_GET['t'] ?? null;
if (!$token) {
    http_response_code(400);
    exit('Lien d’avis invalide.');
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laisser un avis - EcoRide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .review-container { max-width: 600px; margin: 50px auto; padding: 30px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .rating-stars { font-size: 2rem; color: #ffc107; }
        .rating-stars .form-check-input { display: none; }
        .rating-stars label { cursor: pointer; }
        .rating-stars label:hover, .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label { color: #e0a800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="review-container">
            <h1 class="mb-4">Avis pour <?= htmlspecialchars($driver['prenom'] ?? 'votre conducteur') ?></h1>

            <form action="<?= htmlspecialchars(BASE_URL . '/avis/' . $token) ?>" method="POST">
                <?= $csrf_field ?? '' // Assuming csrf_field() generates the hidden input ?>

                <div class="mb-3">
                    <label for="note" class="form-label">Votre note :</label>
                    <div class="rating-stars">
                        <input type="radio" id="star5" name="note" value="5" required><label for="star5" title="5 étoiles">★</label>
                        <input type="radio" id="star4" name="note" value="4"><label for="star4" title="4 étoiles">★</label>
                        <input type="radio" id="star3" name="note" value="3"><label for="star3" title="3 étoiles">★</label>
                        <input type="radio" id="star2" name="note" value="2"><label for="star2" title="2 étoiles">★</label>
                        <input type="radio" id="star1" name="note" value="1"><label for="star1" title="1 étoile">★</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="commentaire" class="form-label">Votre commentaire (facultatif, max 1000 caractères) :</label>
                    <textarea class="form-control" id="commentaire" name="commentaire" rows="5" maxlength="1000"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Envoyer mon avis</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
