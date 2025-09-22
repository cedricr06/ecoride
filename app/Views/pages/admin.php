<?php include_once __DIR__ . '/../includes/header.php'; ?>

<?php
// garde-fous (au cas où)
$tab     = $tab     ?? 'dashboard';
$total   = $total   ?? 0;
$pending = $pending ?? [];
$pages   = $pages   ?? 0;
$page    = $page    ?? 1;
$createAdminErrors  = is_array($createAdminErrors ?? null) ? $createAdminErrors : [];
$createAdminOld     = is_array($createAdminOld ?? null) ? $createAdminOld : [];
$createAdminSuccess = $createAdminSuccess ?? '';
$createAdminOld     = array_merge(['email' => '', 'pseudo' => ''], $createAdminOld);
?>

<?php
// Expects: $dashboard, $users, $stats
$admin = $_SESSION['user'] ?? [];
?>

<div class="container profile mt-4">
  <div class="row g-4 admin-grid">
    <!-- Sidebar -->
    <aside class="col-12 col-lg-4">
      <div class="top-cards">

        <!-- Carte gauche : identité admin -->
        <div class="card profile__card">
          <div class="card-body admin-identity">
            <div class="identity-text">
              <h2 class="h5 mb-1">Administrateur</h2>
              <p class="mb-0 small"><?= e($admin['prenom'] ?? '') ?> <?= e($admin['nom'] ?? '') ?> <?= e($admin['pseudo'] ?? '') ?></p>
              <p class="mb-0 small"><?= e($admin['email'] ?? '') ?></p>
              <span class="badge bg-success mt-2">Rôle: Administrateur</span>
            </div>
          </div>
        </div>

        <!-- Carte droite : aperçu rapide -->
        <div class="card profile__card">
          <div class="card-body ">
            <h3 class="h6 mb-3 ">Aperçu rapide</h3>
            <ul class="list-unstyled small mb-0">
              <li>Total utilisateurs: <strong><?= (int)($dashboard['users_total'] ?? 0) ?></strong></li>
              <li>Utilisateurs suspendus: <strong><?= (int)($dashboard['users_suspended'] ?? 0) ?></strong></li>
              <li>Trajets aujourd'hui: <strong><?= (int)($dashboard['trips_today'] ?? 0) ?></strong></li>
              <li>Crédits (total): <strong><?= (int)($dashboard['site_wallet_balance'] ?? 0) ?></strong></li>
            </ul>
          </div>
        </div>

      </div>
    </aside>

    <!-- Main -->
    <main class="col-12 col-lg-8">
      <div class="card profile__card">
        <div class="card-body">
          <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-dashboard-tab" data-bs-toggle="tab" data-bs-target="#tab-dashboard" type="button" role="tab" aria-controls="tab-dashboard" aria-selected="true">Tableau de bord</button>
            </li>          
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-stats-tab" data-bs-toggle="tab" data-bs-target="#tab-stats" type="button" role="tab" aria-controls="tab-stats" aria-selected="false">Statistiques</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab" aria-controls="tab-users" aria-selected="false">Utilisateurs</button>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="avis-en-attente-tab" data-bs-toggle="tab" href="#avis-en-attente" role="tab" aria-controls="avis-en-attente" aria-selected="false" data-count="<?= (int)$total ?>">Avis en attente (<?= (int)$total ?>)</a>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-settings-tab" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab" aria-controls="tab-settings" aria-selected="false">Creer compte</button>
            </li>
          </ul>

          <div class="tab-content pt-3">
            <?php if (function_exists('flashes')): ?>
              <?php $msgs = flashes(); ?>
              <?php if (!empty($msgs)): ?>
                <div class="mb-3">
                  <?php foreach ($msgs as $m): ?>
                    <div class="alert alert-<?= e($m['type'] ?? 'info') ?>" role="alert">
                      <?= e($m['message'] ?? '') ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
            <!-- Dashboard -->
            <div class="tab-pane fade show active" id="tab-dashboard" role="tabpanel" aria-labelledby="tab-dashboard-tab">
              <div class="row row-cols-2  g-3">
                <div class="col-6">
                  <div class="card">
                    <div class="card-body text-center h-100">
                      <div class=" info-tab-admin small">Utilisateurs</div>
                      <div class="display-6"><?= (int)($dashboard['users_total'] ?? 0) ?></div>
                    </div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="card">
                    <div class="card-body text-center h-100">
                      <div class=" info-tab-admin small">Suspendus</div>
                      <div class="display-6"><?= (int)($dashboard['users_suspended'] ?? 0) ?></div>
                    </div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="card">
                    <div class="card-body text-center h-100">
                      <div class=" info-tab-admin small">Trajets aujourd'hui</div>
                      <div class="display-6"><?= (int)($dashboard['trips_today'] ?? 0) ?></div>
                    </div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="card">
                    <div class="card-body text-center h-100">
                      <div class=" info-tab-admin small">Cagnotte du site</div>
                      <div class="display-6"><?= (int)($dashboard['site_wallet_balance'] ?? 0) ?></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Users -->
            <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="tab-users-tab">
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr class="titre-tableau">
                      <th>ID</th>
                      <th>Pseudo</th>
                      <th>Email</th>
                      <th>Crédits</th>
                      <th>Rôle</th>
                      <th>Statut</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (($users ?? []) as $u): ?>
                      <tr class="tableau-utilisateurs">
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= e($u['pseudo']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= (int)$u['credits'] ?></td>
                        <td><?= e($u['role'] ?? 'utilisateur') ?></td>
                        <td>
                          <?php if (!empty($u['est_suspendu'])): ?>
                            <span class="badge bg-danger">Suspendu</span>
                          <?php else: ?>
                            <span class="badge bg-success">Actif</span>
                          <?php endif; ?>
                        </td>
                        <td class="actions">
                          <?php if (!empty($u['est_suspendu'])): ?>
                            <form method="post" action="<?= url('admin/utilisateurs/reactiver') ?>" class="d-inline">
                              <?= csrf_field() ?>
                              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                              <button class="btn  btn-admin btn-success" type="submit">Réactiver</button>
                            </form>
                          <?php else: ?>
                            <form method="post" action="<?= url('admin/utilisateurs/suspendre') ?>" class="d-inline" onsubmit="return confirm('Suspendre cet utilisateur ?');">
                              <?= csrf_field() ?>
                              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                              <button class="btn  btn-admin btn-danger" type="submit">Suspendre</button>
                            </form>
                          <?php endif; ?>

                          <?php $isSelf = ((int)$u['id'] === (int)($_SESSION['user']['id'] ?? 0)); ?>
                          <form method="post" action="<?= url('admin/utilisateurs/supprimer') ?>" class="d-inline" onsubmit="return confirm('Supprimer définitivement cet utilisateur ? Cette action est irréversible.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-admin btn-outline-danger" type="submit" <?= $isSelf ? 'disabled title="Vous ne pouvez pas vous supprimer"' : '' ?>>Supprimer</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Stats -->
            <div class="tab-pane fade" id="tab-stats" role="tabpanel" aria-labelledby="tab-stats-tab">
              <div id="admin-stats-ctx" data-endpoint="<?= url('admin/stats') ?>" data-days="<?= (int)($statsDays ?? 7) ?>"></div>
              <div class="row g-3 ">
                <div class="col-12 ">
                  <div class="card ">
                    <div class="card-body">
                      <h3 class="h6">Covoiturages par jour</h3>
                      <canvas class="chart-warp" id="ridesChart" height="160"  ></canvas>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="card">
                    <div class="card-body ">
                      <h3 class="h6 d-flex justify-content-between align-items-center">
                        <span>Crédits gagnés par jour</span>
                      </h3>
                      <canvas class="chart-warp" id="revenueChart" height="160"></canvas>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Avis coté admin -->


            <div class="tab-pane fade" id="avis-en-attente" role="tabpanel" aria-labelledby="avis-en-attente-tab">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0">Avis en attente</h5>

                <form class="d-flex gap-2" method="get">
                  <input type="hidden" name="tab" value="pending">
                  <input class="form-control form-control-sm" type="number" min="1" max="5" name="min"
                    value="<?= htmlspecialchars((string)($_GET['min'] ?? '')) ?>" placeholder="Note min.">
                  <input class="form-control form-control-sm" type="number" name="driver"
                    value="<?= htmlspecialchars((string)($_GET['driver'] ?? '')) ?>" placeholder="Driver ID">
                  <button class="btn btn-sm btn-outline-secondary">Filtrer</button>
                </form>
              </div>

              <div id="pending-empty" class="alert alert-success<?= $pending ? ' d-none' : '' ?>">Aucun avis en attente 🎉</div>
              <?php if ($pending): ?>
                <div class="table-responsive" id="pending-table-wrapper">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th style="width:80px">Note</th>
                        <th>Commentaire</th>
                        <th style="width:140px">Driver</th>
                        <th style="width:140px">Auteur</th>
                        <th style="width:160px">Créé</th>
                        <th style="width:220px" class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="pending-tbody">
                      <?php foreach ($pending as $doc): ?>
                        <?php
                        $id = (string)$doc['_id'];
                        $rating = (int)($doc['rating'] ?? 0);
                        $comment = trim((string)($doc['comment'] ?? ''));
                        $driverId = (int)($doc['driver_id'] ?? 0);
                        $riderId  = (int)($doc['rider_id'] ?? 0);
                        $created  = $doc['created_at'] ?? null;
                        if ($created instanceof MongoDB\BSON\UTCDateTime) {
                          $created = $created->toDateTime()->format('Y-m-d H:i');
                        }
                        ?>
                        <tr id="row-<?= $id ?>">
                          <td>
                            <span class="badge bg-primary-subtle text-primary fw-semibold"><?= $rating ?>/5</span>
                          </td>
                          <td>
                            <?= htmlspecialchars($comment ?: '—') ?>
                          </td>
                          <td>#<?= $driverId ?></td>
                          <td>#<?= $riderId ?></td>
                          <td><?= htmlspecialchars($created ?: '—') ?></td>
                          <td class="text-end">
                            <button
                              class="btn btn-sm btn-outline-success me-2 act-approve"
                              data-id="<?= $id ?>">Approuver</button>
                            <button
                              class="btn btn-sm btn-outline-danger act-reject"
                              data-id="<?= $id ?>">Rejeter</button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php if ($pages > 1): ?>
                  <nav id="pending-pagination">
                    <ul class="pagination pagination-sm">
                      <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <?php
                        $active = $p === $page ? ' active' : '';
                        $q = $_GET;
                        $q['page'] = $p;
                        $q['tab'] = 'pending';
                        $href = '?' . http_build_query($q);
                        ?>
                        <li class="page-item<?= $active ?>">
                          <a class="page-link" href="<?= htmlspecialchars($href) ?>"><?= $p ?></a>
                        </li>
                      <?php endfor; ?>
                    </ul>
                  </nav>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-tab">
              <div class="row">
                <div class="col-12 col-lg-12 col-xl-12">
                  <?php if ($createAdminSuccess): ?>
                    <div class="alert alert-success"><?= e($createAdminSuccess) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($createAdminErrors['global'])): ?>
                    <div class="alert alert-danger"><?= e($createAdminErrors['global']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($createAdminErrors['csrf'])): ?>
                    <div class="alert alert-danger"><?= e($createAdminErrors['csrf']) ?></div>
                  <?php endif; ?>
                  <form method="post" class="card">
                    <div class="card-body">
                      <h5 class="card-title mb-3">Créer un administrateur</h5>
                      <input type="hidden" name="action" value="create_admin">
                      <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
                      <input type="hidden" name="role" value="administrateur">
                      <div class="mb-3">
                        <label for="create-admin-email" class="form-label">Email</label>
                        <input type="email" class="form-control<?= !empty($createAdminErrors['email']) ? ' is-invalid' : '' ?>" id="create-admin-email" name="email" value="<?= e($createAdminOld['email']) ?>" required>
                        <?php if (!empty($createAdminErrors['email'])): ?>
                          <div class="invalid-feedback"><?= e($createAdminErrors['email']) ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="mb-3">
                        <label for="create-admin-pseudo" class="form-label">Pseudo</label>
                        <input type="text" class="form-control<?= !empty($createAdminErrors['pseudo']) ? ' is-invalid' : '' ?>" id="create-admin-pseudo" name="pseudo" value="<?= e($createAdminOld['pseudo']) ?>" required>
                        <?php if (!empty($createAdminErrors['pseudo'])): ?>
                          <div class="invalid-feedback"><?= e($createAdminErrors['pseudo']) ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="mb-3">
                        <label for="create-admin-password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control<?= !empty($createAdminErrors['password']) ? ' is-invalid' : '' ?>" id="create-admin-password" name="password" required>
                        <?php if (!empty($createAdminErrors['password'])): ?>
                          <div class="invalid-feedback"><?= e($createAdminErrors['password']) ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="mb-3">
                        <label for="create-admin-password-confirm" class="form-label">Confirmation du mot de passe</label>
                        <input type="password" class="form-control<?= !empty($createAdminErrors['password_confirm']) ? ' is-invalid' : '' ?>" id="create-admin-password-confirm" name="password_confirm" required>
                        <?php if (!empty($createAdminErrors['password_confirm'])): ?>
                          <div class="invalid-feedback"><?= e($createAdminErrors['password_confirm']) ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Créer le compte</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
  window.__ADMIN_MODERATION__ = <?= json_encode([
                                  'csrf' => (string)$csrf,
                                  'moderatorId' => (int)($admin['id'] ?? 0),
                                  'count' => (int)$total,
                                  'endpoint' => url('admin'),
                                  'query' => (string)($_SERVER['QUERY_STRING'] ?? ''),
                                ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
  // Expose initial stats (server-side) for instant render
  window.__ADMIN_STATS__ = <?= json_encode($stats ?? ['rides' => ['labels' => [], 'values' => []], 'revenue' => ['labels' => [], 'values' => []], 'total_revenue' => 0]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/JS/admin.js" defer></script>
<script>
  (function() {
    var initialTab = <?= json_encode($tab) ?>;
    if (!initialTab) return;
    var map = {
      dashboard: 'tab-dashboard',
      users: 'tab-users',
      stats: 'tab-stats',
      pending: 'avis-en-attente',
      create: 'tab-settings'
    };
    var targetId = map[initialTab];
    if (!targetId) return;
    var activate = function() {
      var trigger = document.querySelector('[data-bs-target="#' + targetId + '"], a[href="#' + targetId + '"]');
      if (!trigger) return;
      if (window.bootstrap && bootstrap.Tab) {
        bootstrap.Tab.getOrCreateInstance(trigger).show();
      } else if (typeof trigger.click === 'function') {
        trigger.click();
      }
    };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', activate);
    } else {
      activate();
    }
  })();
</script>

<div class="page-profil">
  <?php include_once __DIR__ . '/../includes/footer.php'; ?>
  <!-- reuse same footer hook to keep styles/scripts consistent -->
</div>
