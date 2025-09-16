<?php
// ------------------------------
// Constantes & bootstrap
// ------------------------------
define('BASE_PATH', dirname(__DIR__));            // racine projet
define('BASE_URL', '/Projet_ecoride/public');     // préfixe URL publique (adapter si besoin)

require_once BASE_PATH . '/app/core/bootstrap.php';
require_once BASE_PATH . '/app/core/security.php';
require_once BASE_PATH . '/app/core/validation.php';
require_once BASE_PATH . '/app/core/db.php';
require_once BASE_PATH . '/app/core/FormGuard.php';
require_once BASE_PATH . '/app/Router.php';

// (Dev) voir les erreurs si 500
error_reporting(E_ALL);
ini_set('display_errors','1');

// ------------------------------
// Contexte global
// ------------------------------
$db       = db();
$authUser = $_SESSION['user'] ?? [];
$GLOBALS['db']       = $db;
$GLOBALS['authUser'] = $authUser;

// ------------------------------
// Normalisation du PATH (enlève le sous-dossier /Projet_ecoride/public)
// ------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$path   = parse_url($uri, PHP_URL_PATH);

// ex: SCRIPT_NAME = /Projet_ecoride/public/index.php  → on retire /Projet_ecoride/public
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($scriptDir && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
}
if ($path === '' || $path === false) { $path = '/'; }

// ------------------------------
// Router simple (pages "vues")
// ------------------------------
$router = new Router();
$router->add('/',               BASE_PATH . '/app/Views/pages/home.php');
$router->add('home',            BASE_PATH . '/app/Views/pages/home.php');
$router->add('trajets',         BASE_PATH . '/app/Views/pages/listeTrajets.php');
$router->add('proposer-trajet', BASE_PATH . '/app/Views/pages/proposerTrajet.php');
$router->add('trajet',          BASE_PATH . '/app/Views/pages/trajet.php');
$router->add('contact',         BASE_PATH . '/app/Views/pages/contact.php');
$router->add('mention',         BASE_PATH . '/app/Views/pages/mention.php');
$router->add('condition',       BASE_PATH . '/app/Views/pages/condition.php');
$router->add('inscription',     BASE_PATH . '/app/Views/pages/inscription.php');
$router->add('connexion',       BASE_PATH . '/app/Views/pages/connexion.php');
$router->add('deconnexion',     BASE_PATH . '/app/Views/pages/deconnexion.php');

// ❌ NE PAS router /profil via une simple vue, on passe par le contrôleur
// $router->add('profil', BASE_PATH . '/app/Views/pages/profil.php');

// ------------------------------
// Routes /profil (unifiées SANS préfixe)
// ------------------------------

// GET /profil  → prépare $ctx et affiche la vue
if ($method === 'GET' && $path === '/profil') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php'; // prépare $ctx
    $ctx = profile_prepare($db, $_SESSION['user'] ?? []);
    extract($ctx);
    require BASE_PATH . '/app/Views/pages/profil.php';
    exit;
}

// POST /profil (sauvegarde infos de compte)
if ($method === 'POST' && $path === '/profil') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    // Ton contrôleur gère quelle action précise selon les champs envoyés
    profile_save($db, $authUser, $_POST);
    exit;
}

// POST /profil/enregistrer
if ($method === 'POST' && $path === '/profil/enregistrer') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_save($db, $authUser, $_POST);
    exit;
}

// POST /profil/update (mise à jour compte)
if ($method === 'POST' && $path === '/profil/update') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_update_account($db, $authUser, $_POST);
    exit;
}

// POST /profil/password (changement de mot de passe)
if ($method === 'POST' && $path === '/profil/password') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_update_password($db, $authUser, $_POST);
    exit;
}

// POST /profil/role
if ($method === 'POST' && $path === '/profil/role') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_role_update($db, $authUser, $_POST);
    exit;
}

// POST /profil/preferences/save
if ($method === 'POST' && $path === '/profil/preferences/save') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    preferences_save($db, $authUser, $_POST);
    exit;
}

// Véhicules
// POST /profil/vehicules/ajouter
if ($method === 'POST' && $path === '/profil/vehicules/ajouter') {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    vehicle_add($db, $authUser, $_POST);
    exit;
}

// POST /profil/vehicules/{id}/edit
if ($method === 'POST' && preg_match('#^/profil/vehicules/(\d+)/edit$#', $path, $m)) {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    vehicle_update($db, $authUser, (int)$m[1], $_POST);
    exit;
}

// POST /profil/vehicules/{id}/delete
if ($method === 'POST' && preg_match('#^/profil/vehicules/(\d+)/delete$#', $path, $m)) {
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    vehicle_delete($db, $authUser, (int)$m[1]);
    exit;
}

// POST /profil/avatar (upload)
if ($method === 'POST' && $path === '/profil/avatar') {
    // S'assurer que $db est visible dans le contrôleur
    $GLOBALS['db'] = $db;
    require BASE_PATH . '/app/Controllers/_profil_avatar.ctrl.php';
    exit;
}

// ------------------------------
// Routes /admin
// ------------------------------
// GET /admin → tableau de bord
if ($method === 'GET' && $path === '/admin') {
    require_once BASE_PATH . '/app/Controllers/_admin.ctrl.php';
    require_admin();

    $dashboard = admin_dashboard($db);
    $users     = admin_list_users($db);
    // Stats de base (peuvent aussi être chargées via AJAX)
    $stats     = admin_stats($db);

    require BASE_PATH . '/app/Views/pages/admin.php';
    exit;
}

// POST /admin/utilisateurs/suspendre
if ($method === 'POST' && $path === '/admin/utilisateurs/suspendre') {
    require_once BASE_PATH . '/app/Controllers/_admin.ctrl.php';
    admin_suspend_user($db, $_POST);
    exit;
}

// POST /admin/utilisateurs/reactiver
if ($method === 'POST' && $path === '/admin/utilisateurs/reactiver') {
    require_once BASE_PATH . '/app/Controllers/_admin.ctrl.php';
    admin_reactivate_user($db, $_POST);
    exit;
}

// GET /admin/stats → JSON pour graphiques
if ($method === 'GET' && $path === '/admin/stats') {
    require_once BASE_PATH . '/app/Controllers/_admin.ctrl.php';
    require_admin();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(admin_stats($db));
    exit;
}

// POST /admin/utilisateurs/supprimer
if ($method === 'POST' && $path === '/admin/utilisateurs/supprimer') {
    require_once BASE_PATH . '/app/Controllers/_admin.ctrl.php';
    admin_delete_user($db, $_POST);
    exit;
}

// ------------------------------
// Le reste des pages via Router
// ------------------------------
$router->dispatch();
