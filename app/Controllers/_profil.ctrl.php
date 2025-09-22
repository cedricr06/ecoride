<?php
require_login();
global $db;

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

// Annuler une participation (et rendre la/les place(s))
function profile_participation_delete(PDO $db, int $participationId, int $uid): void
{
    if ($uid <= 0 || $participationId <= 0) return;

    try {
        $db->beginTransaction();

        $st = $db->prepare("
            SELECT p.id, p.voyage_id, p.passager_id, p.statut,
                   COALESCE(p.places,1) AS places,
                   v.prix, v.date_depart,
                   COALESCE(v.statut,'') AS voyage_statut,
                   COALESCE(v.payout_status,'') AS payout_status
            FROM participations p
            JOIN voyages v ON v.id = p.voyage_id
            WHERE p.id = :id
              AND p.passager_id = :uid
            FOR UPDATE
        ");
        $st->execute([':id' => $participationId, ':uid' => $uid]);
        $participation = $st->fetch(PDO::FETCH_ASSOC);
        $st->closeCursor();

        if ($participation) {
            $statut = (string)($participation['statut'] ?? '');
            if (!in_array($statut, ['en_attente', 'confirme'], true)) {
                $db->rollBack();
                if (function_exists('flash')) flash('warning', "Aucune participation active à annuler.");
                return;
            }

            $voyageStatut = (string)($participation['voyage_statut'] ?? '');
            if ($voyageStatut === 'valide') {
                $db->rollBack();
                if (function_exists('flash')) flash('danger', "Annulation impossible : trajet déjà validé.");
                return;
            }

            $payoutStatus = (string)($participation['payout_status'] ?? '');
            if ($payoutStatus === 'released') {
                $db->rollBack();
                if (function_exists('flash')) flash('danger', "Annulation impossible : paiement chauffeur déjà effectué.");
                return;
            }

            $paid = $db->prepare("SELECT 1 FROM transactions WHERE voyage_id=:vid AND reason='driver_payout' LIMIT 1");
            $paid->execute([':vid' => (int)$participation['voyage_id']]);
            $driverPaid = $paid->fetchColumn();
            $paid->closeCursor();
            if ($driverPaid) {
                $db->rollBack();
                if (function_exists('flash')) flash('danger', "Annulation impossible : paiement chauffeur déjà effectué.");
                return;
            }

            $price = (int)$participation['prix'];

            $now = new DateTimeImmutable();
            try {
                $departure = new DateTimeImmutable((string)$participation['date_depart']);
            } catch (Throwable $e) {
                $departure = $now;
            }
            $secondsBeforeDeparture = $departure->getTimestamp() - $now->getTimestamp();

            $commission = 0;
            if ($secondsBeforeDeparture < 3600) {
                $commission = 2;
                if ($price < $commission) {
                    $commission = max(0, $price);
                }
            }
            $refundAmount = max(0, $price - $commission);

            $lockUser = $db->prepare("SELECT credits FROM utilisateurs WHERE id=:u FOR UPDATE");
            $lockUser->execute([':u' => $uid]);
            $userCredits = $lockUser->fetchColumn();
            $lockUser->closeCursor();
            if ($userCredits === false) {
                throw new RuntimeException("Utilisateur introuvable.");
            }

            $walletStmt = $db->query("SELECT balance FROM site_wallet WHERE id=1 FOR UPDATE");
            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
            $walletStmt->closeCursor();
            if (!$wallet) {
                throw new RuntimeException("Caisse site introuvable.");
            }
            if ($refundAmount > (int)$wallet['balance']) {
                throw new RuntimeException("Solde site insuffisant pour remboursement.");
            }

            $db->prepare("UPDATE utilisateurs SET credits = credits + :a WHERE id=:u")
                ->execute([':a' => $refundAmount, ':u' => $uid]);

            $db->prepare("UPDATE site_wallet SET balance = balance - :a WHERE id=1")
                ->execute([':a' => $refundAmount]);

            if ($refundAmount > 0) {
                $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                              VALUES(:u,:vid,:a,'credit','refund')")
                    ->execute([':u' => $uid, ':vid' => (int)$participation['voyage_id'], ':a' => $refundAmount]);

                $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                              VALUES(NULL,:vid,:a,'debit','refund')")
                    ->execute([':vid' => (int)$participation['voyage_id'], ':a' => $refundAmount]);
            }

            if ($commission > 0) {
                $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                              VALUES(NULL,:vid,:a,'credit','site_commission')")
                    ->execute([':vid' => (int)$participation['voyage_id'], ':a' => $commission]);
            }

            $places = (int)$participation['places'];
            if ($places > 0) {
                $db->prepare("
                    UPDATE voyages
                    SET places_disponibles = places_disponibles + :n
                    WHERE id = :vid
                ")->execute([':n' => $places, ':vid' => (int)$participation['voyage_id']]);
            }

            $db->prepare("DELETE FROM participations WHERE id = :id")
                ->execute([':id' => (int)$participation['id']]);

            $db->commit();
            if (function_exists('flash')) flash('success', 'Participation supprimée.');
            return;
        }

        $stVoyage = $db->prepare("
            SELECT v.id, v.chauffeur_id, v.prix, v.date_depart,
                   COALESCE(v.statut,'') AS statut,
                   COALESCE(v.payout_status,'') AS payout_status
            FROM voyages v
            WHERE v.id = :id
            FOR UPDATE
        ");
        $stVoyage->execute([':id' => $participationId]);
        $voyage = $stVoyage->fetch(PDO::FETCH_ASSOC);
        $stVoyage->closeCursor();

        if (!$voyage) {
            throw new RuntimeException("Voyage introuvable.");
        }

        if ((int)$voyage['chauffeur_id'] !== (int)$uid) {
            throw new RuntimeException("Accès refusé.");
        }

        $voyageStatut = (string)($voyage['statut'] ?? '');
        if ($voyageStatut === 'annule') {
            $db->rollBack();
            if (function_exists('flash')) flash('warning', 'Trajet déjà annulé.');
            return;
        }
        if ($voyageStatut === 'valide') {
            $db->rollBack();
            if (function_exists('flash')) flash('danger', "Annulation impossible : trajet déjà validé.");
            return;
        }
        if ((string)($voyage['payout_status'] ?? '') === 'released') {
            $db->rollBack();
            if (function_exists('flash')) flash('danger', "Annulation impossible : paiement chauffeur déjà effectué.");
            return;
        }

        $paid = $db->prepare("SELECT 1 FROM transactions WHERE voyage_id=:vid AND reason='driver_payout' LIMIT 1");
        $paid->execute([':vid' => (int)$voyage['id']]);
        $driverPaid = $paid->fetchColumn();
        $paid->closeCursor();
        if ($driverPaid) {
            $db->rollBack();
            if (function_exists('flash')) flash('danger', "Annulation impossible : paiement chauffeur déjà effectué.");
            return;
        }

        $ps = $db->prepare("
            SELECT id, passager_id
            FROM participations
            WHERE voyage_id = :vid
              AND statut IN ('en_attente','confirme')
            FOR UPDATE
        ");
        $ps->execute([':vid' => (int)$voyage['id']]);
        $activeParticipations = $ps->fetchAll(PDO::FETCH_ASSOC);
        $ps->closeCursor();

        $price = (int)$voyage['prix'];
        $totalRefund = $price * count($activeParticipations);

        $walletStmt = $db->query("SELECT balance FROM site_wallet WHERE id=1 FOR UPDATE");
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
        $walletStmt->closeCursor();
        if (!$wallet) {
            throw new RuntimeException("Caisse site introuvable.");
        }
        if ($totalRefund > (int)$wallet['balance']) {
            throw new RuntimeException("Solde site insuffisant pour remboursement.");
        }

        $lockPassenger = $db->prepare("SELECT credits FROM utilisateurs WHERE id=:u FOR UPDATE");
        $creditPassenger = $db->prepare("UPDATE utilisateurs SET credits = credits + :a WHERE id=:u");
        $insertRefund = $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                                      VALUES(:u,:vid,:a,'credit','refund')");
        $insertSiteRefund = $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                                          VALUES(NULL,:vid,:a,'debit','refund')");

        foreach ($activeParticipations as $part) {
            $passagerId = (int)$part['passager_id'];

            $lockPassenger->execute([':u' => $passagerId]);
            $creditsRow = $lockPassenger->fetchColumn();
            $lockPassenger->closeCursor();
            if ($creditsRow === false) {
                throw new RuntimeException("Participant introuvable.");
            }

            $creditPassenger->execute([':a' => $price, ':u' => $passagerId]);
            $insertRefund->execute([':u' => $passagerId, ':vid' => (int)$voyage['id'], ':a' => $price]);
            $insertSiteRefund->execute([':vid' => (int)$voyage['id'], ':a' => $price]);
        }

        $db->prepare("UPDATE site_wallet SET balance = balance - :a WHERE id=1")
            ->execute([':a' => $totalRefund]);

        $db->prepare("UPDATE participations SET statut='annule' WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')")
            ->execute([':vid' => (int)$voyage['id']]);

        $db->prepare("UPDATE voyages SET statut='annule' WHERE id=:vid")
            ->execute([':vid' => (int)$voyage['id']]);

        $db->commit();
        if (function_exists('flash')) flash('success', 'Trajet annulé.');
        return;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('flash')) flash('danger', "Suppression impossible : " . $e->getMessage());
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

            // Lock voyage
            $st = $db->prepare("SELECT id, chauffeur_id, prix, statut, places_disponibles
                            FROM voyages WHERE id=:id FOR UPDATE");
            $st->execute([':id' => $voyageId]);
            $v = $st->fetch(PDO::FETCH_ASSOC);
            if (!$v) throw new RuntimeException('Trajet introuvable.');
            if (($v['statut'] ?? '') === 'annule') throw new RuntimeException('Trajet annulé.');

            // Déjà inscrit actif ?
            $chk = $db->prepare("SELECT 1 FROM participations
                             WHERE voyage_id=:vid AND passager_id=:uid
                               AND statut IN ('en_attente','confirme')
                             LIMIT 1 FOR UPDATE");
            $chk->execute([':vid' => $voyageId, ':uid' => $uid]);
            if ($chk->fetchColumn()) throw new RuntimeException('Vous participez déjà à ce trajet.');

            // 1 seule place
            $places = 1;
            if (isset($v['places_disponibles']) && (int)$v['places_disponibles'] < $places) {
                throw new RuntimeException('Plus de place disponible.');
            }

            $prix      = (int)$v['prix'];
            $totalCost = $prix; // 1 place

            // Lock crédits user
            $u = $db->prepare("SELECT credits FROM utilisateurs WHERE id=:u FOR UPDATE");
            $u->execute([':u' => $uid]);
            $credits = (int)$u->fetchColumn();
            if ($credits < $totalCost) throw new RuntimeException('Crédits insuffisants.');

            // Débit passager
            $db->prepare("UPDATE utilisateurs SET credits = credits - :a WHERE id=:u")
                ->execute([':a' => $totalCost, ':u' => $uid]);

            // Crédit ESCROW (wallet site)
            $db->prepare("UPDATE site_wallet SET balance = balance + :a WHERE id=1")
                ->execute([':a' => $totalCost]);

            // Journaux
            $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                      VALUES(:u,:vid,:a,'debit','participation_pay')")
                ->execute([':u' => $uid, ':vid' => $voyageId, ':a' => $totalCost]);
            $db->prepare("INSERT INTO transactions(user_id, voyage_id, amount, direction, reason)
                      VALUES(NULL,:vid,:a,'credit','participation_pay')")
                ->execute([':vid' => $voyageId, ':a' => $totalCost]);

            // Créer participation
            $db->prepare("INSERT INTO participations(voyage_id, passager_id, places, statut)
                      VALUES(:vid, :uid, 1, 'confirme')")
                ->execute([':vid' => $voyageId, ':uid' => $uid]);

            // Décrément éventuel du stock
            if (isset($v['places_disponibles'])) {
                $db->prepare("UPDATE voyages SET places_disponibles = places_disponibles - 1 WHERE id=:vid")
                    ->execute([':vid' => $voyageId]);
            }

            $db->commit();
            if (function_exists('flash')) flash('success', 'Participation payée et confirmée.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (function_exists('flash')) flash('danger', $e->getMessage());
        }
    }
}



// Chauffeur : valider 
if (!function_exists('profile_voyage_accept')) {
    function profile_voyage_accept(PDO $db, int $voyageId, int $uid): void
    {
        if ($voyageId <= 0 || $uid <= 0) return;

        try {
            if (function_exists('require_post')) require_post();
            if (function_exists('verify_csrf')) verify_csrf();

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
            if (!$row || $row['payout_status'] !== 'pending') {
                $db->rollBack();
                continue;
            }

            // places actives
            $ps = $db->prepare("SELECT COALESCE(SUM(COALESCE(places,1)),0)
                                FROM participations
                                WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')");
            $ps->execute([':vid' => $v['id']]);
            $places = (int)$ps->fetchColumn();

            $prix = (int)$row['prix'];
            $commission   = 2 * max(0, $places);
            $driverPayout = max(0, ($prix - 2)) * max(0, $places);

            // si rien à verser, on clôture quand même
            if ($places <= 0) {
                $db->prepare("UPDATE voyages SET payout_status='released', payout_released_at=NOW() WHERE id=:vid")
                    ->execute([':vid' => $v['id']]);
                $db->commit();
                continue;
            }

            // escrow
            $w = $db->query("SELECT balance FROM site_wallet WHERE id=1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            if ((int)$w['balance'] < $driverPayout) {
                throw new RuntimeException("Escrow insuffisant pour le voyage {$v['id']}.");
            }

            // versement
            $db->prepare("UPDATE utilisateurs SET credits=credits+:a WHERE id=:u")
                ->execute([':a' => $driverPayout, ':u' => $row['chauffeur_id']]);
            $db->prepare("UPDATE site_wallet SET balance=balance-:a WHERE id=1")
                ->execute([':a' => $driverPayout]);

            // journaux
            $db->prepare("INSERT INTO transactions(user_id,voyage_id,amount,direction,reason)
                          VALUES(:u,:vid,:a,'credit','driver_payout')")
                ->execute([':u' => $row['chauffeur_id'], ':vid' => $v['id'], ':a' => $driverPayout]);
            $db->prepare("INSERT INTO transactions(user_id,voyage_id,amount,direction,reason)
                          VALUES(NULL,:vid,:a,'debit','driver_payout')")
                ->execute([':vid' => $v['id'], ':a' => $driverPayout]);
            $db->prepare("INSERT INTO transactions(user_id,voyage_id,amount,direction,reason)
                          VALUES(NULL,:vid,:a,'credit','site_commission')")
                ->execute([':vid' => $v['id'], ':a' => $commission]);

            // clôture
            $db->prepare("UPDATE voyages
                          SET payout_status='released', payout_released_at=NOW()
                          WHERE id=:vid")
                ->execute([':vid' => $v['id']]);

            // marquer participe=valide
            $db->prepare("UPDATE participations SET statut='valide'
                          WHERE voyage_id=:vid AND statut IN ('en_attente','confirme')")
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
        CASE WHEN ve.energie IN ('Electrique','�lectrique','Hybride','Hybride rechargeable') THEN 1 ELSE 0 END AS eco,
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

