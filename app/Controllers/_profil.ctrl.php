<?php
require_login();
global $db;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'participer') {
    if (function_exists('participer')) {
        $voyageId = (int)($_POST['id'] ?? 0);
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        participer($db, $voyageId, $uid);
        // Redirect back to the trip page
        header('Location: ' . url('trajet') . '?id=' . $voyageId);
        exit;
    }
}

if (!function_exists('app_log')) {
    function app_log(string $msg): void
    {
        // écris dans le log PHP standard avec timestamp
        error_log(date('c') . ' ' . $msg);
    }
}

// Passager : participer 
if (!function_exists('participer')) {
    function participer(PDO $db, int $voyageId, int $uid): void
    {
        if ($uid <= 0 || $voyageId <= 0) return;

        try {
            if (function_exists('require_post')) require_post();
            $db->beginTransaction();

            $st = $db->prepare("SELECT id, chauffeur_id, prix, statut, places_disponibles FROM voyages WHERE id=:id FOR UPDATE");
            $st->execute([':id' => $voyageId]);
            $voyage = $st->fetch(PDO::FETCH_ASSOC);

            if (!$voyage) throw new RuntimeException('Trajet introuvable.');
            if (($voyage['statut'] ?? '') === 'annule') throw new RuntimeException('Trajet annulé.');

            // Force places = 1
            $places = 1;

            $places_disponibles = (int)($voyage['places_disponibles'] ?? 0);
            if ($places_disponibles < 1) throw new RuntimeException('Plus de place disponible.');

            $chk = $db->prepare("SELECT 1 FROM participations WHERE voyage_id=:vid AND passager_id=:u AND statut IN ('en_attente','confirme') LIMIT 1 FOR UPDATE");
            $chk->execute([':vid' => $voyageId, ':u' => $uid]);
            if ($chk->fetchColumn()) throw new RuntimeException('Vous participez déjà à ce trajet.');

            $prix = (int)($voyage['prix'] ?? 0);
            $total = $prix; // total = prix * 1 place
            if ($total <= 0) throw new RuntimeException('Montant invalide.');

            $u = $db->prepare("SELECT credits FROM utilisateurs WHERE id=:u FOR UPDATE");
            $u->execute([':u' => $uid]);
            $credits = (int)$u->fetchColumn();
            if ($credits < $total) throw new RuntimeException('Crédits insuffisants.');

            $db->prepare("UPDATE utilisateurs SET credits = credits - :total WHERE id=:u")->execute([':total' => $total, ':u' => $uid]);
            $db->prepare("UPDATE site_wallet SET balance = balance + :total WHERE id=1")->execute([':total' => $total]);
            $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id, created_at) VALUES ('debit', 'participation_pay', :total, :u, :v, NOW())")->execute([':total' => $total, ':u' => $uid, ':v' => $voyageId]);
            $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id, created_at) VALUES ('credit', 'participation_pay', :total, NULL, :v, NOW())")->execute([':total' => $total, ':v' => $voyageId]);
            $db->prepare("INSERT INTO participations (voyage_id, passager_id, places, prix, statut, inscrit_le) VALUES (:v, :u, 1, :prix, 'confirme', NOW())")->execute([':v' => $voyageId, ':u' => $uid, ':prix' => $prix]);
            $db->prepare("UPDATE voyages SET places_disponibles = places_disponibles - 1, payout_status='pending' WHERE id=:v")->execute([':v' => $voyageId]);

            $db->commit();
            if (function_exists('flash')) flash('success', 'Participation payée et confirmée.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (function_exists('flash')) flash('danger', $e->getMessage());
        }
    }
}

// --- Hook pour l'audit SELECT-only (BLOC 6)
if (isset($_GET['audit']) && $_GET['audit'] === 'true') {
    if (function_exists('audit_ledger_consistency')) {
        header('Content-Type: application/json');
        echo json_encode(audit_ledger_consistency($db));
        exit;
    }
}

/* -------------------- Utils -------------------- */

function yearsFromDate(?string $date): ?int
{
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', trim($date));
    if (!$d) return null;
    $now = new DateTime('today');
    return max(0, (int)$d->diff($now)->y);
}

