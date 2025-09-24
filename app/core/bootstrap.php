<?php
// === Définition BASE_URL / BASE_URI + helpers ===
$https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme = $https ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$baseUri   = ($scriptDir === '' || $scriptDir === '/') ? '' : $scriptDir;

if (!defined('BASE_URI')) define('BASE_URI', $baseUri);
if (!defined('BASE_URL')) define('BASE_URL', $scheme.'://'.$host.$baseUri);

if (!function_exists('asset')) {
  function asset(string $p=''): string { return rtrim(BASE_URI, '/').'/assets/'.ltrim($p,'/'); }
}
if (!function_exists('url')) {
  function url(string $p=''): string   { return rtrim(BASE_URL, '/').'/'.ltrim($p,'/'); }
}
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* ------------------------- Sessions sécurisées ------------------------- */
$lifetime = 86400; // 24h

if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
    // PHP ≥ 7.3 : support du tableau + SameSite
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '', // domaine courant
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    // Ancien PHP : pas de param SameSite ici
    session_set_cookie_params(
        $lifetime,
        '/',            // path
        '',             // domain
        !empty($_SERVER['HTTPS']), // secure
        true            // httponly
    );
    // Aide à sécuriser un peu
    ini_set('session.cookie_secure',    !empty($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_httponly',  '1');
    // NB: SameSite non supporté proprement avant 7.3
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['initiated'])) {
    session_regenerate_id(true); // anti fixation
    $_SESSION['initiated'] = true;
}

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=()");

    // Pas de CDN => CSP simple
    $csp = "default-src 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src  'self' data: https://fonts.gstatic.com; "
        . "script-src 'self';";
    header("Content-Security-Policy: $csp");
}

/* --------------------------- Constantes chemin ------------------------- */
// Pas de dirname(__DIR__, 2) (incompatible 5.6) : on fait deux dirname() à la main
if (!defined('BASE_PATH')) {
    // BASE_PATH = /ton-projet
    define('BASE_PATH', dirname(dirname(__DIR__)));
}

/* ----------------------------- Helper URL ----------------------------- */
/**
 * Construit une URL en respectant un éventuel sous-dossier.
 * Ex: url('connexion') -> /Projet_ecoride/public/connexion si ton site vit sous /Projet_ecoride/public
 */
if (!function_exists('url')) {
    function url($path = '/')
    {
        $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
        $scriptDir = rtrim($scriptDir, '/\\');                // ex: /Projet_ecoride/public
        $base      = ($scriptDir === '' || $scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
        return $base . '/' . ltrim($path, '/');
    }
}
/** Echappement HTML */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Flash message */
function flash(string $type, string $message): void
{
    $_SESSION['__flash'][] = ['type' => $type, 'message' => $message];
}

/** Récupérer + vider les flash */
function flashes(): array
{
    $msgs = $_SESSION['__flash'] ?? [];
    unset($_SESSION['__flash']);
    return $msgs;
}

/** Redirection */
function redirect(string $path): void
{
    if (preg_match('~^https?://~', $path)) {
        $url = $path;
    } else {
        $url = BASE_URL . '/' . ltrim($path, '/');
    }
    header('Location: ' . $url, true, 302);
    exit;
}

/* ---------------------------- Gestion erreurs ------------------------- */
error_reporting(E_ALL);
ini_set('display_errors', '0'); // pas d’affichage en prod
ini_set('log_errors', '1');
// Optionnel: fichier de log dédié
// ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
