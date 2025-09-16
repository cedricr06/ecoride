<?php
// Page de détails d'un trajet
// - Inclut le header/footer existants du projet
// - Récupère l'id via GET ?id=...
// - PDO via $GLOBALS['db'] ou fonction db()

// ---------------------------------------------------------------------
// Helpers locaux
// ---------------------------------------------------------------------
if (!function_exists('e')) {
  function e($v)
  {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

function ageFrom(?string $date): string
{
  if (!$date) return '';
  try {
    $dob = new DateTime(substr($date, 0, 10));
    $now = new DateTime();
    $age = (int)$now->format('Y') - (int)$dob->format('Y');
    if ((int)$now->format('md') < (int)$dob->format('md')) {
      $age--;
    }
    return ($age >= 0) ? (string)$age : '';
  } catch (Throwable $e) {
    return '';
  }
}

function starsHtml($note): string
{
  $n = is_numeric($note) ? max(0, min(5, (float)$note)) : 0.0;
  $full = (int)floor($n);
  $half = ($n - $full) >= 0.5 ? 1 : 0; // simple arrondi demi-star (optionnel)
  $empty = 5 - $full - $half;
  $out = '';
  for ($i = 0; $i < $full; $i++)  $out .= '<i class="bi bi-star-fill text-warning"></i>';
  if ($half) $out .= '<i class="bi bi-star-half text-warning"></i>';
  for ($i = 0; $i < $empty; $i++) $out .= '<i class="bi bi-star text-warning"></i>';
  return $out;
}

function fmtDateTime(?string $dt): string
{
  if (!$dt) return '';
  try {
    $d = new DateTime($dt);
    if (class_exists('IntlDateFormatter')) {
      $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::SHORT);
      $fmt->setPattern("d MMMM y '–' HH'h'mm");
      return $fmt->format($d);
    }
    return $d->format('d/m/Y H:i');
  } catch (Throwable $e) {
    return '';
  }
}

// ---------------------------------------------------------------------
// Accès DB
// ---------------------------------------------------------------------
/**
 * Essaie d'obtenir un PDO depuis $GLOBALS['db'] ou db().
 * En dernier recours, crée une fonction db() de secours (placeholders à adapter).
 */
function get_pdo(): ?PDO
{
  if (!empty($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
    return $GLOBALS['db'];
  }
  if (function_exists('db')) {
    $pdo = db();
    if ($pdo instanceof PDO) return $pdo;
  }
  // Fallback (adapter si ce fichier est utilisé hors du projet)
  if (!function_exists('db')) {
    function db(): PDO
    {
      // Adapter ces paramètres à votre environnement si nécessaire
      $dsn = 'mysql:host=localhost;dbname=ecoride;charset=utf8mb4';
      $user = 'root';
      $pass = '';
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      return $pdo;
    }
  }
  $pdo = db();
  return ($pdo instanceof PDO) ? $pdo : null;
}

// ---------------------------------------------------------------------
// Validation ID + récupération
// ---------------------------------------------------------------------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
  http_response_code(404);
  include_once __DIR__ . '/../includes/header.php';
  echo '<main class="container my-4"><div class="alert alert-danger">Trajet introuvable (ID manquant ou invalide).</div>';
  echo '<a class="btn btn-outline-secondary" href="' . (function_exists('url') ? e(url('trajets')) : '../trajets.php') . '">Retour</a></main>';
  include_once __DIR__ . '/../includes/footer.php';
  return;
}

$pdo = get_pdo();
if (!$pdo) {
  http_response_code(500);
  include_once __DIR__ . '/../includes/header.php';
  echo '<main class="container my-4"><div class="alert alert-danger">Erreur de connexion à la base de données.</div></main>';
  include_once __DIR__ . '/../includes/footer.php';
  return;
}

// ---------------------------------------------------------------------
// Session + utilisateur courant
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = $_SESSION['user']['id']
       ?? $_SESSION['utilisateur']['id']
       ?? $_SESSION['auth']['id']
       ?? null;
// Valeurs par défaut pour éviter les notices côté vue
$isDriver = false;
$myParticipation = null;
$placesTotal = 0;
$placesRestantes = 0;
// ---------------------------------------------------------------------
// POST Participer
// ---------------------------------------------------------------------
$flash = function (string $type, string $msg) {
  if (function_exists('flash')) {
    flash($type, $msg);
    return;
  }
  $_SESSION['__flash'][] = ['type' => $type, 'message' => $msg];
};

// Détection de la table de réservations/participants
$detectTable = function (PDO $pdo, array $names): ?string {
  foreach ($names as $name) {
    try {
      $st = $pdo->prepare('SHOW TABLES LIKE ?');
      $st->execute([$name]);
      if ($st->fetchColumn()) return $name;
    } catch (Throwable $e) {
      // ignore
    }
  }
  return null;
};

$T_RES = 'participations';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'participer') {
  if (function_exists('verify_csrf')) {
    verify_csrf();
  }

  $userId = $_SESSION['user']['id'] ?? null; // adapte si ta session stocke différemment
  if (empty($userId)) {
    // Non connecté → redirection vers connexion avec redirect
    $target = (function_exists('url') ? url('connexion') : '../connexion.php');
    $cur = (function_exists('url') ? url('trajet') . '?id=' . $id : 'trajet.php?id=' . $id);
    header('Location: ' . $target . '?redirect=' . urlencode($cur));
    exit;
  }

  if ($T_RES === null) {
    $flash('danger', "La fonctionnalité de réservation n'est pas configurée (table manquante). Contactez l'admin.");
  } else {
    // Récupère le trajet pour validations
    $st = $pdo->prepare("SELECT id, chauffeur_id, places_disponibles FROM voyages WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $tripRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$tripRow) {
      $flash('danger', "Trajet introuvable.");
    } else {
      if ((int)$tripRow['chauffeur_id'] === (int)$userId) {
        $flash('warning', "Vous ne pouvez pas réserver votre propre trajet.");
      } else {
        // Transaction simple : insérer une réservation + (optionnel) décrémenter places
        try {
          $pdo->beginTransaction();

          // Empêche les surbookings si tu continues d’utiliser v.places_disponibles
          $st2 = $pdo->prepare("SELECT places_disponibles FROM voyages WHERE id = :id FOR UPDATE");
          $st2->execute([':id' => $id]);
          $cur = $st2->fetchColumn();
          if ($cur === false || (int)$cur <= 0) {
            throw new RuntimeException('Plus de places disponibles.');
          }

          // Déjà inscrit ? (ignore les participations annulées)
          $st3 = $pdo->prepare("SELECT 1 FROM {$T_RES} WHERE voyage_id = :v AND passager_id = :u AND statut IN ('en_attente','confirme') LIMIT 1");
          $st3->execute([':v' => $id, ':u' => $userId]);
          if ($st3->fetch()) {
            throw new RuntimeException('Vous avez déjà une participation pour ce trajet.');
          }

          // Insert : inscrit_le a une valeur par défaut → pas besoin de le fournir
          $st4 = $pdo->prepare("INSERT INTO {$T_RES} (voyage_id, passager_id, places, statut)
                          VALUES (:v, :u, 1, 'en_attente')");
          $st4->execute([':v' => $id, ':u' => $userId]);

          // Si tu tiens à décrémenter v.places_disponibles tout de suite :
          $st5 = $pdo->prepare("UPDATE voyages
                          SET places_disponibles = places_disponibles - 1
                          WHERE id = :id AND places_disponibles > 0");
          $st5->execute([':id' => $id]);

          $pdo->commit();
          $flash('success', 'Votre demande de participation a été envoyée.');
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          // 1062 = doublon (UNIQUE (voyage_id, passager_id))
          if (($e instanceof PDOException) && ($e->errorInfo[1] ?? null) == 1062) {
            $flash('warning', "Vous êtes déjà inscrit sur ce trajet.");
          } else {
            $flash('danger', e($e->getMessage()));
          }
        }
      }
    }
  }
}


// ---------------------------------------------------------------------
// POST Annuler ma participation
// ---------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'annuler') {

  if (function_exists('verify_csrf')) verify_csrf();

  // ⚠️ n’écrase pas $userId ici : on utilise celui calculé en haut
  if (empty($userId)) {
    $flash('danger', "Vous devez être connecté.");
  } else {
    try {
      $pdo->beginTransaction();

      // supprime UNE participation active pour ce voyage & cet utilisateur
      $del = $pdo->prepare("
        DELETE FROM {$T_RES}
        WHERE voyage_id = :v AND passager_id = :u
          AND statut IN ('en_attente','confirme')
        LIMIT 1
      ");
      $del->execute([':v' => $id, ':u' => $userId]);

      if ($del->rowCount() === 0) {
        $pdo->rollBack();
        $flash('warning', "Aucune participation active à annuler.");
      } else {
        
        $inc = $pdo->prepare("
          UPDATE voyages
          SET places_disponibles = places_disponibles + 1
          WHERE id = :v
        ");
        $inc->execute([':v' => $id]);
      

        $pdo->commit();
        $flash('success', "Votre participation a été annulée.");
        header('Location: ' . ((function_exists('url') ? url('trajet') : 'trajet.php') . '?id=' . $id));
        exit;
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $flash('danger', "Annulation impossible : " . e($e->getMessage()));
    }
  }
}

// Participation de l'utilisateur courant (s'il est connecté)
// ---------------------------------------------------------------------
// Récupération des données du trajet
// NOTE: Adapter les noms de tables/colonnes si votre schéma diffère
// ---------------------------------------------------------------------
$sqlTrip = "
  SELECT
    v.id, v.ville_depart, v.ville_arrivee,
    v.date_depart, v.date_arrivee, v.prix, v.places_disponibles, v.ecologique,
    u.id AS conducteur_id, u.pseudo, u.email,
    pr.date_naissance, pr.photo_url, pr.bio,
    ve.marque, ve.modele, ve.couleur, ve.energie, ve.places AS veh_places,
    pf.fumeur
  FROM voyages v
  JOIN utilisateurs u  ON u.id = v.chauffeur_id
  LEFT JOIN profils pr ON pr.utilisateur_id = u.id
  LEFT JOIN vehicules ve ON ve.id = v.vehicule_id
  LEFT JOIN preferences pf ON pf.utilisateur_id = u.id
  WHERE v.id = :id
  LIMIT 1";
$st = $pdo->prepare($sqlTrip);
$st->execute([':id' => $id]);
$trip = $st->fetch(PDO::FETCH_ASSOC);

// total = nb de places du véhicule (fallback sur v.places_disponibles si besoin)
if ($trip) {
  $placesTotal = (int)($trip['veh_places'] ?? ($trip['places_disponibles'] ?? 0));

  // places occupées = participations en attente + confirmées
  $stOcc = $pdo->prepare("
  SELECT COALESCE(SUM(p.places),0)
  FROM participations p
  WHERE p.voyage_id = :v AND p.statut IN ('en_attente','confirme')");
  $stOcc->execute([':v' => $id]);
  $placesOccupees   = (int)$stOcc->fetchColumn();
  $placesRestantes  = max(0, $placesTotal - $placesOccupees);
}


if (!$trip) {
  http_response_code(404);
  include_once __DIR__ . '/../includes/header.php';
  echo '<main class="container my-4"><div class="alert alert-danger">Trajet introuvable.</div>';
  echo '<a class="btn btn-outline-secondary" href="' . (function_exists('url') ? e(url('trajets')) : '../trajets.php') . '">Retour</a></main>';
  include_once __DIR__ . '/../includes/footer.php';
  return;
}

// Déterminer si l'utilisateur est le conducteur
$isDriver = !empty($userId) && isset($trip['conducteur_id']) && ((int)$userId === (int)$trip['conducteur_id']);

// Participation de l'utilisateur courant (après chargement du trajet)
$myParticipation = null;
if (!empty($userId)) {
  $stMy = $pdo->prepare("
        SELECT id, statut, places
        FROM participations
        WHERE voyage_id = :v AND passager_id = :u
        ORDER BY id DESC LIMIT 1
    ");
  $stMy->execute([':v' => $id, ':u' => $userId]);
  $myParticipation = $stMy->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Existe-t-il une participation active (en_attente/confirme) ?
$hasActiveParticipation = false;
if (!empty($userId)) {
  $stAct = $pdo->prepare("SELECT 1 FROM participations WHERE voyage_id = :v AND passager_id = :u AND statut IN ('en_attente','confirme') LIMIT 1");
  $stAct->execute([':v' => $id, ':u' => $userId]);
  $hasActiveParticipation = (bool)$stAct->fetchColumn();
}

// Participants (adapter table: reservations/participants)
$participants = [];
if ($T_RES !== null) {
  try {
    $sqlParts = "
      SELECT u.id, u.pseudo AS p_pseudo, pr.photo_url AS p_photo, p.statut
      FROM participations p
      JOIN utilisateurs u  ON u.id = p.passager_id
      LEFT JOIN profils pr ON pr.utilisateur_id = u.id
      WHERE p.voyage_id = :v
        AND p.statut IN ('en_attente','confirme')
      ORDER BY p.inscrit_le ASC";
    $stp = $pdo->prepare($sqlParts);
    $stp->execute([':v' => $id]);
    $participants = $stp->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $participants = [];
  }
}


// Avis sur le conducteur (adapter: table avis, clé étrangère conducteur_id ou voyage_id)
$sqlReviews = "
  SELECT a.id, a.auteur_pseudo, a.note, a.commentaire, a.created_at
  FROM avis a
  WHERE a.conducteur_id = :u
  ORDER BY a.created_at DESC
  LIMIT 8";
try {
  $str = $pdo->prepare($sqlReviews);
  $str->execute([':u' => $trip['conducteur_id']]);
  $reviews = $str->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  // Table non présente → pas d'avis
  $reviews = [];
}

// Note moyenne (si avis)
$avgNote = null;
if ($reviews) {
  $sum = 0;
  $n = 0;
  foreach ($reviews as $r) {
    if (is_numeric($r['note'])) {
      $sum += (float)$r['note'];
      $n++;
    }
  }
  if ($n > 0) $avgNote = $sum / $n;
}

include_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-4">
  <?php if (function_exists('flashes')): foreach (flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
  <?php endforeach;
  endif; ?>

  <a href="<?= function_exists('url') ? e(url('trajets')) : '../trajets.php' ?>" class="btn btn-outline-secondary btn-sm mb-3" aria-label="Retour à la liste">&larr; Retour</a>

  <!-- A. Informations du conducteur -->
  <section class="card bg-dark text-white shadow rounded-4 mb-4" style="--bs-bg-opacity: .7; backdrop-filter: blur(2px);">
    <div class="card-body">
      <div class="row g-3 align-items-center">
        <div class="col-auto">
          <?php if (!empty($trip['photo_url'])): ?>
            <img src="<?= e($trip['photo_url']) ?>" alt="Avatar conducteur" class="rounded-circle" style="width:80px;height:80px;object-fit:cover;border:3px solid var(--bs-success);">
          <?php else: ?>
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;border:3px solid var(--bs-success);background: radial-gradient(circle at 50% 50%, var(--bs-primary) 0 42px, transparent 43px);">
              <i class="bi bi-person text-white" style="font-size:32px;"></i>
            </div>
          <?php endif; ?>
        </div>
        <div class="col">
          <h5 class="mb-1">
            <?= e($trip['pseudo']) ?>
            <?php $age = ageFrom($trip['date_naissance'] ?? null);
            if ($age !== ''): ?>
              <span class="text-success">&middot; <?= e($age) ?> ans</span>
            <?php endif; ?>
          </h5>
          <ul class="mb-2 text-light" style="--bs-text-opacity:.9;">
            <li>Permis depuis <em>inconnu</em></li>
            <li>Trajets réalisés: <em>—</em> &middot; Passagers: <em>—</em></li>
            <li>Note moyenne: <?= $avgNote !== null ? starsHtml($avgNote) . ' <span class="ms-1">' . number_format($avgNote, 1, ',', '') . '</span>' : '—' ?></li>
            <li>Véhicule: <?= e(trim(($trip['marque'] ?? '') . ' ' . ($trip['modele'] ?? ''))) ?><?php if (!empty($trip['couleur'])): ?>, <?= e($trip['couleur']) ?><?php endif; ?><?php if (!empty($trip['energie'])): ?>, <?= e($trip['energie']) ?><?php endif; ?></li>
            <li>Préférences: Fumeur <?= isset($trip['fumeur']) ? ((int)$trip['fumeur'] ? 'autorisé' : 'non autorisé') : '—' ?></li>
          </ul>

          <div class="d-flex gap-2">
            <?php if (!empty($trip['email'])): ?>
              <a href="mailto:<?= e($trip['email']) ?>" class="btn btn-outline-light" aria-label="Contacter le conducteur">Contacter</a>
            <?php else: ?>
              <button class="btn btn-outline-light" type="button" disabled title="Email non disponible" aria-label="Email non disponible">Contacter</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- B. Informations Trajet -->
  <section class="card shadow rounded-4 mb-4">
    <div class="card-body position-relative">
      <?php if (isset($trip['prix'])): ?>
        <span class="badge bg-success position-absolute top-0 end-0 translate-middle-y me-3 mt-3 fs-6"><?= e(number_format((int)$trip['prix'], 2, ',', ' ')) ?> </span>
      <?php endif; ?>

      <p class="mb-1"><strong>Places restantes:</strong> <?= $placesRestantes ?> / <?= $placesTotal ?></p>

      <p class="mb-1"><strong>Trajet:</strong> <?= e($trip['ville_depart']) ?> &rarr; <?= e($trip['ville_arrivee']) ?></p>
      <p class="mb-3"><strong>Départ:</strong> <?= e(fmtDateTime($trip['date_depart'] ?? null)) ?></p>
      <!-- -->
      <div class="d-flex flex-wrap gap-2 ">
        <?php if (!$userId): ?>
          <a href="<?= function_exists('url') ? e(url('connexion') . '?redirect=' . urlencode((function_exists('url') ? url('trajet') : 'trajet.php') . '?id=' . $id)) : '../connexion.php' ?>"
            class="btn btn-outline-primary">Se connecter pour participer</a>

        <?php elseif ($isDriver): ?>
          <button class="btn btn-success" disabled title="Vous êtes le conducteur">Participer</button>

        <?php elseif (!$hasActiveParticipation): ?>
          <form method="post" action="" class="d-inline">
            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
            <input type="hidden" name="action" value="participer">
            <button type="submit" class="btn btn-success">Participer</button>
          </form>

        
        <?php endif; ?>
      </div>
    </div>
    <!-- -->
  </section>

  <!-- C. Participants -->
  <section class="card shadow rounded-4 mb-4">
    <div class="card-body">
      <h5 class="card-title">Participants <small class="text-muted-admin ms-2">(<?= count($participants) ?>/<?= $placesTotal ?>)</small></h5>
      <?php if (!$participants): ?>
        <p class="text-muted-admin mb-0">Aucun participant pour le moment.</p>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($participants as $p): ?>
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($p['p_photo'])): ?>
                <img src="<?= e($p['p_photo']) ?>" alt="Avatar participant" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
              <?php else: ?>
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary text-white" style="width:40px;height:40px;">
                  <i class="bi bi-person"></i>
                </div>
              <?php endif; ?>
              <span><?= e($p['p_pseudo']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- D. Derniers avis -->
  <section class="card shadow rounded-4 mb-4">
    <div class="card-body">
      <h5 class="card-title">Derniers avis</h5>
      <?php if (!$reviews): ?>
        <p class="text-muted-admin mb-0">Pas encore d'avis.</p>
        <?php else: foreach ($reviews as $r): ?>
          <article class="mb-3 border-bottom pb-2">
            <div class="d-flex align-items-center justify-content-between">
              <strong><?= e($r['auteur_pseudo'] ?? 'Anonyme') ?></strong>
              <span><?= starsHtml($r['note'] ?? 0) ?></span>
            </div>
            <div class="text-muted-admin small mb-1">
              <?php try {
                $d = new DateTime($r['created_at']);
                echo e($d->format('d/m/Y H:i'));
              } catch (Throwable $e) {
                echo '';
              } ?>
            </div>
            <p class="mb-0"><?= e(mb_strimwidth((string)($r['commentaire'] ?? ''), 0, 220, '…', 'UTF-8')) ?></p>
          </article>
      <?php endforeach;
      endif; ?>
    </div>
  </section>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>