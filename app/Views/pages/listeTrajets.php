<?php include_once __DIR__ . "/../includes/header.php"; ?>

<!-- Section recherche -->

<section class="head search-hero">
    <div class="container py-3">
        <div class="text-center mb-3">
            <h1 class="titre-recherche d-inline-block px-3 py-2">Rechercher votre trajet</h1>
        </div>

        <?php
        $energie  = trim($_GET['energie']  ?? '');
        $prix_max = trim($_GET['prix_max'] ?? '');
        $fumeur   = trim($_GET['fumeur']   ?? '');
        $filtersOpen = ($energie !== '' || $prix_max !== '' || $fumeur !== '');
        ?>
        <form action="<?= url('trajets') ?>" method="get" class="forms-wrap">
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
                        aria-expanded="<?= $filtersOpen ? 'true' : 'false' ?>"
                        aria-controls="filtersCollapse">
                        Filtres avancés
                    </button>
                </div>

                <!-- Colonne droite -->
                <div class="col-12 col-lg-5">
                    <div id="filtersCollapse" class="collapse d-lg-flex filters-advanced <?= $filtersOpen ? 'show' : '' ?>">
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












<?php
// Récupération des critères (compatibilité noms existants)
$ville_depart  = trim((string)($_GET['ville_depart'] ?? $_GET['depart'] ?? ''));
$ville_arrivee = trim((string)($_GET['ville_arrivee'] ?? $_GET['arrivee'] ?? ''));
$date          = trim((string)($_GET['date'] ?? ''));
$energie       = trim((string)($_GET['energie'] ?? $_GET['motorisation'] ?? ''));
$prix_max      = trim((string)($_GET['prix_max'] ?? $_GET['prix'] ?? ''));
$fumeur        = trim((string)($_GET['fumeur'] ?? ''));
if ($fumeur === 'oui') { $fumeur = '1'; }
if ($fumeur === 'non') { $fumeur = '0'; }

$hasSearch = ($ville_depart !== '' || $ville_arrivee !== '' || $date !== '');