/** Données “de base” de l’utilisateur (table utilisateurs) */
function user_fetch(PDO $db, int $userId): array
{
    $st = $db->prepare("
        SELECT id, pseudo, prenom, nom, email, role, credits, avatar_path
        FROM utilisateurs
        WHERE id = ?
    ");
    $st->execute([$userId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

/** Profil + préférences (tables profils, preferences) */
function profile_fetch(PDO $db, int $userId): array
{
    // profil
    $sp = $db->prepare("SELECT * FROM profils WHERE utilisateur_id = ?");
    $sp->execute([$userId]);
    $profil = $sp->fetch(PDO::FETCH_ASSOC) ?: [];

    // preferences (toutes les colonnes utiles d’un coup)
    $sq = $db->prepare("
        SELECT fumeur, animaux, autre_pref, role_covoiturage, aime_parler, musique_niveau
        FROM preferences WHERE utilisateur_id = ?
    ");
    $sq->execute([$userId]);
    $prefs = $sq->fetch(PDO::FETCH_ASSOC) ?: [];

    return [$profil, $prefs];
}

/** Prépare le contexte pour la vue profil */
function profile_prepare(PDO $db, array $authUser): array
{
    $uid = (int)($authUser['id'] ?? 0);
    if ($uid <= 0) return ['user' => [], 'profil' => [], 'prefs' => [], 'age' => null, 'permisYears' => null, 'vehicules' => []];

    $dbUser = user_fetch($db, $uid);          // ← contient avatar_path
    $user   = array_merge($authUser, $dbUser ?: []); // ← IMPORTANT

    [$profil, $prefs] = profile_fetch($db, $uid);
    $age         = yearsFromDate($profil['date_naissance'] ?? null);
    $permisYears = yearsFromDate($profil['date_permis'] ?? null);

    $voyages_view = $_GET['v'] ?? 'upcoming';
    if (!in_array($voyages_view, ['upcoming', 'done', 'canceled', 'all'], true)) {
        $voyages_view = 'upcoming';
    }

    $qv = $db->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ? ORDER BY id DESC");
    $qv->execute([$uid]);
    $vehicules = $qv->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $my_participations = profile_list_participations($db, $uid);
    $voyages = profile_list_my_voyages($db, $uid, $voyages_view);


    $voyages_view_label = [
        'upcoming' => 'À venir',
        'done'     => 'Historique — effectués',
        'canceled' => 'Historique — annulés',
        'all'      => 'Tout',
    ][$voyages_view];

    return compact('user', 'profil', 'prefs', 'age', 'permisYears', 'vehicules', 'my_participations', 'voyages', 'voyages_view', 'voyages_view_label');
}

/* -------------------- Handlers POST -------------------- */
/* Toutes les fonctions POST commencent par require_post() + verify_csrf() */

function profile_save(PDO $db, array $authUser, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid = (int)$authUser['id'];

    // Validation légère (garde tes règles existantes)
    // Validation légère (garde tes règles existantes)
    if (!function_exists('validate')) {
        $clean  = $post;
        $errors = [];
    } else {
        [$clean, $errors] = validate($post, [
            'date_naissance' => 'string:0,10',
            'date_permis'    => 'string:0,10',
            'telephone'      => 'numeros',
            'ville'          => 'string:0,100',
            'bio'            => 'string:0,280',
            // 'no_permis' => 'bool'   // si ton helper gère, sinon on le traite à la main
        ]);
    }

    // ---- Normalisation dates + "pas de permis"
    $noPermis = isset($_POST['no_permis']);
    $dn = trim($_POST['date_naissance'] ?? '');
    $dp = $noPermis ? '' : trim($_POST['date_permis'] ?? '');

    // formats valides ?
    if ($dn !== '' && !DateTime::createFromFormat('Y-m-d', $dn)) $dn = '';
    if ($dp !== '' && !DateTime::createFromFormat('Y-m-d', $dp)) $dp = '';

    $today = date('Y-m-d');
    // garde-fous
    if ($dn !== '' && $dn > $today) $dn = '';
    if ($dp !== '' && $dp > $today) $dp = '';
    if ($dn !== '' && $dp !== '' && $dp < $dn) $dp = ''; // permis avant naissance -> on annule

    $clean['date_naissance']   = $dn !== '' ? $dn : null;
    $clean['date_permis']      = $dp !== '' ? $dp : null;

    if (!empty($errors)) {
        if (function_exists('flash')) flash('error', 'Formulaire invalide.');
        header('Location: ' . url('profil') . '?error=profil#tab-info'); // (tu peux cibler l’onglet Infos)
        exit;
    }


    // UPSERT profils
    $stmt = $db->prepare("SELECT 1 FROM profils WHERE utilisateur_id=?");
    $stmt->execute([$uid]);

    if ($stmt->fetchColumn()) {
        $sql = "UPDATE profils SET
                  date_naissance=:date_naissance, date_permis=:date_permis,
                  telephone=:telephone, ville=:ville, bio=:bio
                WHERE utilisateur_id=:uid";
    } else {
        $sql = "INSERT INTO profils
                (utilisateur_id, date_naissance, date_permis, telephone, ville, bio)
                VALUES (:uid, :date_naissance, :date_permis, :telephone, :ville, :bio)";
    }
    $stmt_upsert = $db->prepare($sql);
    $params = [
        ':date_naissance'   => $clean['date_naissance'] ?? null,
        ':date_permis'      => $clean['date_permis'] ?? null,
        ':telephone'        => $clean['telephone'] ?? '',
        ':ville'            => $clean['ville'] ?? '',
        ':bio'              => $clean['bio'] ?? '',
        ':uid'              => $uid,
    ];

    $stmt_upsert->execute($params);

    if (function_exists('flash')) flash('success', 'Profil mis à jour.');
    header('Location: ' . url('profil') . '?success=1#tab-parametres');
    exit;
}

function vehicle_add(PDO $db, array $authUser, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid = (int)$authUser['id'];

    $marque  = trim($post['marque']  ?? '');
    $modele  = trim($post['modele']  ?? '');
    $couleur = trim($post['couleur'] ?? '');
    $energie = trim($post['energie'] ?? '');
    $places  = (int)($post['places'] ?? 4);
    $d1      = trim($post['date_premiere_immatriculation'] ?? '');

    // --- Immat ---
    $immat_raw = strtoupper(trim($post['immatriculation'] ?? ''));

    // regex FR : SIV (AB-123-CD / AB 123 CD / AB123CD) ou ancien FNI (123 ABC 45, 1234 AB 56, 123 AB 2A, 123 AB 971…)
    $re_full = '/^(?:[A-Z]{2}[- ]?\d{3}[- ]?[A-Z]{2}|\d{1,4}\s?[A-Z]{1,3}\s?(?:\d{2}|2A|2B|97[1-6]))$/';

    $errors = [];
    if ($marque === '' || $modele === '' || $energie === '' || $immat_raw === '') $errors[] = 'Champs requis manquants.';
    if ($places < 1 || $places > 9) $errors[] = 'Nombre de places invalide.';

    // date 1re immatriculation
    if ($d1 === '') {
        $d1 = null;
    } elseif (!DateTime::createFromFormat('Y-m-d', $d1)) {
        $d1 = null;
    }

    // Valide l’immat
    if (!preg_match($re_full, $immat_raw)) {
        $errors[] = 'Immatriculation invalide.';
    }

    // Normalise ce qui sera stocké (format canonique)
    $immat_store = null;
    if (preg_match('/^[A-Z]{2}[- ]?\d{3}[- ]?[A-Z]{2}$/', $immat_raw)) {
        // SIV -> AA-123-AA
        $t = preg_replace('/[^A-Z0-9]/', '', $immat_raw);              // ex: AB123CD
        $immat_store = substr($t, 0, 2) . '-' . substr($t, 2, 3) . '-' . substr($t, 5, 2);
    } elseif (preg_match('/^(\d{1,4})\s*([A-Z]{1,3})\s*(2A|2B|\d{2}|97[1-6])$/', $immat_raw, $m)) {
        // FNI -> "123 ABC 45"
        $immat_store = $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }

    if ($errors) {
        if (function_exists('flash')) flash('error', implode(' ', $errors));
        header('Location: ' . url('profil') . '#tab-vehicules');
        exit;
    }

    $sql = "INSERT INTO vehicules
        (utilisateur_id, marque, modele, couleur, immatriculation, energie, places, date_premiere_immatriculation)
        VALUES (:uid,:marque,:modele,:couleur,:immatriculation,:energie,:places,:d1)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'uid'              => (int)$authUser['id'],
        'marque'           => $marque,
        'modele'           => $modele,
        'couleur'          => $couleur,
        'immatriculation'  => $immat_store,  // <— la version canonique
        'energie'          => $energie,
        'places'           => $places,
        'd1'               => $d1,
    ]);

    if (function_exists('flash')) flash('success', 'Véhicule ajouté.');
    header('Location: ' . url('profil') . '?v_added=1#tab-vehicules');
    exit;
}

function vehicle_delete(PDO $db, array $authUser, int $id): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid = (int)$authUser['id'];

    $stmt = $db->prepare("DELETE FROM vehicules WHERE id=:id AND utilisateur_id=:uid");
    $stmt->execute(['id' => $id, 'uid' => $uid]);

    if (function_exists('flash')) flash('success', 'Véhicule supprimé.');
    header('Location: ' . url('profil') . '#tab-vehicules');
    exit;
}

function vehicle_update(PDO $db, array $authUser, int $id, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid = (int)$authUser['id'];

    $marque  = trim($post['marque']  ?? '');
    $modele  = trim($post['modele']  ?? '');
    $couleur = trim($post['couleur'] ?? '');
    $immat   = trim($post['immatriculation'] ?? '');
    $energie = trim($post['energie'] ?? '');
    $places  = (int)($post['places'] ?? 4);
    $d1      = trim($post['date_premiere_immatriculation'] ?? '');
    if ($d1 === '' || !DateTime::createFromFormat('Y-m-d', $d1)) $d1 = null;

    $sql = "UPDATE vehicules SET
              marque=:marque, modele=:modele, couleur=:couleur,
              immatriculation=:immatriculation, energie=:energie, places=:places,
              date_premiere_immatriculation=:d1
            WHERE id=:id AND utilisateur_id=:uid";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'marque' => $marque,
        'modele' => $modele,
        'couleur' => $couleur,
        'immatriculation' => $immat,
        'energie' => $energie,
        'places' => $places,
        'd1' => $d1,
        'id' => $id,
        'uid' => $uid,
    ]);

    if (function_exists('flash')) flash('success', 'Véhicule mis à jour.');
    header('Location: ' . url('profil') . '#tab-vehicules');
    exit;
}



