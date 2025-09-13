<?php
$errors = []; $data = []; $success_message = '';

list($data, $errors) = form_guard([
  'method' => 'POST',
  'rate'   => ['key' => 'connexion', 'max' => 5, 'window' => 300],
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
      // ⚠ table/colonne conformes à ta BDD
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

      session_regenerate_id(true);
      $_SESSION['user'] = [
        'id'     => $u['id'],
        'email'  => $u['email'],
        'prenom' => $u['prenom'] ?? null,
        'nom'    => $u['nom'] ?? null,
        'pseudo' => $u['pseudo'] ?? null,
        'credits'=> (int)($u['credits'] ?? 0),
      ];

      return ['redirect' => url('/')];

    } catch (\Throwable $e) { // ← attrape tout (Exception + Error)
      error_log('LOGIN ERR: '.$e->getMessage());
      global $errors; $errors['global'] = 'Erreur interne. Réessayez plus tard.';
    }
  },
]);
