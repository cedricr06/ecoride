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
ini_set('display_errors', '1');

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
if ($path === '' || $path === false) {
    $path = '/';
}

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



// ------------------------------
// Routes /profil (unifiées SANS préfixe)
// ------------------------------

// GET /profil  → prépare $ctx et affiche la vue
if ($method === 'GET' && $path === '/profil') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php'; // prépare $ctx
    $ctx = profile_prepare($db, $_SESSION['user'] ?? []);
    extract($ctx);
    require BASE_PATH . '/app/Views/pages/profil.php';
    exit;
}

// POST /profil (sauvegarde infos de compte)
if ($method === 'POST' && $path === '/profil') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    // Ton contrôleur gère quelle action précise selon les champs envoyés
    profile_save($db, $authUser, $_POST);
    exit;
}

// POST /profil/participations/{id}/annuler
if ($method === 'POST' && preg_match('#^/profil/participations/(\d+)/(?:annuler|supprimer)$#', $path, $m)) {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_participation_delete($db, (int)$m[1], $_SESSION['user']['id'] ?? 0);
    header('Location: ' . BASE_URL . '/profil');
    exit;
}
// POST /trajet/{id}/participer  (passager)
if ($method === 'POST' && preg_match('#^/trajet/(\d+)/participer$#', $path, $m)) {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    trajet_participer($db, (int)$m[1], $_SESSION['user']['id'] ?? 0);
    header('Location: ' . BASE_URL . '/trajet?id=' . (int)$m[1]);
    exit;
}


// POST /profil/voyages/{id}/annuler
if ($method === 'POST' && preg_match('#^/profil/voyages/(\d+)/annuler$#', $path, $m)) {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_participation_delete($db, (int)$m[1], $_SESSION['user']['id'] ?? 0);
    header('Location: ' . BASE_URL . '/profil');
    exit;
}

// POST /profil/voyages/{id}/valider
if ($method === 'POST' && preg_match('#^/profil/voyages/(\d+)/valider$#', $path, $m)) {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_voyage_accept($db, (int)$m[1], $_SESSION['user']['id'] ?? 0);
    header('Location: ' . BASE_URL . '/profil?v=done#tab-voyages'); // retour historique (ou /profil)
    exit;
}

// POST /profil/enregistrer
if ($method === 'POST' && $path === '/profil/enregistrer') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_save($db, $authUser, $_POST);
    exit;
}

// POST /profil/update (mise à jour compte)
if ($method === 'POST' && $path === '/profil/update') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_update_account($db, $authUser, $_POST);
    exit;
}

// POST /profil/password (changement de mot de passe)
if ($method === 'POST' && $path === '/profil/password') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_update_password($db, $authUser, $_POST);
    exit;
}

// POST /profil/role
if ($method === 'POST' && $path === '/profil/role') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    profile_role_update($db, $authUser, $_POST);
    exit;
}

// POST /profil/preferences/save
if ($method === 'POST' && $path === '/profil/preferences/save') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    preferences_save($db, $authUser, $_POST);
    exit;
}

// Véhicules
// POST /profil/vehicules/ajouter
if ($method === 'POST' && $path === '/profil/vehicules/ajouter') {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    vehicle_add($db, $authUser, $_POST);
    exit;
}

// POST /profil/vehicules/{id}/edit
if ($method === 'POST' && preg_match('#^/profil/vehicules/(\d+)/edit$#', $path, $m)) {
    forbid_admin();
    require BASE_PATH . '/app/Controllers/_profil.ctrl.php';
    if (function_exists('verify_csrf')) verify_csrf();
    vehicle_update($db, $authUser, (int)$m[1], $_POST);
    exit;
}

// POST /profil/vehicules/{id}/delete
if ($method === 'POST' && preg_match('#^/profil/vehicules/(\d+)/delete$#', $path, $m)) {
    forbid_admin();
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
    $statsDays = 7;
    // Stats de base (peuvent aussi être chargées via AJAX)
    $stats     = admin_stats($db, $statsDays);

    try {
        [$pending, $total, $pages, $page] = admin_pending_reviews($_GET);
    } catch (Throwable $e) {
        $pending = [];
        $total = 0;
        $pages = 0;
        $page = 1;
    }
    $csrf = admin_csrf_token();

    $tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'dashboard';
    $allowedTabs = ['dashboard', 'users', 'stats', 'pending', 'create'];
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = 'dashboard';
    }

    $createAdminFeedback = $_SESSION['create_admin_feedback'] ?? null;
    $createAdminErrors = $createAdminFeedback['errors'] ?? [];
    if (!is_array($createAdminErrors)) {
        $createAdminErrors = [];
    }
    $createAdminOld = $createAdminFeedback['old'] ?? [];
    if (!is_array($createAdminOld)) {
        $createAdminOld = [];
    }
    $createAdminSuccess = $createAdminFeedback['success'] ?? '';
    unset($_SESSION['create_admin_feedback']);
    $createAdminOld = array_merge(['email' => '', 'pseudo' => ''], $createAdminOld);

    require BASE_PATH . '/app/Views/pages/admin.php';
    exit;
}

// POST /admin (moderation actions)
if ($method === 'POST' && $path === '/admin') {
    require_once BASE_PATH . '/app/Controllers/_admin.ctrl.php';
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
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    $days = max(1, min(31, $days));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(admin_stats($db, $days));
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
