<?php
require_login();
global $db;

/* -------------------- Utils -------------------- */

function yearsFromDate(?string $date): ?int
{
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

    $qv = $db->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ? ORDER BY id DESC");
    $qv->execute([$uid]);
    $vehicules = $qv->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $my_participations = profile_list_participations($db, $uid);
    $voyages = profile_list_my_voyages($db, $uid);

    return compact('user', 'profil', 'prefs', 'age', 'permisYears', 'vehicules', 'my_participations', 'voyages');
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
    $clean['verifie_identite'] = isset($post['verifie_identite']) ? 1 : 0;
    $clean['verifie_tel']     = isset($post['verifie_tel']) ? 1 : 0;

    if (!empty($errors)) {
        // Option simple : message + retour
        if (function_exists('flash')) flash('error', 'Formulaire invalide.');
        header('Location: ' . url('profil') . '?error=profil#tab-parametres');
        exit;
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
    $stmt_upsert->execute($clean + ['uid' => $uid]);

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
    $immat   = trim($post['immatriculation'] ?? '');
    $energie = trim($post['energie'] ?? '');
    $places  = (int)($post['places'] ?? 4);
    $d1      = trim($post['date_premiere_immatriculation'] ?? '');

    $errors = [];
    if ($marque === '' || $modele === '' || $immat === '' || $energie === '') $errors[] = 'Champs requis manquants.';
    if ($places < 1 || $places > 9) $errors[] = 'Nombre de places invalide.';
    if ($d1 !== '' && !DateTime::createFromFormat('Y-m-d', $d1)) $d1 = null;
    if ($d1 === '') $d1 = null;

    if ($errors) {
        if (function_exists('flash')) flash('error', 'Formulaire véhicule invalide.');
        header('Location: ' . url('profil') . '#tab-vehicules');
        exit;
    }

    $sql = "INSERT INTO vehicules
            (utilisateur_id, marque, modele, couleur, immatriculation, energie, places, date_premiere_immatriculation)
            VALUES (:uid,:marque,:modele,:couleur,:immatriculation,:energie,:places,:d1)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'uid' => $uid,
        'marque' => $marque,
        'modele' => $modele,
        'couleur' => $couleur,
        'immatriculation' => $immat,
        'energie' => $energie,
        'places' => $places,
        'd1' => $d1,
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

// Annuler une participation (et rendre la/les place(s))
function profile_participation_delete(PDO $db, int $participationId, int $uid): void
{
    if ($uid <= 0 || $participationId <= 0) return;

    try {
        $db->beginTransaction();

        // On verrouille la ligne pour être safe
        $st = $db->prepare("
            SELECT p.id, p.voyage_id, p.passager_id, COALESCE(p.places,1) AS places
            FROM participations p
            WHERE p.id = :id
            FOR UPDATE
        ");
        $st->execute([':id' => $participationId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);

        if (!$p || (int)$p['passager_id'] !== (int)$uid) {
            throw new RuntimeException("Participation introuvable.");
        }

        // Si tu gères les places restantes sur le voyage,
        // on les rétablit avant de supprimer la participation.
        $n = (int)$p['places'];
        if ($n > 0) {
            $db->prepare("
                UPDATE voyages
                SET places_disponibles = places_disponibles + :n
                WHERE id = :vid
            ")->execute([':n' => $n, ':vid' => (int)$p['voyage_id']]);
        }

        // Suppression définitive
        $db->prepare("DELETE FROM participations WHERE id = :id")
            ->execute([':id' => (int)$p['id']]);

        $db->commit();
        if (function_exists('flash')) flash('success', 'Participation supprimée.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('flash')) flash('danger', "Suppression impossible : " . $e->getMessage());
    }
}

function profile_voyage_cancel(PDO $db, int $voyageId, int $uid): void
{
    if ($uid <= 0 || $voyageId <= 0) return;

    try {
        $db->beginTransaction();

        // 1) Vérifier que je suis bien le chauffeur
        $st = $db->prepare("SELECT id, chauffeur_id FROM voyages WHERE id=:id FOR UPDATE");
        $st->execute([':id' => $voyageId]);
        $v = $st->fetch(PDO::FETCH_ASSOC);
        if (!$v || (int)$v['chauffeur_id'] !== (int)$uid) {
            throw new RuntimeException("Voyage introuvable.");
        }

        // 2) Récupérer les participations actives
        $ps = $db->prepare("
          SELECT id, COALESCE(places,1) AS places
          FROM participations
          WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')
          FOR UPDATE
        ");
        $ps->execute([':vid' => $voyageId]);
        $parts = $ps->fetchAll(PDO::FETCH_ASSOC);

        // 3) Réincrémenter les places (si tu stockes places_disponibles)
        if ($parts) {
            $sum = array_sum(array_map(fn($r) => (int)$r['places'], $parts));
            $db->prepare("UPDATE voyages SET places_disponibles = places_disponibles + :n WHERE id=:vid")
                ->execute([':n' => $sum, ':vid' => $voyageId]);
        }

        // 4) Supprimer les participations actives
        $db->prepare("DELETE FROM participations WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')")
            ->execute([':vid' => $voyageId]);

        // 5) Marquer le voyage annulé (soft)
        $db->prepare("UPDATE voyages SET statut='annule' WHERE id=:vid")
            ->execute([':vid' => $voyageId]);

        $db->commit();
        if (function_exists('flash')) flash('success', 'Voyage annulé.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('flash')) flash('danger', 'Annulation impossible : ' . $e->getMessage());
    }
}


function profile_list_my_voyages(PDO $db, int $uid): array
{
    if ($uid <= 0) return [];

    $sql = "
        SELECT
            v.id,
            v.ville_depart  AS depart,
            v.ville_arrivee AS arrivee,
            DATE_FORMAT(v.date_depart, '%d/%m/%Y') AS date,
            DATE_FORMAT(v.date_depart, '%H:%i')     AS heure,   -- pas de v.heure !
            v.prix,
            CASE
                WHEN ve.energie IN ('Electrique','Électrique','Hybride','Hybride rechargeable') THEN 1
                ELSE 0
            END AS eco
            FROM voyages v
            LEFT JOIN vehicules ve ON ve.id = v.vehicule_id
            WHERE v.chauffeur_id = :u
                AND (v.statut IS NULL OR v.statut NOT IN ('annule','supprime'))
            ORDER BY v.date_depart ASC
            LIMIT 200
            ";

    $st = $db->prepare($sql);
    $st->execute([':u' => $uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['eco'] = (bool)$r['eco'];
    }
    return $rows;
}
