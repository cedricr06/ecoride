<?php
// Page: Proposer un trajet (GET: afficher formulaire, POST: valider + insérer)
require_login();

// Helpers DB / session
/** @var PDO $db */
if (!isset($db)) { $db = db(); }

$uid      = (int)($_SESSION['user']['id'] ?? 0);
$errors   = [];
$old      = [];
$success  = '';
$vehicules = [];

// Charger la liste des véhicules de l'utilisateur (GET et POST pour le select sticky)
try {
    $stmtCars = $db->prepare("SELECT id, marque, modele, couleur, energie FROM vehicules WHERE utilisateur_id = :uid ORDER BY marque, modele");
    $stmtCars->execute([':uid' => $uid]);
    $vehicules = $stmtCars->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $errors['global'] = "Erreur lors du chargement des véhicules.";
}

// Traitement POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    // Récupération + nettoyage de base
    $vehicule_id        = (int)($_POST['vehicule_id'] ?? 0);
    $ville_depart       = trim((string)($_POST['ville_depart'] ?? ''));
    $ville_arrivee      = trim((string)($_POST['ville_arrivee'] ?? ''));
    $date_depart_input  = trim((string)($_POST['date_depart'] ?? ''));
    $date_arrivee_input = trim((string)($_POST['date_arrivee'] ?? ''));
    $prix_input         = (string)($_POST['prix'] ?? '');
    $places_input       = (string)($_POST['places_disponibles'] ?? '');

    $old = [
        'vehicule_id'        => $vehicule_id,
        'ville_depart'       => $ville_depart,
        'ville_arrivee'      => $ville_arrivee,
        'date_depart'        => $date_depart_input,
        'date_arrivee'       => $date_arrivee_input,
        'prix'               => $prix_input,
        'places_disponibles' => $places_input,
    ];

    // Validations
    if ($vehicule_id <= 0) {
        $errors['vehicule_id'] = "Choisissez un véhicule.";
    }

    $len = function($s){ return function_exists('mb_strlen') ? mb_strlen($s,'UTF-8') : strlen($s); };

    if ($ville_depart === '' || $len($ville_depart) < 2 || $len($ville_depart) > 80) {
        $errors['ville_depart'] = "Ville de départ invalide (2–80 caractères).";
    }
    if ($ville_arrivee === '' || $len($ville_arrivee) < 2 || $len($ville_arrivee) > 80) {
        $errors['ville_arrivee'] = "Ville d’arrivée invalide (2–80 caractères).";
    }
    if (!isset($errors['ville_depart']) && !isset($errors['ville_arrivee'])) {
        $toLower = function($s){ return function_exists('mb_strtolower') ? mb_strtolower($s,'UTF-8') : strtolower($s); };
        if ($toLower($ville_depart) === $toLower($ville_arrivee)) {
            $errors['ville_arrivee'] = "La ville d’arrivée doit être différente de la ville de départ.";
        }
    }

    // Dates: inputs HTML5 datetime-local → 'Y-m-d H:i:00'
    $date_depart_sql = '';
    $date_arrivee_sql = '';
    if ($date_depart_input === '') {
        $errors['date_depart'] = "La date de départ est requise.";
    } else {
        $ts = strtotime($date_depart_input);
        if ($ts === false) {
            $errors['date_depart'] = "Format de date de départ invalide.";
        } else {
            $date_depart_sql = date('Y-m-d H:i:00', $ts);
            // Doit être ≥ maintenant
            try {
                $now = new DateTime();
                $dd  = new DateTime($date_depart_sql);
                if ($dd < $now) {
                    $errors['date_depart'] = "La date de départ doit être dans le futur.";
                }
            } catch (Throwable $e) {
                $errors['date_depart'] = "Date de départ invalide.";
            }
        }
    }
    if ($date_arrivee_input !== '') {
        $tsa = strtotime($date_arrivee_input);
        if ($tsa === false) {
            $errors['date_arrivee'] = "Format de date d’arrivée invalide.";
        } else {
            $date_arrivee_sql = date('Y-m-d H:i:00', $tsa);
        }
    }
    if ($date_depart_sql && $date_arrivee_sql) {
        try {
            $dd = new DateTime($date_depart_sql);
            $da = new DateTime($date_arrivee_sql);
            if ($da <= $dd) {
                $errors['date_arrivee'] = "La date d’arrivée doit être après le départ.";
            }
        } catch (Throwable $e) {
            $errors['date_arrivee'] = "Date d’arrivée invalide.";
        }
    }

    // Prix: décimal min 0, step 0.5 (on tolère côté serveur min 0)
    if ($prix_input === '' || !is_numeric($prix_input)) {
        $errors['prix'] = "Prix invalide.";
    } else {
        $prix = (float)$prix_input;
        if ($prix < 0) {
            $errors['prix'] = "Le prix doit être ≥ 0.";
        }
    }

    // Places: tinyint 1..8
    if ($places_input === '' || !ctype_digit((string)$places_input)) {
        $errors['places_disponibles'] = "Nombre de places invalide.";
    } else {
        $places = (int)$places_input;
        if ($places < 1 || $places > 8) {
            $errors['places_disponibles'] = "Places entre 1 et 8.";
        }
    }

    // Vérifier que le véhicule appartient à l’utilisateur + récupérer son énergie
    $is_electrique = 0;
    if ($vehicule_id > 0 && empty($errors['vehicule_id'])) {
        $stmtCheck = $db->prepare("SELECT energie FROM vehicules WHERE id = :vid AND utilisateur_id = :uid");
        $stmtCheck->execute([':vid' => $vehicule_id, ':uid' => $uid]);
        $rowCar = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$rowCar) {
            $errors['vehicule_id'] = "Véhicule invalide.";
        } else {
            $is_electrique = (strtolower((string)$rowCar['energie']) === 'electrique') ? 1 : 0;
        }
    }

    // Si tout est ok → insertion
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO voyages
(chauffeur_id, vehicule_id, ville_depart, ville_arrivee, date_depart, date_arrivee, prix, places_disponibles, statut, ecologique)
VALUES
(:chauffeur_id, :vehicule_id, :ville_depart, :ville_arrivee, :date_depart, :date_arrivee, :prix, :places_disponibles, :statut, :ecologique)";

            $params = [
                ':chauffeur_id'       => $uid,
                ':vehicule_id'        => (int)$vehicule_id,
                ':ville_depart'       => $ville_depart,
                ':ville_arrivee'      => $ville_arrivee,
                ':date_depart'        => $date_depart_sql,
                ':date_arrivee'       => ($date_arrivee_sql !== '' ? $date_arrivee_sql : null),
                ':prix'               => (isset($prix) ? $prix : 0),
                ':places_disponibles' => (int)$places,
                ':statut'             => 'ouvert',
                ':ecologique'         => $is_electrique ? 1 : 0,
            ];

            $stIns = $db->prepare($sql);
            $stIns->execute($params);

            if (function_exists('flash')) {
                flash('success', 'Trajet proposé avec succès.');
                header('Location: ' . url('trajets'));
                exit;
            } else {
                $success = 'Trajet proposé avec succès.';
            }
        } catch (Throwable $e) {
            error_log('[voyage insert] ' . $e->getMessage());
            $errors['global'] = "Une erreur est survenue. Réessayez plus tard.";
        }
    }
}
?>

