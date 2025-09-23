<?php
require_login(); // protégé

$errors = []; $data = []; $success_message = '';

list($data, $errors, $success_message) = form_guard([
  'method' => 'POST',
  'rules'  => [
    'depart'      => 'required|string:2,100',
    'arrivee'     => 'required|string:2,100',
    'date_depart' => 'required',
    'places'      => 'required|in:1,2,3,4,5,6,7,8',
  ],
  'on_success' => function($clean) {
    // TODO: INSERT en BDD
    // $stmt = db()->prepare("INSERT INTO trajets ...");
    // $stmt->execute([...]);
    return 'Votre trajet a été proposé avec succès !';
  },
]);
