<?php
$errors = []; $data = []; $success_message = '';

list($data, $errors) = form_guard([
  'method' => 'POST',
  'rate'   => ['key' => 'connexion', 'max' => 5, 'window' => 3],  // nombre de tentatives / temps de blocage
  'rules'  => [
    'email'    => 'required|email',
    'password' => 'required|password:8,72',
  ],
  'on_success' => function($clean) {
    // Normalise l'email (évite test@/Test@)
    $clean['email'] = strtolower(trim($clean['email']));

    // (Optionnel) 2e rate-limit par email
    $emailKey = 'connexion-email:' . $clean['email'];
    if (!rate_limit($emailKey, 5, 900)) {
      global $errors; $errors['global'] = 'Trop de tentatives. Réessayez plus tard.';
      return;
    }

    try {
      // ? table/colonne conformes à ta BDD
      $stmt = db()->prepare("
        SELECT id, email, mot_de_passe_hash, prenom, nom, pseudo, credits
        FROM utilisateurs
        WHERE email = ?
      ");
      $stmt->execute([$clean['email']]);
      $u = $stmt->fetch();

      if (!$u || !is_string($u['mot_de_passe_hash']) || !password_verify($clean['password'], $u['mot_de_passe_hash'])) {
        global $errors; $errors['global'] = 'Email ou mot de passe incorrect.';
        return;
      }
      // Extra: rôle + suspension (si colonnes présentes)
      $role = 'utilisateur';
      $suspended = 0;
      try {
        $stmt2 = db()->prepare("SELECT role, est_suspendu FROM utilisateurs WHERE id = ?");
        $stmt2->execute([$u['id']]);
        if ($row = $stmt2->fetch()) {
          if (isset($row['role']) && $row['role'] !== null) $role = (string)$row['role'];
          if (!empty($row['est_suspendu'])) $suspended = 1;
        }
      } catch (\Throwable $e) { /* colonnes absentes: ok */ }

      if ($suspended) {
        global $errors; $errors['global'] = 'Votre compte est suspendu.';
        return;
      }

      session_regenerate_id(true);
      $_SESSION['user'] = [
        'id'     => $u['id'],
        'email'  => $u['email'],
        'prenom' => $u['prenom'] ?? null,
        'nom'    => $u['nom'] ?? null,
        'pseudo' => $u['pseudo'] ?? null,
        'credits'=> (int)($u['credits'] ?? 0),
        'role'   => $role,
      ];

      // Redirection selon rôle
      if (($_SESSION['user']['role'] ?? '') === 'administrateur') {
        return ['redirect' => url('admin')];
      }
      return ['redirect' => url('profil')];

    } catch (\Throwable $e) { // attrape tout (Exception + Error)
      error_log('LOGIN ERR: '.$e->getMessage());
      global $errors; $errors['global'] = 'Erreur interne. Réessayez plus tard.';
    }
  },
]);