$trajets = [];
if ($hasSearch) {
    $pdo = $GLOBALS['db'] ?? (function_exists('db') ? db() : null);
    if ($pdo instanceof PDO) {
        $joins = "\n    FROM voyages v\n    JOIN utilisateurs u  ON u.id = v.chauffeur_id\n    LEFT JOIN profils pr ON pr.utilisateur_id = u.id\n    JOIN vehicules ve    ON ve.id = v.vehicule_id\n    LEFT JOIN preferences pf ON pf.utilisateur_id = u.id\n";

        $where = [];
        $params = [];
        $where[] = "v.places_disponibles > 0";
        $where[] = "v.statut = 'ouvert'";

        if ($ville_depart !== '') {
            $where[] = "v.ville_depart LIKE :vd";
            $params[':vd'] = '%' . $ville_depart . '%';
        }
        if ($ville_arrivee !== '') {
            $where[] = "v.ville_arrivee LIKE :va";
            $params[':va'] = '%' . $ville_arrivee . '%';
        }
        if ($date !== '') {
            $where[] = "DATE(v.date_depart) = :d";
            $params[':d'] = $date;
        }

        if ($energie !== '') {
            $where[] = "ve.energie = :energie";
            $params[':energie'] = $energie;
        }

        if ($prix_max !== '' && is_numeric($prix_max)) {
            $where[] = "v.prix <= :prix_max";
            $params[':prix_max'] = (float)$prix_max;
        }

        if ($fumeur !== '' && ($fumeur === '0' || $fumeur === '1')) {
            $where[] = "pf.fumeur = :fumeur";
            $params[':fumeur'] = (int)$fumeur;
        }

        $sql = "\n  SELECT\n    v.id, v.ville_depart, v.ville_arrivee,\n    v.date_depart, v.date_arrivee, v.prix, v.places_disponibles, v.statut, v.ecologique,\n    u.pseudo, u.prenom, u.nom,\n    pr.date_naissance, pr.photo_url,\n    ve.marque, ve.modele, ve.couleur, ve.energie\n  $joins\n  " . (count($where) ? 'WHERE ' . implode(' AND ', $where) : '') . "\n  ORDER BY v.date_depart ASC\n  LIMIT 60\n";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
?>

<section class="trajets-list container my-4">
  <?php if (!$hasSearch): ?>
    <div class="alert alert-light" role="alert">Renseignez au moins un critère pour lancer une recherche.</div>
  <?php else: ?>
    <?php if (empty($trajets)): ?>
      <div class="alert alert-info" role="alert">Aucun trajet trouvé pour ces critères.</div>
    <?php else: ?>
      <div class="trajets-grid">
        <?php foreach ($trajets as $row): ?>
          <?php
          $avatar = '';
          if (!empty($row['photo_url'])) {
              $p = (string)$row['photo_url'];
              if (strpos($p, '/uploads/') === 0) {
                  $avatar = rtrim(BASE_URL, '/') . $p;
              } elseif (preg_match('#^https?://#i', $p)) {
                  $avatar = $p;
              } elseif (strpos($p, BASE_URL . '/uploads/') === 0) {
                  $avatar = $p;
              } else {
                  $avatar = rtrim(BASE_URL, '/') . '/' . ltrim($p, '/');
              }
          }

          $ageText = '';
          if (!empty($row['date_naissance'])) {
              $dob = DateTime::createFromFormat('Y-m-d', substr((string)$row['date_naissance'], 0, 10));
              if ($dob instanceof DateTime) {
                  $now = new DateTime();
                  $age = (int)$now->format('Y') - (int)$dob->format('Y');
                  if ((int)$now->format('md') < (int)$dob->format('md')) { $age--; }
                  if ($age >= 0) { $ageText = ', ' . $age . ' ans'; }
              }
          }

          $dtText = '';
          if (!empty($row['date_depart'])) {
              try {
                  $dt = new DateTime((string)$row['date_depart']);
                  $dtText = $dt->format('d/m/Y H:i');
              } catch (Exception $e) {
                  $dtText = '';
              }
          }

          $prix = '';
          if (isset($row['prix'])) {
              $p = (string)$row['prix'];
              if (is_numeric($p)) {
                  $p = rtrim(rtrim((string)number_format((float)$p, 2, '.', ''), '0'), '.');
                  $prix = $p . '€';
              }
          }

          $vehTxt = trim(implode(' ', array_filter([
              (string)$row['marque'],
              (string)$row['modele'],
          ])));
          if (!empty($row['couleur'])) { $vehTxt .= ' · ' . (string)$row['couleur']; }
          $vehTxt .= ' · ' . (string)$row['energie'];

          $reserveUrl = function_exists('url') ? url('trajet/' . (int)$row['id']) : '#';
          ?>

          <article class="trajet-card card">
            <?php if (!empty($row['ecologique'])): ?>
              <span class="trajet-card__eco badge bg-success">Éco</span>
            <?php endif; ?>
            <div class="card-body">
              <div class="d-flex align-items-center gap-3">
                <div class="trajet-card__avatar">
                  <?php if ($avatar !== ''): ?>
                    <img src="<?= e($avatar) ?>" alt="Avatar de <?= e($row['pseudo']) ?>">
                  <?php else: ?>
                    <?php
                      if (!function_exists('initials_from_names')) {
                        function initials_from_names($prenom, $nom, $pseudo = '') {
                          $prenom = trim((string)$prenom);
                          $nom    = trim((string)$nom);
                          $letters = '';
                          if (function_exists('mb_strtoupper') && function_exists('mb_substr')) {
                            if ($prenom !== '') $letters .= mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8');
                            if ($nom !== '')    $letters .= mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8');
                            if ($letters === '') {
                              $s = trim((string)$pseudo);
                              if ($s !== '') {
                                $clean  = preg_replace('/[^\pL\s\-_.]+/u', '', $s);
                                $parts  = preg_split('/[\s\-_.]+/u', (string)$clean, -1, PREG_SPLIT_NO_EMPTY);
                                foreach ($parts as $p) {
                                  $letters .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
                                  if (mb_strlen($letters, 'UTF-8') >= 2) break;
                                }
                                if ($letters === '') $letters = mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8');
                              }
                            }
                          } else {
                            if ($prenom !== '') $letters .= strtoupper(substr($prenom, 0, 1));
                            if ($nom !== '')    $letters .= strtoupper(substr($nom, 0, 1));
                            if ($letters === '') {
                              $s = trim((string)$pseudo);
                              if ($s !== '') {
                                $clean  = preg_replace('/[^A-Za-z0-9\s\-_.]+/', '', $s);
                                $parts  = preg_split('/[\s\-_.]+/', (string)$clean, -1, PREG_SPLIT_NO_EMPTY);
                                foreach ($parts as $p) {
                                  $letters .= strtoupper(substr($p, 0, 1));
                                  if (strlen($letters) >= 2) break;
                                }
                                if ($letters === '') $letters = strtoupper(substr($s, 0, 1));
                              }
                            }
                          }
                          return $letters !== '' ? $letters : '?';
                        }
                      }
                    ?>
                    <span class="avatar-initials"><?= e(initials_from_names($row['prenom'] ?? '', $row['nom'] ?? '', $row['pseudo'] ?? '')) ?></span>
                  <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                  <div class="trajet-card__title"><?= e($row['pseudo']) ?><?= e($ageText) ?></div>
                  <div class="trajet-card__route"><?= e($row['ville_depart']) ?> → <?= e($row['ville_arrivee']) ?></div>
                  <?php if ($dtText !== ''): ?>
                    <div class="trajet-card__datetime trajet-info small mt-1">Départ: <?= e($dtText) ?></div>
                  <?php endif; ?>
                  <div class="vehicule-badge mt-2"><?= e($vehTxt) ?></div>
                </div>
              </div>

              <div class="trajet-card__footer mt-3">
                <span class="trajet-infod">Places restantes: <strong><?= (int)$row['places_disponibles'] ?></strong></span>
                <a href="<?= e($reserveUrl) ?>" class="btn btn-success btn-sm">Réserver</a>
              </div>

              <?php if ($prix !== ''): ?>
                <div class="trajet-card__price"><?= e($prix) ?></div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>




<?php include_once __DIR__ . "/../includes/footer.php"; ?>