function profile_role_update(PDO $db, array $authUser, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid = (int)$authUser['id'];

    $allowed = ['passager', 'chauffeur', 'lesdeux'];
    $roleCov = strtolower(trim($post['role'] ?? ''));
    if (!in_array($roleCov, $allowed, true)) {
        if (function_exists('flash')) flash('error', 'Choix invalide.');
        header('Location: ' . url('profil') . '?error=role#tab-voyages');
        exit;
    }

    // upsert dans preferences.role_covoiturage
    $exists = $db->prepare("SELECT id FROM preferences WHERE utilisateur_id=?");
    $exists->execute([$uid]);
    if ($exists->fetchColumn()) {
        $q = $db->prepare("UPDATE preferences SET role_covoiturage=:r WHERE utilisateur_id=:id");
    } else {
        $q = $db->prepare("INSERT INTO preferences (utilisateur_id, role_covoiturage) VALUES (:id, :r)");
    }
    $q->execute(['r' => $roleCov, 'id' => $uid]);

    $_SESSION['prefs']['role_covoiturage'] = $roleCov;

    if (function_exists('flash')) flash('success', 'Rôle covoiturage mis à jour.');
    header('Location: ' . url('profil') . '?role=ok#tab-voyages');
    exit;
}

function preferences_save(PDO $db, array $authUser, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid = (int)$authUser['id'];

    // valeurs
    $fumeur  = ((string)($post['fumeur']  ?? '0') === '1') ? 1 : 0;
    $animaux = ((string)($post['animaux'] ?? '0') === '1') ? 1 : 0;
    $autre   = trim((string)($post['autre_pref'] ?? ''));
    if ($autre !== '' && mb_strlen($autre) > 120) $autre = mb_substr($autre, 0, 120);

    $allowedRole = ['passager', 'chauffeur', 'lesdeux'];
    $role = array_key_exists('role_covoiturage', $post)
        ? (in_array(strtolower((string)$post['role_covoiturage']), $allowedRole, true) ? strtolower((string)$post['role_covoiturage']) : 'passager')
        : null;

    $aimeParler = ((string)($post['aime_parler'] ?? '0') === '1') ? 1 : 0;

    $allowedMusique = ['silence', 'douce', 'normale', 'forte'];
    $musique = in_array(strtolower((string)($post['musique_niveau'] ?? '')), $allowedMusique, true)
        ? strtolower((string)$post['musique_niveau'])
        : null;

    // upsert
    $exists = $db->prepare("SELECT id FROM preferences WHERE utilisateur_id=?");
    $exists->execute([$uid]);
    $id = $exists->fetchColumn();

    if ($id) {
        $sql = "UPDATE preferences SET
                  fumeur=:fumeur, animaux=:animaux, autre_pref=:autre_pref,
                  role_covoiturage=COALESCE(:role, role_covoiturage),
                  aime_parler=:aime_parler, musique_niveau=:musique
                WHERE utilisateur_id=:uid";
    } else {
        $sql = "INSERT INTO preferences
                  (utilisateur_id, fumeur, animaux, autre_pref, role_covoiturage, aime_parler, musique_niveau)
                VALUES (:uid, :fumeur, :animaux, :autre_pref, :role, :aime_parler, :musique)";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'uid' => $uid,
        'fumeur' => $fumeur,
        'animaux' => $animaux,
        'autre_pref' => ($autre === '' ? null : $autre),
        'role' => $role,
        'aime_parler' => $aimeParler,
        'musique' => $musique,
    ]);

    if (function_exists('flash')) flash('success', 'Préférences enregistrées.');
    header('Location: ' . url('profil') . '#tab-preferences');
    exit;
}

function profile_update_account(PDO $db, array $authUser, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid    = (int)$authUser['id'];
    $prenom = trim($post['prenom'] ?? '');
    $nom    = trim($post['nom'] ?? '');
    $email  = trim($post['email'] ?? '');

    $errors = [];
    if ($prenom === '' || $nom === '' || $email === '') $errors[] = 'Champs requis manquants.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';

    if ($errors) {
        if (function_exists('flash')) flash('error', 'Paramètres invalides.');
        header('Location: ' . url('profil') . '?error=params#tab-parametres');
        exit;
    }

    // email unique
    $q = $db->prepare('SELECT id FROM utilisateurs WHERE email=:email AND id<>:id');
    $q->execute(['email' => $email, 'id' => $uid]);
    if ($q->fetchColumn()) {
        if (function_exists('flash')) flash('error', 'Email déjà utilisé.');
        header('Location: ' . url('profil') . '?error=email#tab-parametres');
        exit;
    }

    $stmt = $db->prepare('UPDATE utilisateurs SET prenom=:p, nom=:n, email=:e WHERE id=:id');
    $stmt->execute(['p' => $prenom, 'n' => $nom, 'e' => $email, 'id' => $uid]);

    // maj session
    $_SESSION['user']['prenom'] = $prenom;
    $_SESSION['user']['nom']    = $nom;
    $_SESSION['user']['email']  = $email;

    if (function_exists('flash')) flash('success', 'Compte mis à jour.');
    header('Location: ' . url('profil') . '?saved=1#tab-parametres');
    exit;
}

function profile_update_password(PDO $db, array $authUser, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) {
        header('Location: ' . url('connexion'));
        exit;
    }
    $uid      = (int)$authUser['id'];
    $password = (string)($post['password'] ?? '');
    $confirm  = (string)($post['password_confirm'] ?? '');

    $errors = [];
    if (strlen($password) < 8)   $errors[] = 'Mot de passe trop court.';
    if ($password !== $confirm)  $errors[] = 'Confirmation différente.';
    if ($errors) {
        if (function_exists('flash')) flash('error', 'Mot de passe invalide.');
        header('Location: ' . url('profil') . '?error=password#tab-securite');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE utilisateurs SET mot_de_passe_hash=:pwd WHERE id=:id');
    $stmt->execute(['pwd' => $hash, 'id' => $uid]);

    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }

    if (function_exists('flash')) flash('success', 'Mot de passe mis à jour.');
    header('Location: ' . url('profil') . '?saved=password#tab-securite');
    exit;
}

/* -------------------- Hook GET -------------------- */
/* Quand index.php inclut ce fichier pour GET /profil, on prépare $ctx */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $ctx = profile_prepare($db, $_SESSION['user'] ?? []);
}

