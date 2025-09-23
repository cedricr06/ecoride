<?php
require_post();   // POST only
verify_csrf();    // CSRF one-shot

unset($_SESSION['user']);         // déconnexion
session_regenerate_id(true);      // anti fixation après logout
header('Location: ' . url('/'));  // retour à l’accueil
exit;