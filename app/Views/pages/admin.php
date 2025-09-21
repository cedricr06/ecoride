<?php include_once __DIR__ . '/../includes/header.php'; ?>

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
              <p class="mb-0 small"><?= e($admin['prenom'] ?? '') ?> <?= e($admin['nom'] ?? '') ?> (<?= e($admin['pseudo'] ?? '') ?>)</p>
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
              <button class="nav-link" id="tab-users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab" aria-controls="tab-users" aria-selected="false">Utilisateurs</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-stats-tab" data-bs-toggle="tab" data-bs-target="#tab-stats" type="button" role="tab" aria-controls="tab-stats" aria-selected="false">Statistiques</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-settings-tab" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab" aria-controls="tab-settings" aria-selected="false">Avis en attente</button>
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
              <div class="row g-3">
                <div class="col-12">
                  <div class="card">
                    <div class="card-body">
                      <h3 class="h6">Covoiturages par jour</h3>
                      <canvas id="ridesChart" height="160"></canvas>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="card">
                    <div class="card-body">
                      <h3 class="h6 d-flex justify-content-between align-items-center">
                        <span>Crédits gagnés par jour</span>
                        <span class="badge bg-success">Total: <span id="totalRevenueText">0</span></span>
                      </h3>
                      <canvas id="revenueChart" height="160"></canvas>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Settings -->
            <div class="tab-pane fade" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-tab">
              <p class="info-tab-admin">Paramètres d'administration à venir…</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
  // Expose initial stats (server-side) for instant render
  window.__ADMIN_STATS__ = <?= json_encode($stats ?? ['rides' => ['labels' => [], 'values' => []], 'revenue' => ['labels' => [], 'values' => []], 'total_revenue' => 0]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/JS/admin.js" defer></script>

<div class="page-profil">
  <?php include_once __DIR__ . '/../includes/footer.php'; ?>
  <!-- reuse same footer hook to keep styles/scripts consistent -->
</div>