// --- Liste des participations actives de l'utilisateur (onglet Voyages)
// Liste des participations actives (en_attente/confirmé)
function profile_list_participations(PDO $db, int $uid): array
{
    if ($uid <= 0) return [];
    $sql = "
      SELECT
            p.id AS part_id,
            v.id AS voyage_id,
            v.ville_depart,
            v.ville_arrivee,
            v.date_depart,
            v.prix,
            p.statut,
            CASE
              WHEN TRIM(CONCAT(COALESCE(u.nom,''),' ',COALESCE(u.prenom,''))) <> ''
                THEN TRIM(CONCAT(u.nom,' ',u.prenom))      
              ELSE u.pseudo
            END AS conducteur
        FROM participations p
        JOIN voyages v       ON v.id = p.voyage_id
        JOIN utilisateurs u  ON u.id = v.chauffeur_id   
        WHERE p.passager_id = :u
        ORDER BY v.date_depart DESC
    ";
    $st = $db->prepare($sql);
    $st->execute([':u' => $uid]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Annuler une participation (passager) ou un voyage (chauffeur)
function profile_participation_delete(PDO $db, int $id, int $uid): void
{
    if ($uid <= 0 || $id <= 0) return;

    // Tenter de trouver une participation à annuler pour le passager
    $st_part = $db->prepare("
        SELECT p.id, p.voyage_id, p.passager_id, p.statut,
               COALESCE(p.places, 1) AS places,
               p.prix AS prix_unitaire,
               v.date_depart,
               v.chauffeur_id
        FROM participations p
        JOIN voyages v ON v.id = p.voyage_id
        WHERE p.id = :id AND p.passager_id = :uid
    ");
    $st_part->execute([':id' => $id, ':uid' => $uid]);
    $participation = $st_part->fetch(PDO::FETCH_ASSOC);

    if ($participation) {
        // CAS 1: Le passager annule sa propre participation
        passenger_cancel_participation($db, $participation, $uid);
        header('Location: ' . url('profil') . '?cancel=ok#tab-participations');
        exit;
    }

    // Si aucune participation trouvée, vérifier si l'ID est un voyage à annuler par le chauffeur
    $st_voyage = $db->prepare("
        SELECT v.id, v.chauffeur_id, v.statut, v.payout_status
        FROM voyages v
        WHERE v.id = :id AND v.chauffeur_id = :uid
    ");
    $st_voyage->execute([':id' => $id, ':uid' => $uid]);
    $voyage = $st_voyage->fetch(PDO::FETCH_ASSOC);

    if ($voyage) {
        // CAS 2: Le chauffeur annule son propre voyage
        driver_cancel_voyage($db, $voyage, $uid);
        header('Location: ' . url('profil') . '?cancel_trip=ok#tab-voyages');
        exit;
    }

    if (function_exists('flash')) flash('error', 'Opération non autorisée ou introuvable.');
    header('Location: ' . url('profil'));
    exit;
}


function passenger_cancel_participation(PDO $db, array $participation, int $uid): void
{
    if ((int)$participation['passager_id'] !== $uid) {
        if (function_exists('flash')) flash('error', 'Accès refusé.');
        return;
    }

    if (!in_array($participation['statut'], ['en_attente', 'confirme'], true)) {
        if (function_exists('flash')) flash('warning', 'Cette participation ne peut plus être annulée.');
        return;
    }

    $db->beginTransaction();
    try {
        // Verrouiller les lignes pour éviter les race conditions
        $st_lock = $db->prepare("
            SELECT p.id, v.statut, v.payout_status
            FROM participations p
            JOIN voyages v ON v.id = p.voyage_id
            WHERE p.id = :id FOR UPDATE
        ");
        $st_lock->execute([':id' => $participation['id']]);
        $locked_data = $st_lock->fetch(PDO::FETCH_ASSOC);

        if (!$locked_data || in_array($locked_data['statut'], ['annule', 'valide']) || $locked_data['payout_status'] === 'released') {
            $db->rollBack();
            if (function_exists('flash')) flash('danger', 'Annulation impossible : le trajet est déjà annulé, terminé ou payé.');
            return;
        }

        // Calcul du délai avant départ (TZ Europe/Paris)
        $tz = new DateTimeZone('Europe/Paris'); // TZ Europe/Paris
        $now = new DateTime('now', $tz);
        $departure = new DateTime($participation['date_depart'], $tz);
        $minutesBeforeDeparture = floor(($departure->getTimestamp() - $now->getTimestamp()) / 60);

        $places = (int)$participation['places'];
        $prix_unitaire = (int)$participation['prix_unitaire'];
        $total_paid = $prix_unitaire * $places;

        $refund_amount = 0;
        $commission = 0;

        if ($minutesBeforeDeparture > 60) {
            // > 60 min avant: remboursement 100%
            $refund_amount = $total_paid;
        } else {
            // ≤ 60 min avant: remboursement partiel, commission pour le site
            $commission = 2 * $places; // multi-places
            $refund_amount = $total_paid - $commission;
        }
        $refund_amount = max(0, $refund_amount); // sécurité

        // Mouvements d'argent
        if ($refund_amount > 0) {
            // Créditer le passager
            $db->prepare("UPDATE utilisateurs SET credits = credits + :amount WHERE id = :uid")->execute([':amount' => $refund_amount, ':uid' => $uid]);
            // Journaliser le remboursement
            $db->prepare("INSERT INTO transactions (user_id, voyage_id, amount, direction, reason) VALUES (:uid, :vid, :amount, 'credit', 'refund_passenger_cancel')")->execute([':uid' => $uid, ':vid' => $participation['voyage_id'], ':amount' => $refund_amount]);
        }

        if ($commission > 0) {
            // Journaliser la commission
            $db->prepare("INSERT INTO transactions (user_id, voyage_id, amount, direction, reason) VALUES (NULL, :vid, :amount, 'credit', 'site_commission')")->execute([':vid' => $participation['voyage_id'], ':amount' => $commission]);
        }

        // Le montant total payé (refund + commission) est déduit du wallet du site (escrow)
        $db->prepare("UPDATE site_wallet SET balance = balance - :amount WHERE id = 1")->execute([':amount' => $total_paid]);

        // Mettre à jour la participation
        $db->prepare("UPDATE participations SET statut = 'annule' WHERE id = :id")->execute([':id' => $participation['id']]);

        // Libérer les places
        $db->prepare("UPDATE voyages SET places_disponibles = places_disponibles + :places WHERE id = :vid")->execute([':places' => $places, ':vid' => $participation['voyage_id']]);

        $db->commit();
        if (function_exists('flash')) flash('success', 'Votre participation a été annulée.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('flash')) flash('danger', "Erreur technique lors de l'annulation: " . $e->getMessage());
    }
}


function driver_cancel_voyage(PDO $db, array $voyage, int $uid): void
{
    if ((int)$voyage['chauffeur_id'] !== $uid) {
        if (function_exists('flash')) flash('error', 'Accès refusé.');
        return;
    }

    if (in_array($voyage['statut'], ['annule', 'valide']) || $voyage['payout_status'] === 'released') {
        if (function_exists('flash')) flash('warning', 'Ce trajet ne peut plus être annulé.');
        return;
    }

    $db->beginTransaction();
    try {
        // Lister tous les passagers actifs pour ce voyage
        $st_passengers = $db->prepare("
            SELECT id, passager_id, places, prix AS prix_unitaire
            FROM participations
            WHERE voyage_id = :vid AND statut IN ('en_attente', 'confirme')
            FOR UPDATE
        ");
        $st_passengers->execute([':vid' => $voyage['id']]);
        $passengers = $st_passengers->fetchAll(PDO::FETCH_ASSOC);

        $total_refunded = 0;

        foreach ($passengers as $p) {
            $passenger_id = (int)$p['passager_id'];
            $places = (int)$p['places'];
            $prix_unitaire = (int)$p['prix_unitaire'];
            $refund_amount = $prix_unitaire * $places; // Remboursement 100%

            if ($refund_amount > 0) {
                // Créditer le passager
                $db->prepare("UPDATE utilisateurs SET credits = credits + :amount WHERE id = :uid")->execute([':amount' => $refund_amount, ':uid' => $passenger_id]);
                // Journaliser
                $db->prepare("INSERT INTO transactions (user_id, voyage_id, amount, direction, reason) VALUES (:uid, :vid, :amount, 'credit', 'refund_driver_cancel')")->execute([':uid' => $passenger_id, ':vid' => $voyage['id'], ':amount' => $refund_amount]);

                $total_refunded += $refund_amount;
            }
            // Mettre à jour la participation
            $db->prepare("UPDATE participations SET statut = 'annule' WHERE id = :id")->execute([':id' => $p['id']]);
        }

        // Débiter le montant total du wallet site (escrow)
        if ($total_refunded > 0) {
            $db->prepare("UPDATE site_wallet SET balance = balance - :amount WHERE id = 1")->execute([':amount' => $total_refunded]);
        }

        // Annuler le voyage
        $db->prepare("UPDATE voyages SET statut = 'annule', payout_status = 'canceled' WHERE id = :id")->execute([':id' => $voyage['id']]);

        $db->commit();
        if (function_exists('flash')) flash('success', 'Trajet annulé. Tous les passagers ont été remboursés.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('flash')) flash('danger', "Erreur technique lors de l'annulation du trajet: " . $e->getMessage());
    }
}

// Passager : participer 
if (!function_exists('trajet_participer')) {
    function trajet_participer(PDO $db, int $voyageId, int $uid): void
    {
        if ($uid <= 0 || $voyageId <= 0) return;

        try {
            if (function_exists('require_post')) require_post();
            $db->beginTransaction();

            // Lock voyage et disponibilites
            $st = $db->prepare("SELECT id, chauffeur_id, prix, statut, places_disponibles
                            FROM voyages WHERE id=:id FOR UPDATE");
            $st->execute([':id' => $voyageId]);
            $voyage = $st->fetch(PDO::FETCH_ASSOC);
            if (!$voyage) throw new RuntimeException('Trajet introuvable.');
            if (($voyage['statut'] ?? '') === 'annule') throw new RuntimeException('Trajet annule.');

            $chk = $db->prepare("SELECT 1 FROM participations
                             WHERE voyage_id=:vid AND passager_id=:uid
                               AND statut IN ('en_attente','confirme')
                             LIMIT 1 FOR UPDATE");
            $chk->execute([':vid' => $voyageId, ':uid' => $uid]);
            if ($chk->fetchColumn()) throw new RuntimeException('Vous participez deja a ce trajet.');

            // multi-places
            $places = isset($_POST['places']) ? (int)$_POST['places'] : 1;
            if ($places < 1) {
                $places = 1;
            }

            $places_disponibles = (int)($voyage['places_disponibles'] ?? 0);
            if ($places > $places_disponibles) {
                throw new RuntimeException('Nombre de places demandé supérieur au nombre de places disponibles.');
            }

            $prix_unitaire = (int)($voyage['prix'] ?? 0);
            $total = $prix_unitaire * $places;
            if ($total <= 0) throw new RuntimeException('Montant invalide.');

            // Lock credits user
            $u = $db->prepare("SELECT credits FROM utilisateurs WHERE id=:u FOR UPDATE");
            $u->execute([':u' => $uid]);
            $credits = (int)$u->fetchColumn();
            if ($credits < $total) throw new RuntimeException('Credits insuffisants.');

            // Mouvements
            $db->prepare("UPDATE utilisateurs SET credits = credits - :total WHERE id=:u")
                ->execute([':total' => $total, ':u' => $uid]);

            $db->prepare("UPDATE site_wallet SET balance = balance + :total WHERE id=1")
                ->execute([':total' => $total]);

            $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id) VALUES ('debit', 'participation_pay', :total, :u, :v)")
                ->execute([':total' => $total, ':u' => $uid, ':v' => $voyageId]);

            $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id) VALUES ('credit', 'participation_pay', :total, NULL, :v)")
                ->execute([':total' => $total, ':v' => $voyageId]);

            $db->prepare("INSERT INTO participations (voyage_id, passager_id, places, prix, statut, inscrit_le) VALUES (:v, :u, :places, :prix, 'confirme', NOW())")
                ->execute([':v' => $voyageId, ':u' => $uid, ':places' => $places, ':prix' => $prix_unitaire]);

            $db->prepare("UPDATE voyages SET places_disponibles = places_disponibles - :places, payout_status='pending' WHERE id=:v")
                ->execute([':places' => $places, ':v' => $voyageId]);

            $db->commit();
            if (function_exists('flash')) flash('success', 'Participation payee et confirmee.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (function_exists('flash')) flash('danger', $e->getMessage());
        }
    }
}



// Chauffeur : valider 
// Chauffeur : valider 
// Implements the state machine for starting and ending a trip (start/arrival events).
// This operation is idempotent and does not involve money transfer.
if (!function_exists('profile_voyage_accept')) {
    function profile_voyage_accept(PDO $db, int $voyageId, int $uid): void
    {
        try {
            if (function_exists('require_post')) require_post();
            if (function_exists('verify_csrf')) verify_csrf();

            // récup depuis la route OU fallback sur POST
            // Assuming $voyageId from function parameter is from route, if it's 0, check POST
            $voyageId = (int)($voyageId ?? $_POST['voyage_id'] ?? 0); // Use the parameter $voyageId first

            if ($voyageId <= 0 || $uid <= 0) { // Re-add $uid check here
                if (function_exists('flash')) {
                    flash('danger', "Trajet introuvable : identifiant manquant.");
                }
                header('Location: ' . BASE_URL . '/profil?tab=voyages');
                exit;
            }

            $db->beginTransaction();

            $st = $db->prepare("SELECT id, chauffeur_id FROM voyages WHERE id=:id FOR UPDATE");
            $st->execute([':id' => $voyageId]);
            $voyage = $st->fetch(PDO::FETCH_ASSOC);
            if (!$voyage || (int)$voyage['chauffeur_id'] !== (int)$uid) {
                $db->rollBack();
                if (function_exists('flash')) flash('danger', 'Trajet introuvable ou acces refuse.');
                return;
            }

            $checkStarted = $db->prepare("SELECT 1 FROM transactions WHERE voyage_id=:vid AND reason='trip_started' LIMIT 1");
            $checkStarted->execute([':vid' => $voyageId]);
            $hasStarted = (bool)$checkStarted->fetchColumn();
            $checkStarted->closeCursor();

            $checkArrived = $db->prepare("SELECT 1 FROM transactions WHERE voyage_id=:vid AND reason='trip_arrived' LIMIT 1");
            $checkArrived->execute([':vid' => $voyageId]);
            $hasArrived = (bool)$checkArrived->fetchColumn();
            $checkArrived->closeCursor();

            if (!$hasStarted) {
                // Idempotence : si le versement n'a pas déjà été effectué
                $st_payout_status = $db->prepare("SELECT payout_status FROM voyages WHERE id=:v FOR UPDATE");
                $st_payout_status->execute([':v' => $voyageId]);
                $current_payout_status = $st_payout_status->fetchColumn();
                if ($current_payout_status !== 'released') {
                    // Calcule le total encaissé et le nombre de participations actives
                    $st_calc_payout = $db->prepare("
                      SELECT COUNT(*) AS nb,
                             COALESCE(SUM(COALESCE(p.prix, v.prix)), 0) AS paid
                      FROM participations p
                      JOIN voyages v ON v.id = p.voyage_id
                      WHERE p.voyage_id = :v
                        AND p.statut IN ('en_attente','confirme')
                    ");
                    $st_calc_payout->execute([':v' => $voyageId]);
                    $payout_data = $st_calc_payout->fetch(PDO::FETCH_ASSOC);

                    $nb_participations = (int)$payout_data['nb'];
                    $total_paid = (int)$payout_data['paid'];

                    $commission_site = 2 * $nb_participations;
                    $driver_payout = max(0, $total_paid - $commission_site);

                    if ($driver_payout > 0) {
                        // Mouvements d’argent
                        $db->prepare("UPDATE site_wallet SET balance = balance - :driver_payout WHERE id=1")->execute([':driver_payout' => $driver_payout]);
                        $db->prepare("UPDATE utilisateurs SET credits = credits + :driver_payout WHERE id=:chauffeur_id")->execute([':driver_payout' => $driver_payout, ':chauffeur_id' => $uid]);

                        // Journalise les écritures
                        $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id, created_at) VALUES ('debit', 'driver_payout', :driver_payout, NULL, :v, NOW())")->execute([':driver_payout' => $driver_payout, ':v' => $voyageId]);
                        $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id, created_at) VALUES ('credit', 'driver_payout', :driver_payout, :chauffeur_id, :v, NOW())")->execute([':driver_payout' => $driver_payout, ':chauffeur_id' => $uid, ':v' => $voyageId]);
                    }
                    if ($commission_site > 0) {
                        $db->prepare("INSERT INTO transactions (direction, reason, amount, user_id, voyage_id, created_at) VALUES ('credit', 'site_commission', :commission_site, NULL, :v, NOW())")->execute([':commission_site' => $commission_site, ':v' => $voyageId]);
                    }

                    // Marque le versement effectué
                    $db->prepare("UPDATE voyages SET payout_status='released', payout_released_at=NOW() WHERE id=:v")->execute([':v' => $voyageId]);
                }

                $insert = $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason, created_at) VALUES(NULL,:vid,0,'credit','trip_started',NOW())");
                $insert->execute([':vid' => $voyageId]);
                $db->commit();
                if (function_exists('flash')) flash('success', 'Trajet demarre.');
                return;
            }

            if (!$hasArrived) {
                $insert = $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason, created_at) VALUES(NULL,:vid,0,'credit','trip_arrived',NOW())");
                $insert->execute([':vid' => $voyageId]);

                $db->prepare("UPDATE voyages SET statut='valide' WHERE id=:vid")
                    ->execute([':vid' => $voyageId]);

                // --- Envoie mail pour avis au passager --
                $st_voyage_details = $db->prepare("
                    SELECT
                        v.id, v.chauffeur_id, v.ville_depart, v.ville_arrivee, v.date_depart,
                        u_driver.prenom AS driver_prenom, u_driver.nom AS driver_nom, u_driver.pseudo AS driver_pseudo
                    FROM voyages v
                    JOIN utilisateurs u_driver ON u_driver.id = v.chauffeur_id
                    WHERE v.id = :vid
                ");
                $st_voyage_details->execute([':vid' => $voyageId]);
                $voyageDetails = $st_voyage_details->fetch(PDO::FETCH_ASSOC);

                $st_passengers = $db->prepare("
                    SELECT
                        p.passager_id,
                        u_rider.email AS rider_email,
                        u_rider.prenom AS rider_prenom,
                        u_rider.nom AS rider_nom,
                        u_rider.pseudo AS rider_pseudo
                    FROM participations p
                    JOIN utilisateurs u_rider ON u_rider.id = p.passager_id
                    WHERE p.voyage_id = :vid AND p.statut = 'confirme'
                ");
                $st_passengers->execute([':vid' => $voyageId]);
                $activePassengers = $st_passengers->fetchAll(PDO::FETCH_ASSOC);

                if ($voyageDetails && !empty($activePassengers)) {
                    require_once __DIR__ . '/../Services/Mailer.php';
                    require_once __DIR__ . '/../Services/ReviewToken.php';
                    $mailer = new \App\Services\Mailer();

                    error_log("[arrivee] open voyageId={$voyageId} passengers=" . count($activePassengers));

                    foreach ($activePassengers as $p) {
                        try {
                            $expiresAt = new DateTime('+14 days');
                            $token = \App\Services\ReviewToken::create(
                                $db,
                                (int)$voyageDetails['id'],
                                (int)$voyageDetails['chauffeur_id'],
                                (int)$p['passager_id'],
                                $expiresAt
                            );

                            $link = BASE_URL . "/avis/{$token}";
                            $html = "<p>Merci pour votre trajet.</p>
             <p>Laissez votre avis : <a href='{$link}'>{$link}</a></p>";

                            if ($mailer->send($p['rider_email'], "Votre avis sur le trajet #{$voyageId}", $html)) {
                                error_log("[mail] to={$p['rider_email']} sent=1");
                            } else {
                                error_log("[mail] to={$p['rider_email']} sent=0");
                            }
                        } catch (Throwable $e) {
                            error_log("Failed to send review email for voyage {$voyageDetails['id']} to {$p['rider_email']}: " . $e->getMessage());
                        }
                    }
                }
                // --- END NEW ---

                $db->commit();
                queueRideValidationInvites($voyageId);
                if (function_exists('flash')) flash('success', 'Arrivee enregistree.');
                return;
            }

            $db->rollBack();
            if (function_exists('flash')) flash('info', 'Trajet deja finalise.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (function_exists('flash')) flash('danger', $e->getMessage());
        }
    }
}

if (!function_exists('queueRideValidationInvites')) {
    function queueRideValidationInvites(int $voyageId): void
    {
        // TODO: send notifications when trip arrives (placeholder)
    }
}

function release_due_trips(PDO $db): void
{
    // This function handles the payout for completed trips.
    // It is designed to be run as a cron job.
    // It is idempotent and handles multi-place bookings.
    if (!defined('COMMISSION_CREDITS')) define('COMMISSION_CREDITS', 2);

    $sql = "SELECT v.id, v.chauffeur_id, v.prix
            FROM voyages v
            WHERE v.payout_status='pending'
              AND v.date_depart <= NOW()
              AND (v.statut IS NULL OR v.statut <> 'annule')";
    $due = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($due as $v) {
        try {
            $db->beginTransaction();

            // recheck + lock
            $st = $db->prepare("SELECT id, chauffeur_id, prix, payout_status
                                FROM voyages WHERE id=:id FOR UPDATE");
            $st->execute([':id' => $v['id']]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            // idempotent
            if (!$row || $row['payout_status'] !== 'pending') {
                $db->rollBack();
                continue;
            }

            $arrived = $db->prepare("SELECT 1 FROM transactions WHERE voyage_id=:vid AND reason='trip_arrived' LIMIT 1");
            $arrived->execute([':vid' => $v['id']]);
            $hasArrived = (bool)$arrived->fetchColumn();
            $arrived->closeCursor();
            if (!$hasArrived) {
                // idempotent : on attend l'evenement d'arrivee
                $db->rollBack();
                continue;
            }

            $pricing = $db->prepare("SELECT COALESCE(SUM(COALESCE(places,1)),0) AS total_places,
                                           COALESCE(SUM(COALESCE(places,1) * COALESCE(prix, :fallback)),0) AS total_paid
                                    FROM participations
                                    WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')");
            $pricing->execute([':vid' => $v['id'], ':fallback' => (int)($row['prix'] ?? 0)]);
            $totals = $pricing->fetch(PDO::FETCH_ASSOC) ?: ['total_places' => 0, 'total_paid' => 0];
            $pricing->closeCursor();
            $totalPlaces = (int)($totals['total_places'] ?? 0);
            $totalPaid = (int)($totals['total_paid'] ?? 0);

            if ($totalPlaces <= 0) {
                $db->prepare("UPDATE voyages SET payout_status='released', payout_released_at=NOW() WHERE id=:vid")
                    ->execute([':vid' => $v['id']]);
                $db->commit();
                continue;
            }

            $commission = COMMISSION_CREDITS * $totalPlaces;
            if ($commission < 0) $commission = 0;
            $driverPayout = $totalPaid - $commission;
            if ($driverPayout < 0) $driverPayout = 0;

            if ($driverPayout > 0) {
                $wallet = $db->prepare("SELECT balance FROM site_wallet WHERE id=1 FOR UPDATE");
                $wallet->execute();
                $walletRow = $wallet->fetch(PDO::FETCH_ASSOC);
                $wallet->closeCursor();
                if (!$walletRow || (int)$walletRow['balance'] < $driverPayout) {
                    throw new RuntimeException("Escrow insuffisant pour le voyage {$v['id']}.");
                }

                $db->prepare("UPDATE utilisateurs SET credits = credits + :a WHERE id=:u")
                    ->execute([':a' => $driverPayout, ':u' => (int)$row['chauffeur_id']]);
                $db->prepare("UPDATE site_wallet SET balance = balance - :a WHERE id=1")
                    ->execute([':a' => $driverPayout]);

                $insertTx = $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason) VALUES(:user_id,:voyage_id,:amount,:direction,:reason)");
                $insertTx->execute([
                    ':user_id'   => (int)$row['chauffeur_id'],
                    ':voyage_id' => $v['id'],
                    ':amount'    => $driverPayout,
                    ':direction' => 'credit',
                    ':reason'    => 'driver_payout',
                ]);
                $insertTx->execute([
                    ':user_id'   => null,
                    ':voyage_id' => $v['id'],
                    ':amount'    => $driverPayout,
                    ':direction' => 'debit',
                    ':reason'    => 'driver_payout',
                ]);
            }

            $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason) VALUES(NULL,:voyage_id,:amount,'credit','site_commission')")
                ->execute([':voyage_id' => $v['id'], ':amount' => $commission]);

            $db->prepare("UPDATE voyages SET payout_status='released', payout_released_at=NOW() WHERE id=:vid")
                ->execute([':vid' => $v['id']]);
            $db->prepare("UPDATE participations SET statut='valide' WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')")
                ->execute([':vid' => $v['id']]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
        }
    }
}

function profile_list_my_voyages(PDO $db, int $uid, string $view = 'upcoming'): array
{
    if ($uid <= 0) return [];

    switch ($view) {
        case 'done':
            $extra = " AND (v.statut='valide' OR (v.date_depart < NOW() AND (v.statut IS NULL OR v.statut <> 'annule')))";
            $order = " ORDER BY v.date_depart DESC";
            break;
        case 'canceled':
            $extra = " AND v.statut='annule'";
            $order = " ORDER BY v.date_depart DESC";
            break;
        case 'all':
            $extra = "";
            $order = " ORDER BY v.date_depart DESC";
            break;
        default: // a venir
            $extra = " AND v.date_depart >= NOW() AND (v.statut IS NULL OR v.statut <> 'annule')";
            $order = " ORDER BY v.date_depart ASC";
    }

    $sql = "
      SELECT
        v.id,
        v.ville_depart  AS depart,
        v.ville_arrivee AS arrivee,
        DATE_FORMAT(v.date_depart, '%d/%m/%Y') AS date,
        DATE_FORMAT(v.date_depart, '%H:%i')     AS heure,
        v.prix,
        COALESCE(v.statut,'') AS statut,
        CASE WHEN ve.energie IN ('Electrique','lectrique','Hybride','Hybride rechargeable') THEN 1 ELSE 0 END AS eco,
        COALESCE(tx.started, 0) AS has_started,
        COALESCE(tx.arrived, 0) AS has_arrived
      FROM voyages v
      LEFT JOIN vehicules ve ON ve.id = v.vehicule_id
      LEFT JOIN (
        SELECT voyage_id,
               MAX(CASE WHEN reason = 'trip_started' THEN 1 ELSE 0 END) AS started,
               MAX(CASE WHEN reason = 'trip_arrived' THEN 1 ELSE 0 END) AS arrived
        FROM transactions
        GROUP BY voyage_id
      ) tx ON tx.voyage_id = v.id
      WHERE v.chauffeur_id = :u
      $extra
      $order
      LIMIT 200
    ";
    $st = $db->prepare($sql);
    $st->execute([':u' => $uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['eco'] = (bool)$r['eco'];
        $r['has_started'] = !empty($r['has_started']);
        $r['has_arrived'] = !empty($r['has_arrived']);
    }
    return $rows;
}

function audit_ledger_consistency(PDO $db): array
{
    // 1. Site vs ledger
    $st1 = $db->query("
        SELECT
          sw.balance AS site_balance,
          COALESCE(SUM(CASE WHEN t.direction='credit' THEN t.amount ELSE -t.amount END),0) AS site_ledger
        FROM site_wallet sw
        LEFT JOIN transactions t ON t.user_id IS NULL;
    ");
    $site_reconciliation = $st1->fetch(PDO::FETCH_ASSOC);

    // 2. Top deltas users
    $st2 = $db->query("
        SELECT u.id, u.pseudo, u.credits,
               COALESCE(SUM(CASE WHEN t.direction='credit' THEN t.amount ELSE -t.amount END),0) AS ledger,
               (u.credits - COALESCE(SUM(CASE WHEN t.direction='credit' THEN t.amount ELSE -t.amount END),0)) AS delta
        FROM utilisateurs u
        LEFT JOIN transactions t ON t.user_id = u.id
        GROUP BY u.id
        HAVING ABS(delta) <> 0
        ORDER BY ABS(delta) DESC
        LIMIT 10;
    ");
    $user_deltas = $st2->fetchAll(PDO::FETCH_ASSOC);

    // 3. Synthèse par voyage
    $st3 = $db->query("
        SELECT voyage_id,
          SUM(CASE WHEN reason='participation_pay' AND direction='credit' THEN amount END) AS escrow_in,
          SUM(CASE WHEN reason='driver_payout'      AND direction='debit'  THEN amount END) AS escrow_out,
          SUM(CASE WHEN reason='site_commission'    AND direction='credit' THEN amount END) AS commission
        FROM transactions
        GROUP BY voyage_id
        ORDER BY voyage_id DESC
        LIMIT 10;
    ");
    $trip_synthesis = $st3->fetchAll(PDO::FETCH_ASSOC);

    return [
        'site_reconciliation' => $site_reconciliation,
        'user_deltas' => $user_deltas,
        'trip_synthesis' => $trip_synthesis,
    ];
}

if (!function_exists('send_review_invites')) {
    function send_review_invites(PDO $db, int $voyageId): void
    {
        app_log("[avis] arrivee: voyageId={$voyageId}");

        // 1) Données trajet + passagers confirmés
        $st_voyage = $db->prepare("
        SELECT v.id, v.chauffeur_id, v.ville_depart, v.ville_arrivee, v.date_depart,
               u.prenom AS driver_prenom, u.nom AS driver_nom, u.pseudo AS driver_pseudo
        FROM voyages v
        JOIN utilisateurs u ON u.id = v.chauffeur_id
        WHERE v.id = :vid
    ");
        $st_voyage->execute([':vid' => $voyageId]);
        $voyage = $st_voyage->fetch(PDO::FETCH_ASSOC);

        $st_pass = $db->prepare("
        SELECT p.passager_id,
               u.email   AS rider_email,
               u.prenom  AS rider_prenom,
               u.nom     AS rider_nom,
               u.pseudo  AS rider_pseudo
        FROM participations p
        JOIN utilisateurs u ON u.id = p.passager_id
        WHERE p.voyage_id = :vid AND p.statut = 'confirme'
    ");
        $st_pass->execute([':vid' => $voyageId]);
        $passagers = $st_pass->fetchAll(PDO::FETCH_ASSOC);

        app_log("[avis] voyage=" . json_encode($voyage ?: null));
        app_log("[avis] passagersCount=" . (is_array($passagers) ? count($passagers) : 0));

        if (!$voyage || empty($passagers)) {
            app_log("[avis][STOP] pas de voyage ou aucun passager actif");
            return;
        }

        // 2) Prépare contexte commun
        $mailer = new \App\Services\Mailer();

        $driverName = trim(($voyage['driver_prenom'] ?? '') . ' ' . ($voyage['driver_nom'] ?? ''));
        if ($driverName === '') $driverName = $voyage['driver_pseudo'] ?? 'un conducteur';

        try {
            $dt = new \DateTime($voyage['date_depart']);
            $tripLabel = $voyage['ville_depart'] . ' → ' . $voyage['ville_arrivee'] . ' (' . $dt->format('d/m H:i') . ')';
        } catch (\Throwable $e) {
            $tripLabel = $voyage['ville_depart'] . ' → ' . $voyage['ville_arrivee'];
        }

        // ✅ Déclare une seule fois l'expiration
        $expiresAt    = new \DateTime('+14 days');      // objet utile pour affichage
        

        // 3) Boucle d’envoi
        foreach ($passagers as $p) {
            app_log("[avis] envoi rider={$p['passager_id']} email={$p['rider_email']}");

            try {
                $token = \App\Services\ReviewToken::create(
                    $db,
                    (int)$voyage['id'],
                    (int)$voyage['chauffeur_id'],
                    (int)$p['passager_id'],   // <- sans espace
                    $expiresAt                // <- on passe l'objet, pas une string
                );
                if (!$token) {
                    app_log("[avis][WARN] token non créé (rider={$p['passager_id']})");
                    continue;
                }
                app_log("[avis] token={$token}");

                // Lien vers la page d’avis (route typique /avis/{token})
                $reviewUrl = BASE_URL . '/avis/' . urlencode($token);

                $riderName = trim(($p['rider_prenom'] ?? '') . ' ' . ($p['rider_nom'] ?? ''));
                if ($riderName === '') $riderName = $p['rider_pseudo'] ?? 'passager';

                // Envoi de l'email (HTML simple)
                $ok = $mailer->sendReviewEmail($p['rider_email'], [
                    'rider_name'  => $riderName,
                    'driver_name' => $driverName,
                    'trip_label'  => $tripLabel,
                    'review_url'  => $reviewUrl,
                    'expires_at'  => $expiresAt->format('d/m/Y'),
                ]);

                if (!$ok) app_log("[avis][mail][FAIL] {$p['rider_email']}");
            } catch (\Throwable $e) {
                app_log("[avis][ERR] " . $e->getMessage());
            }
        }
    }
}

if (!function_exists('handle_trip_arrival')) {
    function handle_trip_arrival(PDO $db, int $voyageId, int $uid)
    {
        $db->beginTransaction();
        try {
            $st = $db->prepare("SELECT id, chauffeur_id, statut FROM voyages WHERE id=:id FOR UPDATE");
            $st->execute([':id' => $voyageId]);
            $voyage = $st->fetch(PDO::FETCH_ASSOC);

            if (!$voyage || (int)$voyage['chauffeur_id'] !== $uid) {
                throw new RuntimeException('Trajet introuvable ou accès refusé.');
            }
            if ($voyage['statut'] === 'valide' || $voyage['statut'] === 'annule') {
                app_log("[avis][WARN] Trajet {$voyageId} déjà finalisé ou annulé. Statut: {$voyage['statut']}");
                $db->rollBack();
                return; // Idempotence
            }

            // Mettre à jour le statut du voyage
            $db->prepare("UPDATE voyages SET statut='valide' WHERE id=:vid")->execute([':vid' => $voyageId]);
            app_log("[trajet] statut=valide pour voyageId={$voyageId}");

            // Envoyer les invitations pour avis
            send_review_invites($db, $voyageId);

            $db->commit();
            if (function_exists('flash')) flash('success', 'Trajet clôturé, les invitations pour avis ont été envoyées.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            app_log("[avis][ERROR] " . $e->getMessage());
            if (function_exists('flash')) flash('danger', $e->getMessage());
        }
    }
}
