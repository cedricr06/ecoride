<?php include_once __DIR__ . '/../includes/header.php'; ?>



<div class="container profile mt-4">
  <div class="row g-4">
    <!-- Sidebar -->
    <aside class="col-12 col-lg-4">
      <!-- Carte utilisateur -->
      <div class="card profile__card">
        <div class="card-body d-flex align-items-center gap-3">

          <div class="profile__avatar">
            <?php if (!empty($user['avatar_path'])): ?>
              <?php
              $p = (string)$user['avatar_path'];

              // Si la BDD contient /uploads/... on préfixe avec BASE_URL
              if (strpos($p, '/uploads/') === 0) {
                $src = BASE_URL . $p;
              }
              // Si c'est déjà une URL absolue (http/https) on garde tel quel
              elseif (preg_match('#^https?://#i', $p)) {
                $src = $p;
              }
              // Si par erreur la BDD a déjà le préfixe (/Projet_ecoride/public/...)
              // on ne préfixe pas une seconde fois :
              elseif (strpos($p, BASE_URL . '/uploads/') === 0) {
                $src = $p;
              }
              // Dernier recours : on tente tel quel
              else {
                $src = $p;
              }
              ?>
              <img
                src="<?= e($src) ?>"
                alt="Avatar de <?= e($user['pseudo'] ?? (($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))) ?>"
                class="avatar-img">
            <?php else: ?>
              <?= strtoupper(mb_substr($user['prenom'] ?? 'U', 0, 1) . mb_substr($user['nom'] ?? 'N', 0, 1)) ?>
            <?php endif; ?>
          </div>

          <div>
            <h2 class="h5 mb-1"><?= e($user['pseudo'] ?? 'Utilisateur') ?></h2>
            <p class="infos-profil mail-profil mb-0 small"><?= e($user['nom'] ?? '') ?> <?= e($user['prenom'] ?? '') ?></p>
            <p class="infos-profil mail-profil mb-0 small"><?= e($user['email'] ?? '') ?></p>

            <ul class="list-unstyled small info-profil  mb-0">
              <?php if ($age !== null): ?>
                <li>Âge : <strong><?= e($age) ?> ans</strong></li>
              <?php endif; ?>
              <?php if ($permisYears !== null): ?>
                <li>Permis depuis : <strong><?= $permisYears ?> ans</strong></li>
              <?php endif; ?>
              <?php if (!empty($profil['ville'])): ?>
                <li>Ville : <?= e($profil['ville']) ?></li>
              <?php endif; ?>
              <?php if (!empty($profil['telephone'])): ?>
                <li>Tél. : <?= e($profil['telephone']) ?></li>
              <?php endif; ?>
            </ul>

            <!-- Formulaire de changement d'avatar -->
            <form action="<?= url('profil/avatar') ?>" method="post" enctype="multipart/form-data" class="mt-2">
              <?= csrf_field() ?>
              <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm d-none" id="avatarInput" onchange="this.form.submit()">
              <label for="avatarInput" class="btn btn-sm btn-outline-success">Changer la photo</label>
            </form>

          </div>
        </div>
      </div>
      <!-- Préférences chauffeur (afficher si chauffeur) -->

      <div class="card profile__card">
        <div class="card-body">
          <h3 class="h6 mb-3">Préférences</h3>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php
            $chips = [];
            $chips[] = match (($prefs['role_covoiturage'] ?? 'passager')) {
              'chauffeur' => 'Chauffeur',
              'lesdeux'   => 'Chauffeur & Passager',
              default     => 'Passager',
            };

            if (isset($prefs['fumeur']))  $chips[] = ($prefs['fumeur'] ? 'Fumeur OK' : 'Non-fumeur');
            if (isset($prefs['animaux'])) $chips[] = ($prefs['animaux'] ? 'Animaux OK' : 'Sans animaux');

            // 🔹 Aime parler
            if (isset($prefs['aime_parler'])) {
              $chips[] = $prefs['aime_parler'] ? 'Aime discuter' : 'Plutôt discret';
            }

            // Musique (avec défaut "silence")
            $musique = $prefs['musique_niveau'] ?? 'silence';
            $mapMusique = [
              'silence' => 'Musique: Silence',
              'douce'   => 'Musique: douce',
              'normale' => 'Musique: normale',
              'forte'   => 'Musique: forte',
            ];
            $chips[] = $mapMusique[$musique] ?? 'Musique: Silence';

            if (isset($prefs['autre_pref'])) $chips[] = e($prefs['autre_pref']);

            foreach ($chips as $c): ?>
              <span class="badge profile__chip"><?= e($c) ?></span>
            <?php endforeach; ?>
            <!-- Affichage des autres préférences en bas à gauche -->
            <?php $autre = isset($prefs['autre_pref']) ? trim((string)$prefs['autre_pref']) : ''; ?>

          </div>
        </div>
      </div>

      <!-- Crédits -->
      <div class="card profile__card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0">Crédits</h3>
            <span class="badge rounded-pill bg-success-subtle text-success fw-semibold credit-pill">
              <?= (int)($user['credits'] ?? 0) ?> crédits
            </span>
          </div>
          <p class="small infos-profil mt-2 mb-0">Gain de 2 crédits par passager transporté.</p>
        </div>
      </div>




    </aside>

    <!-- Main -->
    <main class="col-12 col-lg-8">
      <div class="card profile__card">
        <div class="card-body">
          <!-- Onglets -->
          <ul class="nav nav-tabs profile__tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-voyages" type="button" role="tab">
                Voyages
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-vehicules" type="button" role="tab">
                Véhicules
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parametres" type="button" role="tab">
                Paramètres
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">
                Infos
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-preferences" type="button" role="tab">
                Préférences
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-securite" type="button" role="tab">
                Sécurité
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-role" type="button" role="tab">
                rôle
              </button>
            </li>
          </ul>

          <div class="tab-content mt-3">
            <!-- VOYAGES -->
            <div class="tab-pane fade show active" id="tab-voyages" role="tabpanel">
              <?php if (empty($voyages)): ?>
                <div class="text-center infos-profil py-5">Aucun voyage pour le moment.</div>
              <?php else: ?>
                <div class="list-group profile__list">
                  <?php foreach ($voyages as $v): ?>
                    <a href="<?= url('trajet/' . $v['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                      <div>
                        <strong><?= e($v['depart']) ?> → <?= e($v['arrivee']) ?></strong>
                        <div class="small infos-profil"><?= e($v['date']) ?> • <?= e($v['heure']) ?> • <?= (int)$v['prix'] ?> pts</div>
                      </div>
                      <span class="badge <?= $v['eco'] ? 'badge-eco' : 'bg-secondary-subtle text-secondary' ?>">
                        <?= $v['eco'] ? 'Éco' : 'Standard' ?>
                      </span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- VEHICULES -->
            <div class="tab-pane fade" id="tab-vehicules" role="tabpanel">

              <!-- Bouton qui ouvre/ferme le formulaire d’ajout -->
              <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-success"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#vehicule-form">
                  Ajouter un véhicule
                </button>
              </div>

              <!-- Formulaire d’ajout (collapse) -->
              <div id="vehicule-form" class="collapse">
                <form class="card card-body border-0 shadow-sm mb-3"
                  method="post"
                  action="<?= url('profil/vehicules/ajouter') ?>">
                  <?= csrf_field() ?>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Marque</label>
                      <input name="marque" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Modèle</label>
                      <input name="modele" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Couleur</label>
                      <input name="couleur" class="form-control">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Énergie</label>
                      <select name="energie" class="form-select" required>
                        <option value="" disabled selected>—</option>
                        <option>Essence</option>
                        <option>Diesel</option>
                        <option>Hybride</option>
                        <option>Électrique</option>
                        <option>GPL</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Places</label>
                      <input type="number" min="1" max="9" name="places" class="form-control" value="4" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">immatriculation</label>
                      <input type="number" min="9" max="9" name="imatriculation" class="form-control" value="4" required>
                    </div>
                    <div class="col-md-8">
                      <label class="form-label">Date 1re immatriculation</label>
                      <input type="date" name="date_premiere_immatriculation" class="form-control">
                    </div>
                    <div class="col-12">
                      <button class="btn btn-success" type="submit">Enregistrer le véhicule</button>
                    </div>
                  </div>
                </form>
              </div>


              <?php if (empty($vehicules)): ?>
                <p class="infos-profil">Aucun véhicule pour l’instant.</p>
              <?php else: ?>
                <div class="row g-3">
                  <?php foreach ($vehicules as $car): ?>
                    <div class="col-12">
                      <div class="card profile__vehicle">
                        <div class="card-body">
                          <div class="d-flex justify-content-between align-items-start">
                            <div>
                              <strong><?= e($car['marque'] . ' ' . $car['modele'] . ' ' . $car['couleur']) ?> </strong>
                              <?php if (strtolower($car['energie']) === 'electrique'): ?>
                                <span class="badge badge-eco">Écologique</span>
                              <?php endif; ?>
                              <div class="small infos-profil">
                                <p><?= e($car['energie']) ?> • <?= (int)$car['places'] ?> places</p>
                                <p>Immatriculation du véhicule: <?= e($car['immatriculation']) ?> <br>
                                  Date de première immatriculation: <?= e($car['date_premiere_immatriculation'] ?? '') ?></p>
                              </div>
                            </div>
                            <div class="d-flex flex-column gap-2  align-items-start ">

                              <!-- Modifier (ouvre un formulaire inline) -->
                              <button class="btn btn-outline-success btn-sm btn-vehicule"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#edit-vehicule-<?= (int)$car['id'] ?>">
                                Modifier
                              </button>

                              <!-- Supprimer (POST) -->
                              <form action="<?= url('profil/vehicules/' . (int)$car['id'] . '/delete') ?>" method="post" class="m-0">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger btn-sm" type="submit">Supprimer</button>
                              </form>
                            </div>
                          </div>

                          <!-- Formulaire d’édition (collapse par véhicule) -->
                          <div id="edit-vehicule-<?= (int)$car['id'] ?>" class="collapse mt-3">
                            <form class="card card-body border-0 shadow-sm"
                              method="post"
                              action="<?= url('profil/vehicules/' . (int)$car['id'] . '/edit') ?>">
                              <?= csrf_field() ?>
                              <div class="row g-3">
                                <div class="col-md-3">
                                  <label class="form-label">Marque</label>
                                  <input name="marque" class="form-control" value="<?= e($car['marque']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                  <label class="form-label">Modèle</label>
                                  <input name="modele" class="form-control" value="<?= e($car['modele']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                  <label class="form-label">Couleur</label>
                                  <input name="couleur" class="form-control" value="<?= e($car['couleur']) ?>">
                                </div>

                                <div class="col-md-3">
                                  <label class="form-label">Places</label>
                                  <input type="number" min="1" max="9" name="places" class="form-control" value="<?= (int)$car['places'] ?>" required>
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label">Énergie</label>
                                  <select name="energie" class="form-select" required>
                                    <?php
                                    $energies = ['Essence', 'Diesel', 'Hybride', 'Électrique', 'GPL'];
                                    foreach ($energies as $eOpt):
                                      $sel = (strcasecmp($car['energie'], $eOpt) === 0) ? 'selected' : '';
                                      echo '<option ' . $sel . '>' . e($eOpt) . '</option>';
                                    endforeach;
                                    ?>
                                  </select>
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label">Immatriculation</label>
                                  <input name="immatriculation" class="form-control" required maxlength="10" pattern="^[A-Z]{2}-\d{3}-[A-Z]{2}$|^\d{1,4}\s?[A-Z]{2,3}\s?\d{2,3}$" title="Format ex : AB-123-CD (actuel) ou 123 ABC 45 (ancien)">
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label">Date 1re immatriculation</label>
                                  <input type="date" name="date_premiere_immatriculation" class="form-control"
                                    value="<?= e($car['date_premiere_immatriculation'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                  <button class="btn btn-success btn-sm" type="submit">Enregistrer</button>
                                </div>
                              </div>
                            </form>
                          </div>

                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- PARAMETRES -->
            <div class="tab-pane fade" id="tab-parametres" role="tabpanel">
              <form class="row g-3" method="post" action="<?= url('profil/update') ?>">
                <?= csrf_field() ?>
                <div class="col-md-6">
                  <label class="form-label">Prénom</label>
                  <input class="form-control" name="prenom" value="<?= e($user['prenom'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Nom</label>
                  <input class="form-control" name="nom" value="<?= e($user['nom'] ?? '') ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" value="<?= e($user['email'] ?? '') ?>">
                </div>
                <div class="col-12">
                  <button class="btn btn-success">Enregistrer</button>
                </div>
              </form>
            </div>

            <!--Infos -->
            <div class="tab-pane fade" id="tab-info" role="tabpanel">
              <form action="<?= url('profil/enregistrer') ?>" method="post" class="mt-3">
                <?= csrf_field() ?>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Date de naissance</label>
                    <input type="date" name="date_naissance" class="form-control"
                      value="<?= e($profil['date_naissance'] ?? '') ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Date d’obtention du permis</label>
                    <input type="date" name="date_permis" class="form-control"
                      value="<?= e($profil['date_permis'] ?? '') ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" name="telephone" class="form-control"
                      value="<?= e($profil['telephone'] ?? '') ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Ville</label>
                    <input type="text" name="ville" class="form-control"
                      value="<?= e($profil['ville'] ?? '') ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">À propos</label>
                    <textarea name="bio" rows="2" maxlength="280" class="form-control"><?= e($profil['bio'] ?? '') ?></textarea>
                  </div>
                  <div class="col-12 d-flex gap-3">
                    <label class="form-check">
                      <input class="form-check-input" type="checkbox" name="verifie_identite" <?= !empty($profil['verifie_identite']) ? 'checked' : ''; ?>>
                      <span class="form-check-label">Identité vérifiée</span>
                    </label>
                    <label class="form-check">
                      <input class="form-check-input" type="checkbox" name="verifie_tel" <?= !empty($profil['verifie_tel']) ? 'checked' : ''; ?>>
                      <span class="form-check-label">Téléphone vérifié</span>
                    </label>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-success">Enregistrer</button>
                  </div>
                </div>
              </form>
            </div>

            <!-- PREFERENCES -->
            <div class="tab-pane fade" id="tab-preferences" role="tabpanel">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h3 class="h6 mb-0">Préférences</h3>
                  <button class="btn btn-sm btn-success"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#prefs-form">
                    Gérer
                  </button>
                </div>

                <!-- Affichage rapide des prefs -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <?php
                  $chips = [];
                  if (isset($prefs['fumeur']))  $chips[] = ($prefs['fumeur'] ? 'Fumeur OK' : 'Non-fumeur');
                  if (isset($prefs['animaux'])) $chips[] = ($prefs['animaux'] ? 'Animaux OK' : 'Sans animaux');
                  if (isset($prefs['aime_parler'])) $chips[] = ($prefs['aime_parler'] ? 'Aime discuter' : 'Plutôt discret');
                  if (!empty($prefs['autre_pref'])) $chips[] = e($prefs['autre_pref']);
                  if (empty($chips)) $chips = ['Aucune préférence'];

                  $musique = $prefs['musique_niveau'] ?? 'silence';

                  $map = [
                    'silence' => 'Musique: Silence',
                    'douce'   => 'Musique: douce',
                    'normale' => 'Musique: normale',
                    'forte'   => 'Musique: forte',
                  ];

                  // Ajoute le chip
                  $chips[] = $map[$musique] ?? 'Musique: Silence';
                  foreach ($chips as $c): ?>
                    <span class="badge profile__chip"><?= e($c) ?></span>
                  <?php endforeach; ?>
                </div>

                <!-- Formulaire (collapse) -->
                <div id="prefs-form" class="collapse">
                  <form class="card card-body border-0 shadow-sm"
                    method="post"
                    action="<?= url('profil/preferences/save') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label d-block mb-1">Fumeur</label>
                        <div class="btn-group" role="group">
                          <input class="btn-check" type="radio" name="fumeur" id="pref-fumeur-non" value="0"
                            <?= (string)($prefs['fumeur'] ?? '0') === '0' ? 'checked' : '' ?>>
                          <label class="btn btn-outline-secondary" for="pref-fumeur-non">Non</label>

                          <input class="btn-check" type="radio" name="fumeur" id="pref-fumeur-oui" value="1"
                            <?= (string)($prefs['fumeur'] ?? '') === '1' ? 'checked' : '' ?>>
                          <label class="btn btn-outline-secondary" for="pref-fumeur-oui">Oui</label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label d-block mb-1">Animaux</label>
                        <div class="btn-group" role="group">
                          <input class="btn-check" type="radio" name="animaux" id="pref-anim-non" value="0"
                            <?= (string)($prefs['animaux'] ?? '0') === '0' ? 'checked' : '' ?>>
                          <label class="btn btn-outline-secondary" for="pref-anim-non">Sans</label>

                          <input class="btn-check" type="radio" name="animaux" id="pref-anim-oui" value="1"
                            <?= (string)($prefs['animaux'] ?? '') === '1' ? 'checked' : '' ?>>
                          <label class="btn btn-outline-secondary" for="pref-anim-oui">OK</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label d-block mb-1">Aime parler</label>
                        <div class="btn-group" role="group">
                          <input class="btn-check" type="radio" name="aime_parler" id="talk-non" value="0"
                            <?= (string)($prefs['aime_parler'] ?? '0') === '0' ? 'checked' : '' ?>>
                          <label class="btn btn-outline-secondary" for="talk-non">Plutôt discret</label>

                          <input class="btn-check" type="radio" name="aime_parler" id="talk-oui" value="1"
                            <?= (string)($prefs['aime_parler'] ?? '') === '1' ? 'checked' : '' ?>>
                          <label class="btn btn-outline-secondary" for="talk-oui">Aime discuter</label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label">Musique</label>
                        <select name="musique_niveau" class="form-select">
                          <?php
                          $niv = $prefs['musique_niveau'] ?? '';
                          $opts = ['silence' => 'Silence', 'douce' => 'Musique douce', 'normale' => 'Normale', 'forte' => 'Forte'];
                          echo '<option value="">—</option>';
                          foreach ($opts as $k => $label) {
                            $sel = ($niv === $k) ? 'selected' : '';
                            echo "<option value=\"$k\" $sel>" . e($label) . "</option>";
                          }
                          ?>
                        </select>
                      </div>

                      <div class="col-12">
                        <label class="form-label">Autres préférences</label>
                        <textarea name="autre_pref" rows="2" maxlength="120" class="form-control"
                          placeholder="Ex : Pause café…"><?= e($prefs['autre_pref'] ?? '') ?></textarea>
                      </div>

                      <div class="col-12">
                        <button class="btn btn-success" type="submit">Enregistrer</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>


            <!-- SECURITE -->
            <div class="tab-pane fade" id="tab-securite" role="tabpanel">
              <form class="row g-3" method="post" action="<?= url('profil/password') ?>">
                <?= csrf_field() ?>
                <div class="col-12 col-md-6">
                  <label class="form-label">Nouveau mot de passe</label>
                  <input type="password" name="password" class="form-control" required minlength="8">
                  <div class="form-text">min 8 caractères.</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Confirmer</label>
                  <input type="password" name="password_confirm" class="form-control" required>
                </div>
                <div class="col-12">
                  <button class="btn btn-success">Mettre à jour le mot de passe</button>
                </div>
              </form>
            </div>

            <!-- Rôles -->

            <div class="tab-pane fade" id="tab-role" role="tabpanel">
              <h3 class="h6 mb-3">Rôle</h3>
              <form method="post" action="<?= url('profil/preferences/save') ?>">
                <?= csrf_field() ?>
                <div class="btn-group w-100">
                  <input class="btn-check" type="radio" name="role_covoiturage" id="rc-passager" value="passager"
                    <?= ($prefs['role_covoiturage'] ?? 'passager') === 'passager' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success" for="rc-passager">Passager</label>

                  <input class="btn-check" type="radio" name="role_covoiturage" id="rc-chauffeur" value="chauffeur"
                    <?= ($prefs['role_covoiturage'] ?? '') === 'chauffeur' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success" for="rc-chauffeur">Chauffeur</label>

                  <input class="btn-check" type="radio" name="role_covoiturage" id="rc-lesdeux" value="lesdeux"
                    <?= ($prefs['role_covoiturage'] ?? '') === 'lesdeux' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success" for="rc-lesdeux">Les deux</label>
                </div>
                <button class="btn btn-success w-100 mt-3" type="submit">Mettre à jour</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>


<div class="page-profil">
  <?php include_once __DIR__ . '/../includes/footer.php'; ?>
</div>