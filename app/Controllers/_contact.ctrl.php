<?php
$errors = []; $data = []; $success_message = '';

list($data, $errors, $success_message) = form_guard([
  'method'   => 'POST',
  'honeypot' => 'website',
  'rate'     => ['key'=>'contact', 'max'=>3, 'window'=>300],
  'rules'    => [
    'fullname' => 'required|string:2,80',
    'email'    => 'required|email',
    'subject'  => 'required|string:3,120',
    'message'  => 'required|string:10,5000',
    'cgu'      => 'required|checkbox',
  ],
  'on_success' => function($clean) {
    // Exemple traitement : mail ou BDD (à adapter)
    // mail(...);
    return 'Votre message a bien été envoyé.';
  },
]);