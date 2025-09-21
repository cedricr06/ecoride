<?php

require_admin();

/*
 | Admin controller helpers
 | - All functions use prepared statements (PDO)
 | - CSRF is enforced by callers for POST routes
*/

function admin_dashboard(PDO $db): array
{
    $users_total      = (int)$db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    $users_suspended  = (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE est_suspendu=1")->fetchColumn();
    $trips_today      = (int)$db->query("SELECT COUNT(*) FROM voyages WHERE DATE(date_depart)=CURDATE()")->fetchColumn();

    //  NOUVEAU : solde de la cagnotte du site
    $site_wallet_balance = 0;
    try {
        $q = $db->query("SELECT balance FROM site_wallet WHERE id=1");
        $site_wallet_balance = (int)($q->fetchColumn() ?? 0);
    } catch (Throwable $e) {
        // table absente -> laisser 0 (ou crée-la si besoin)
    }

    return compact(
        'users_total',
        'users_suspended',
        'trips_today',
        'site_wallet_balance'
    );

    // Total users
    try {
        $stats['total_users'] = (int)$db->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn();
    } catch (Throwable $e) { /* table may not exist yet */
    }

    // Suspended users
    try {
        $q = $db->query('SELECT COUNT(*) FROM utilisateurs WHERE est_suspendu = 1');
        $stats['suspended_users'] = (int)$q->fetchColumn();
    } catch (Throwable $e) {
    }

    // Total credits (sum of users credits as proxy)
    try {
        $q = $db->query('SELECT COALESCE(SUM(credits),0) FROM utilisateurs');
        $stats['total_credits'] = (int)$q->fetchColumn();
    } catch (Throwable $e) {
    }

    // Rides today (best-effort; try typical tables/columns)
    try {
        // try on trajets with date_depart
        $stmt = $db->prepare('SELECT COUNT(*) FROM trajets WHERE DATE(date_depart) = CURDATE()');
        $stmt->execute();
        $stats['rides_today'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e1) {
        try {
            // fallback: covoiturages table
            $stmt = $db->prepare('SELECT COUNT(*) FROM covoiturages WHERE DATE(date_depart) = CURDATE()');
            $stmt->execute();
            $stats['rides_today'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e2) { /* ignore */
        }
    }

    return $stats;
}

function admin_list_users(PDO $db): array
{
    try {
        $st = $db->query('SELECT id, pseudo, email, credits, role, COALESCE(est_suspendu,0) AS est_suspendu FROM utilisateurs ORDER BY id DESC');
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function admin_suspend_user(PDO $db, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();
    require_admin();

    $id = isset($post['user_id']) ? (int)$post['user_id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        exit('Requête invalide.');
    }

    $stmt = $db->prepare('UPDATE utilisateurs SET est_suspendu = 1 WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if (function_exists('flash')) flash('success', 'Utilisateur suspendu.');
    header('Location: ' . url('admin') . '#tab-users');
    exit;
}

function admin_reactivate_user(PDO $db, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();
    require_admin();

    $id = isset($post['user_id']) ? (int)$post['user_id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        exit('Requête invalide.');
    }

    $stmt = $db->prepare('UPDATE utilisateurs SET est_suspendu = 0 WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if (function_exists('flash')) flash('success', 'Utilisateur réactivé.');
    header('Location: ' . url('admin') . '#tab-users');
    exit;
}

function admin_stats(PDO $db): array
{
    $result = [
        'rides' => ['labels' => [], 'values' => []],
        'revenue' => ['labels' => [], 'values' => []],
        'total_revenue' => 0,
    ];

    // Rides per day (last 14 days)
    try {
        $sql = "SELECT DATE(date_depart) AS jour, COUNT(*) AS c FROM trajets WHERE date_depart >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(date_depart) ORDER BY jour";
        $st = $db->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $result['rides']['labels'][] = (string)$r['jour'];
            $result['rides']['values'][] = (int)$r['c'];
        }
    } catch (Throwable $e1) {
        try {
            $sql = "SELECT DATE(date_depart) AS jour, COUNT(*) AS c FROM covoiturages WHERE date_depart >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(date_depart) ORDER BY jour";
            $st = $db->query($sql);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $result['rides']['labels'][] = (string)$r['jour'];
                $result['rides']['values'][] = (int)$r['c'];
            }
        } catch (Throwable $e2) { /* leave empty */
        }
    }

    // Revenue per day (best-effort)
    // Try a typical payments table with commission column
    try {
        $sql = "SELECT DATE(created_at) AS jour, SUM(commission) AS rev FROM paiements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY jour";
        $st = $db->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $result['revenue']['labels'][] = (string)$r['jour'];
            $result['revenue']['values'][] = (float)$r['rev'];
            $result['total_revenue'] += (float)$r['rev'];
        }
    } catch (Throwable $e1) {
        // Fallback: compute 10% of price from trajets if column prix exists
        try {
            $sql = "SELECT DATE(date_depart) AS jour, SUM(prix * 0.1) AS rev FROM trajets WHERE date_depart >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(date_depart) ORDER BY jour";
            $st = $db->query($sql);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $result['revenue']['labels'][] = (string)$r['jour'];
                $result['revenue']['values'][] = (float)$r['rev'];
                $result['total_revenue'] += (float)$r['rev'];
            }
        } catch (Throwable $e2) {
            // Leave empty if schema not present
        }
    }

    return $result;
}

function admin_delete_user(PDO $db, array $post): void
{
    require_post();
    if (function_exists('verify_csrf')) verify_csrf();
    require_admin();

    $currentId = (int)($_SESSION['user']['id'] ?? 0);
    $id = isset($post['user_id']) ? (int)$post['user_id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        exit('Requête invalide.');
    }

    if ($id === $currentId) {
        if (function_exists('flash')) flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        header('Location: ' . url('admin') . '#tab-users');
        exit;
    }

    // Vérifie rôle cible (éviter de supprimer le dernier admin)
    $role = 'utilisateur';
    try {
        $st = $db->prepare('SELECT role FROM utilisateurs WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if (function_exists('flash')) flash('error', "Utilisateur introuvable.");
            header('Location: ' . url('admin') . '#tab-users');
            exit;
        }
        $role = (string)($row['role'] ?? 'utilisateur');
    } catch (Throwable $e) { /* keep default */
    }

    if ($role === 'administrateur') {
        try {
            $c = (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'administrateur'")->fetchColumn();
            if ($c <= 1) {
                if (function_exists('flash')) flash('error', 'Impossible de supprimer le dernier administrateur.');
                header('Location: ' . url('admin') . '#tab-users');
                exit;
            }
        } catch (Throwable $e) { /* ignore */
        }
    }

    try {
        $db->beginTransaction();

        // Nettoyage des tables liées (si présentes)
        $tables = [
            ['table' => 'preferences', 'col' => 'utilisateur_id'],
            ['table' => 'profils',     'col' => 'utilisateur_id'],
            ['table' => 'vehicules',   'col' => 'utilisateur_id'],
        ];
        foreach ($tables as $t) {
            try {
                $sql = "DELETE FROM {$t['table']} WHERE {$t['col']} = :id";
                $stm = $db->prepare($sql);
                $stm->execute(['id' => $id]);
            } catch (Throwable $e) {
                // table absente ou sans lignes: ignorer
            }
        }

        // Supprime l'utilisateur
        $del = $db->prepare('DELETE FROM utilisateurs WHERE id = :id');
        $del->execute(['id' => $id]);

        $db->commit();

        if (function_exists('flash')) flash('success', 'Utilisateur supprimé.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ADMIN DELETE USER ERR: ' . $e->getMessage());
        if (function_exists('flash')) flash('error', 'Suppression impossible (contraintes).');
    }

    header('Location: ' . url('admin') . '#tab-users');
    exit;
}
