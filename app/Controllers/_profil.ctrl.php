<?php
// app/Controllers/_profil.ctrl.php

require_login();
global $db;

/* -------------------- Utils -------------------- */

function yearsFromDate(?string $date): ?int {
    if (!$date) return null;
    try {
        $d   = new DateTime($date);
        $now = new DateTime('today');
        return (int)$d->diff($now)->y;
    } catch (Throwable $e) {
        return null;
    }
}

/** Données “de base” de l’utilisateur (table utilisateurs) */
function user_fetch(PDO $db, int $userId): array {
    $st = $db->prepare("
        SELECT id, pseudo, prenom, nom, email, role, credits, avatar_path
        FROM utilisateurs
        WHERE id = ?
    ");
    $st->execute([$userId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

/** Profil + préférences (tables profils, preferences) */
function profile_fetch(PDO $db, int $userId): array {
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
function profile_prepare(PDO $db, array $authUser): array {
    $uid = (int)($authUser['id'] ?? 0);

    if ($uid <= 0) {
        // sécurité : si pas connecté, require_login() plus haut catchera déjà
        return ['user'=>[], 'profil'=>[], 'prefs'=>[], 'age'=>null, 'permisYears'=>null, 'vehicules'=>[]];
    }

    $user = user_fetch($db, $uid);
    if (!$user) {
        // fallback : au pire, on reprend ce qu’il y a en session
        $user = $authUser;
    }

    [$profil, $prefs] = profile_fetch($db, $uid);

    // calculs dérivés
    $age         = yearsFromDate($profil['date_naissance'] ?? null);
    $permisYears = yearsFromDate($profil['date_permis'] ?? null);

    // véhicules du user
    $vehicules = [];
    $qv = $db->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ? ORDER BY id DESC");
    $qv->execute([$uid]);
    $vehicules = $qv->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return compact('user', 'profil', 'prefs', 'age', 'permisYears', 'vehicules');
}

/* -------------------- Handlers POST -------------------- */
/* ⚠️ Toutes les fonctions POST commencent par require_post() + verify_csrf() */

function profile_save(PDO $db, array $authUser, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid = (int)$authUser['id'];

    // Validation légère (garde tes règles existantes)
    if (!function_exists('validate')) {
        // fallback : pas d’erreurs bloquantes si le helper n’est pas dispo
        $clean  = $post;
        $errors = [];
    } else {
        [$clean, $errors] = validate($post, [
            'date_naissance' => 'string:0,10',
            'date_permis'    => 'string:0,10',
            'telephone'      => 'numeros',
            'ville'          => 'string:0,100',
            'bio'            => 'string:0,280',
        ]);
    }

    // normalisation
    $clean['date_naissance']  = $clean['date_naissance'] ?: null;
    $clean['date_permis']     = $clean['date_permis'] ?: null;
    $clean['verifie_identite']= isset($post['verifie_identite']) ? 1 : 0;
    $clean['verifie_tel']     = isset($post['verifie_tel']) ? 1 : 0;

    if (!empty($errors)) {
        // Option simple : message + retour
        if (function_exists('flash')) flash('error','Formulaire invalide.');
        header('Location: '.url('profil').'?error=profil#tab-parametres'); exit;
    }

    // UPSERT profils
    $stmt = $db->prepare("SELECT 1 FROM profils WHERE utilisateur_id=?");
    $stmt->execute([$uid]);

    if ($stmt->fetchColumn()) {
        $sql = "UPDATE profils SET
                  date_naissance=:date_naissance, date_permis=:date_permis,
                  telephone=:telephone, ville=:ville, bio=:bio,
                  verifie_identite=:verifie_identite, verifie_tel=:verifie_tel
                WHERE utilisateur_id=:uid";
    } else {
        $sql = "INSERT INTO profils
                (utilisateur_id, date_naissance, date_permis, telephone, ville, bio, verifie_identite, verifie_tel)
                VALUES (:uid, :date_naissance, :date_permis, :telephone, :ville, :bio, :verifie_identite, :verifie_tel)";
    }
    $stmt_upsert = $db->prepare($sql);
    $stmt_upsert->execute($clean + ['uid'=>$uid]);

    if (function_exists('flash')) flash('success','Profil mis à jour.');
    header('Location: '.url('profil').'?success=1#tab-parametres'); exit;
}

function vehicle_add(PDO $db, array $authUser, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid = (int)$authUser['id'];

    $marque  = trim($post['marque']  ?? '');
    $modele  = trim($post['modele']  ?? '');
    $couleur = trim($post['couleur'] ?? '');
    $immat   = trim($post['immatriculation'] ?? '');
    $energie = trim($post['energie'] ?? '');
    $places  = (int)($post['places'] ?? 4);
    $d1      = trim($post['date_premiere_immatriculation'] ?? '');

    $errors = [];
    if ($marque==='' || $modele==='' || $immat==='' || $energie==='') $errors[]='Champs requis manquants.';
    if ($places < 1 || $places > 9) $errors[]='Nombre de places invalide.';
    if ($d1 !== '' && !DateTime::createFromFormat('Y-m-d', $d1)) $d1 = null;
    if ($d1 === '') $d1 = null;

    if ($errors) {
        if (function_exists('flash')) flash('error','Formulaire véhicule invalide.');
        header('Location: '.url('profil').'#tab-vehicules'); exit;
    }

    $sql = "INSERT INTO vehicules
            (utilisateur_id, marque, modele, couleur, immatriculation, energie, places, date_premiere_immatriculation)
            VALUES (:uid,:marque,:modele,:couleur,:immatriculation,:energie,:places,:d1)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'uid'=>$uid,'marque'=>$marque,'modele'=>$modele,'couleur'=>$couleur,
        'immatriculation'=>$immat,'energie'=>$energie,'places'=>$places,'d1'=>$d1,
    ]);

    if (function_exists('flash')) flash('success','Véhicule ajouté.');
    header('Location: '.url('profil').'?v_added=1#tab-vehicules'); exit;
}

function vehicle_delete(PDO $db, array $authUser, int $id): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid = (int)$authUser['id'];

    $stmt = $db->prepare("DELETE FROM vehicules WHERE id=:id AND utilisateur_id=:uid");
    $stmt->execute(['id'=>$id,'uid'=>$uid]);

    if (function_exists('flash')) flash('success','Véhicule supprimé.');
    header('Location: '.url('profil').'#tab-vehicules'); exit;
}

function vehicle_update(PDO $db, array $authUser, int $id, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
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
        'marque'=>$marque,'modele'=>$modele,'couleur'=>$couleur,
        'immatriculation'=>$immat,'energie'=>$energie,'places'=>$places,
        'd1'=>$d1,'id'=>$id,'uid'=>$uid,
    ]);

    if (function_exists('flash')) flash('success','Véhicule mis à jour.');
    header('Location: '.url('profil').'#tab-vehicules'); exit;
}

