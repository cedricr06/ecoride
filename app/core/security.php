<?php
/* ---------- Échappement HTML ---------- */
if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* ---------- hash_equals (fallback si absent) ---------- */
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) return false;
        $len = strlen($known_string);
        if ($len !== strlen($user_string)) return false;
        $res = 0;
        for ($i = 0; $i < $len; $i++) {
            $res |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $res === 0;
    }
}

/* ---------- CSRF ---------- */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            if (function_exists('random_bytes')) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            } else {
                $_SESSION['csrf_token'] = sha1(uniqid(mt_rand(), true));
            }
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        // important: échapper la valeur
        $val = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_token" value="' . $val . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $ok = isset($_POST['_token'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], (string)$_POST['_token']);

            // NE PAS détruire le token ici
            if (!$ok) {
                http_response_code(419);
                exit('419 - Session expirée (CSRF).');
            }
        }
    }
}


/* ---------- Méthodes HTTP ---------- */
if (!function_exists('require_post')) {
    function require_post() {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('405 - Méthode non autorisée.');
        }
    }
}

/* ---------- Rate limit fichier /tmp ---------- */
if (!function_exists('rate_limit')) {
    /**
     * @param string $key        identifiant logique (ex: "login:IP")
     * @param int    $max        tentatives max dans la fenêtre
     * @param int    $windowSec  durée fenêtre en secondes
     * @return bool  true si autorisé, false si dépassé
     */
    function rate_limit($key, $max, $windowSec) {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ecoride_rl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . sha1($key) . '.json';
        $now  = time();

        $data = array('attempts' => 0, 'first' => $now);
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $dec = json_decode($raw, true);
                if (is_array($dec) && isset($dec['attempts'], $dec['first'])) {
                    $data = $dec;
                }
            }
        }

        if (($now - (int)$data['first']) > (int)$windowSec) {
            $data = array('attempts' => 0, 'first' => $now);
        }

        $data['attempts']++;

        @file_put_contents($file, json_encode($data), LOCK_EX);

        return ((int)$data['attempts'] <= (int)$max);
    }
}

/* ---------- Auth simples ---------- */
if (!function_exists('require_login')) {
    function require_login() {
        if (empty($_SESSION['user'])) {
            header('Location: ' . url('connexion'));
            exit;
        }
    }
}
if (!function_exists('require_admin')) {
    function require_admin() {
        // Exige une session active
        require_login();

        $role = $_SESSION['user']['role'] ?? '';
        if ($role !== 'administrateur') {
            // Option: rediriger vers /profil
            // header('Location: ' . url('profil')); exit;
            http_response_code(403);
            exit('403 - Accès refusé (administrateur requis).');
        }
    }
}
if (!function_exists('forbid_admin')) {
    function forbid_admin(?string $redirect = null): void {
        $role = $_SESSION['user']['role'] ?? '';
        if ($role === 'administrateur') {
            $target = $redirect ?? (BASE_URL . '/admin');
            header('Location: ' . $target);
            exit;
        }
    }
}
if (!function_exists('guest_only')) {
    function guest_only() {
        if (!empty($_SESSION['user'])) {
            header('Location: ' . url('/'));
            exit;
        }
    }
}
