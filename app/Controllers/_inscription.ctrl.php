<?php
$errors = []; $data = []; $success_message = '';

list($data, $errors) = form_guard([
  'method' => 'POST',
  'rate'   => ['key' => 'inscription', 'max' => 3, 'window' => 600],
  'rules'  => [
    'pseudo'   => 'required|speudo:3,30',
    'email'    => 'required|email',
    'password' => 'required|password:8,72',
    'prenom'   => 'required|nom-prenom:2,40',
    'nom'      => 'required|nom-prenom:2,40',
    'cgu'      => 'required|checkbox',
  ],
  'on_success' => function($clean) {
    // Confirmation mot de passe
    if (!isset($_POST['password2']) || $_POST['password2'] !== $clean['password']) {
      global $errors; $errors['password2'] = 'La confirmation ne correspond pas.';
      return;
    }

    // Normalisation
    $clean['email']  = strtolower(trim($clean['email']));
    $clean['pseudo'] = trim($clean['pseudo']);

    try {
      // Doublons ciblés (email/pseudo)
      $stmt = db()->prepare("SELECT email, pseudo FROM utilisateurs WHERE email = ? OR pseudo = ?");
      $stmt->execute([$clean['email'], $clean['pseudo']]);
      if ($row = $stmt->fetch()) {
        global $errors;
        if ($row['email'] === $clean['email'])  { $errors['email']  = 'Cette adresse email est déjà utilisée.'; }
        if ($row['pseudo'] === $clean['pseudo']){ $errors['pseudo'] = 'Ce pseudo est déjà pris.'; }
        return;
      }

      $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
      $hash = password_hash($clean['password'], $algo);

      // Si votre colonne `cree_le` a un DEFAULT CURRENT_TIMESTAMP, on peut l’omettre :
      $ins = db()->prepare("
        INSERT INTO utilisateurs (pseudo, prenom, nom, email, mot_de_passe_hash)
        VALUES (?, ?, ?, ?, ?)
      ");
      $ins->execute([$clean['pseudo'], $clean['prenom'], $clean['nom'], $clean['email'], $hash]);

      return ['redirect' => url('connexion') . '?success=1'];

    } catch (PDOException $e) {
      // Si contrainte UNIQUE déclenchée malgré la vérif (course), message propre
      if ($e->getCode() === '23000') {
        global $errors; $errors['global'] = "Cet email ou ce pseudo est déjà utilisé.";
        return;
      }
      error_log($e->getMessage());
      global $errors; $errors['global'] = 'Erreur interne. Réessayez plus tard.';
    }
  },
]);