function profile_role_update(PDO $db, array $authUser, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid = (int)$authUser['id'];

    $allowed = ['passager','chauffeur','lesdeux'];
    $role = strtolower(trim($post['role'] ?? ''));
    if (!in_array($role, $allowed, true)) {
        if (function_exists('flash')) flash('error','Rôle invalide.');
        header('Location: '.url('profil').'?error=role#tab-voyages'); exit;
    }

    $q = $db->prepare("UPDATE utilisateurs SET role=:role WHERE id=:id");
    $q->execute(['role'=>$role,'id'=>$uid]);

    $_SESSION['user']['role'] = $role;

    if (function_exists('flash')) flash('success','Rôle mis à jour.');
    header('Location: '.url('profil').'?role=ok#tab-voyages'); exit;
}

function preferences_save(PDO $db, array $authUser, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid = (int)$authUser['id'];

    // valeurs
    $fumeur  = ((string)($post['fumeur']  ?? '0') === '1') ? 1 : 0;
    $animaux = ((string)($post['animaux'] ?? '0') === '1') ? 1 : 0;
    $autre   = trim((string)($post['autre_pref'] ?? ''));
    if ($autre !== '' && mb_strlen($autre) > 120) $autre = mb_substr($autre, 0, 120);

    $allowedRole = ['passager','chauffeur','lesdeux'];
    $role = array_key_exists('role_covoiturage', $post)
        ? (in_array(strtolower((string)$post['role_covoiturage']), $allowedRole, true) ? strtolower((string)$post['role_covoiturage']) : 'passager')
        : null;

    $aimeParler = ((string)($post['aime_parler'] ?? '0') === '1') ? 1 : 0;

    $allowedMusique = ['silence','douce','normale','forte'];
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
        'uid'=>$uid,
        'fumeur'=>$fumeur,
        'animaux'=>$animaux,
        'autre_pref'=>($autre === '' ? null : $autre),
        'role'=>$role,
        'aime_parler'=>$aimeParler,
        'musique'=>$musique,
    ]);

    if (function_exists('flash')) flash('success','Préférences enregistrées.');
    header('Location: '.url('profil').'#tab-preferences'); exit;
}