<?php include_once __DIR__ . "/../includes/header.php"; ?>

<section class="head-inscription d-flex align-items-center justify-content-center min-vh-100 text-white">
    <div class="form-container justify-content-center text-center bg-dark bg-opacity-75 p-4 rounded">
        <h1 class="titre-formulaire">Proposer un trajet</h1>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success text-start" role="alert">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['global'])): ?>
            <div class="alert alert-danger text-start" role="alert">
                <?= e($errors['global']) ?>
            </div>
        <?php endif; ?>

        <form action="<?= url('proposer-trajet') ?>" method="post" novalidate class="text-start">
            <?= csrf_field() ?>

            <!-- Véhicule -->
            <div class="mb-3">
                <label for="vehicule_id" class="form-label">Véhicule</label>
                <?php if (empty($vehicules)): ?>
                    <div class="alert alert-info">
                        Vous n’avez encore aucun véhicule. <a class="alert-link" href="<?= url('profil') ?>#tab-vehicules">Ajouter un véhicule</a>.
                    </div>
                <?php endif; ?>
                <select id="vehicule_id" name="vehicule_id" class="form-select" required <?= empty($vehicules) ? 'disabled' : '' ?>>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($vehicules as $car): ?>
                        <?php
                            $optId = (int)$car['id'];
                            $label = trim(($car['marque'] ?? '') . ' ' . ($car['modele'] ?? ''));
                            $couleur = trim((string)($car['couleur'] ?? ''));
                            $energie = strtoupper((string)($car['energie'] ?? ''));
                            $text = $label . ($couleur !== '' ? " (" . $couleur . ")" : '') . " – " . $energie;
                            $selected = ((int)($old['vehicule_id'] ?? 0) === $optId) ? 'selected' : '';
                        ?>
                        <option value="<?= $optId ?>" <?= $selected ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['vehicule_id'])): ?>
                    <small class="text-danger"><?= e($errors['vehicule_id']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Ville de départ -->
            <div class="mb-3">
                <label for="ville_depart" class="form-label">Ville de départ</label>
                <input type="text" id="ville_depart" name="ville_depart" class="form-control" required minlength="2" maxlength="80" value="<?= e($old['ville_depart'] ?? '') ?>">
                <?php if (isset($errors['ville_depart'])): ?>
                    <small class="text-danger"><?= e($errors['ville_depart']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Ville d’arrivée -->
            <div class="mb-3">
                <label for="ville_arrivee" class="form-label">Ville d’arrivée</label>
                <input type="text" id="ville_arrivee" name="ville_arrivee" class="form-control" required minlength="2" maxlength="80" value="<?= e($old['ville_arrivee'] ?? '') ?>">
                <?php if (isset($errors['ville_arrivee'])): ?>
                    <small class="text-danger"><?= e($errors['ville_arrivee']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Date et heure de départ -->
            <div class="mb-3">
                <label for="date_depart" class="form-label">Date et heure de départ</label>
                <input type="datetime-local" id="date_depart" name="date_depart" class="form-control" required value="<?= e($old['date_depart'] ?? '') ?>">
                <?php if (isset($errors['date_depart'])): ?>
                    <small class="text-danger"><?= e($errors['date_depart']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Date et heure d’arrivée (optionnel) -->
            <div class="mb-3">
                <label for="date_arrivee" class="form-label">Date et heure d’arrivée (optionnel)</label>
                <input type="datetime-local" id="date_arrivee" name="date_arrivee" class="form-control" value="<?= e($old['date_arrivee'] ?? '') ?>">
                <?php if (isset($errors['date_arrivee'])): ?>
                    <small class="text-danger"><?= e($errors['date_arrivee']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Prix (€) -->
            <div class="mb-3">
                <label for="prix" class="form-label">Prix (€)</label>
                <input type="number" step="0.5" min="0" id="prix" name="prix" class="form-control" required value="<?= e($old['prix'] ?? '') ?>">
                <?php if (isset($errors['prix'])): ?>
                    <small class="text-danger"><?= e($errors['prix']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Places disponibles -->
            <div class="mb-3">
                <label for="places_disponibles" class="form-label">Places disponibles</label>
                <input type="number" min="1" max="8" id="places_disponibles" name="places_disponibles" class="form-control" required value="<?= e($old['places_disponibles'] ?? '') ?>">
                <?php if (isset($errors['places_disponibles'])): ?>
                    <small class="text-danger"><?= e($errors['places_disponibles']) ?></small>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-success w-100" <?= empty($vehicules) ? 'disabled' : '' ?>>Proposer ce trajet</button>
        </form>
    </div>
</section>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>
