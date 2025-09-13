<?php
if (!function_exists('form_guard')) {
  /**
   * Traite un formulaire si la méthode correspond (POST par défaut).
   * @param array $cfg [
   *   'method' => 'POST',
   *   'honeypot' => 'website' | null,
   *   'rate' => ['key'=>'contact', 'max'=>3, 'window'=>300] | null,
   *   'rules' => [...],                    // règles validate()
   *   'on_success' => function($clean) {}  // callback; peut retourner:
   *                                       //  - string (message succès)
   *                                       //  - ['redirect' => url('...')]
   * ]
   * @return array [$data, $errors, $success_message]
   */
  function form_guard($cfg) {
    $data = []; $errors = []; $success = '';
    $method = isset($cfg['method']) ? strtoupper($cfg['method']) : 'POST';

    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === $method) {
      // Sécurité minimale
      require_post();
      verify_csrf();

      // Honeypot
      if (!empty($cfg['honeypot'])) {
        $hp = $cfg['honeypot'];
        if (!empty($_POST[$hp])) {
          http_response_code(400);
          exit;
        }
      }

      // Rate limit
      if (!empty($cfg['rate'])) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
        $rk = $cfg['rate']['key'] . ':' . $ip;
        $max = (int)$cfg['rate']['max'];
        $win = (int)$cfg['rate']['window'];
        if (!rate_limit($rk, $max, $win)) {
          $errors['global'] = 'Trop de tentatives. Réessayez plus tard.';
        }
      }

      // Validation
      if (empty($errors) && !empty($cfg['rules'])) {
        list($data, $errors) = validate($_POST, $cfg['rules']);
      }

      // Succès
      if (empty($errors) && !empty($cfg['on_success']) && is_callable($cfg['on_success'])) {
        $res = call_user_func($cfg['on_success'], $data);
        if (is_array($res) && !empty($res['redirect'])) {
          header('Location: ' . $res['redirect']); exit;
        } elseif (is_string($res) && $res !== '') {
          $success = $res;
        }
      }
    }

    return array($data, $errors, $success);
  }
}