function profile_update_account(PDO $db, array $authUser, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid    = (int)$authUser['id'];
    $prenom = trim($post['prenom'] ?? '');
    $nom    = trim($post['nom'] ?? '');
    $email  = trim($post['email'] ?? '');

    $errors = [];
    if ($prenom==='' || $nom==='' || $email==='') $errors[]='Champs requis manquants.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[]='Email invalide.';

    if ($errors) {
        if (function_exists('flash')) flash('error','Paramètres invalides.');
        header('Location: '.url('profil').'?error=params#tab-parametres'); exit;
    }

    // email unique
    $q = $db->prepare('SELECT id FROM utilisateurs WHERE email=:email AND id<>:id');
    $q->execute(['email'=>$email,'id'=>$uid]);
    if ($q->fetchColumn()) {
        if (function_exists('flash')) flash('error','Email déjà utilisé.');
        header('Location: '.url('profil').'?error=email#tab-parametres'); exit;
    }

    $stmt = $db->prepare('UPDATE utilisateurs SET prenom=:p, nom=:n, email=:e WHERE id=:id');
    $stmt->execute(['p'=>$prenom,'n'=>$nom,'e'=>$email,'id'=>$uid]);

    // maj session
    $_SESSION['user']['prenom'] = $prenom;
    $_SESSION['user']['nom']    = $nom;
    $_SESSION['user']['email']  = $email;

    if (function_exists('flash')) flash('success','Compte mis à jour.');
    header('Location: '.url('profil').'?saved=1#tab-parametres'); exit;
}

function profile_update_password(PDO $db, array $authUser, array $post): void {
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();

    if (empty($authUser['id'])) { header('Location: '.url('connexion')); exit; }
    $uid      = (int)$authUser['id'];
    $password = (string)($post['password'] ?? '');
    $confirm  = (string)($post['password_confirm'] ?? '');

    $errors = [];
    if (strlen($password) < 8)   $errors[]='Mot de passe trop court.';
    if ($password !== $confirm)  $errors[]='Confirmation différente.';
    if ($errors) {
        if (function_exists('flash')) flash('error','Mot de passe invalide.');
        header('Location: '.url('profil').'?error=password#tab-securite'); exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE utilisateurs SET mot_de_passe_hash=:pwd WHERE id=:id');
    $stmt->execute(['pwd'=>$hash,'id'=>$uid]);

    if (function_exists('session_regenerate_id')) { session_regenerate_id(true); }

    if (function_exists('flash')) flash('success','Mot de passe mis à jour.');
    header('Location: '.url('profil').'?saved=password#tab-securite'); exit;
}

/* -------------------- Hook GET -------------------- */
/* Quand index.php inclut ce fichier pour GET /profil, on prépare $ctx */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $ctx = profile_prepare($db, $_SESSION['user'] ?? []);
}
