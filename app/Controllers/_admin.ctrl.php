<?php
declare(strict_types=1);

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Operation\FindOneAndUpdate;

require_once __DIR__ . '/../core/mongo.php';
require_once __DIR__ . '/../core/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_admin();

// === Handlers JSON (si pas déjà définis) ===
if (!function_exists('json_ok')) { function json_ok(array $d=[]) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]+$d); exit; } }
if (!function_exists('json_err')) { function json_err(string $m,int $c=400){ http_response_code($c); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$m]); exit; } }

// === BLOC POST de modération (early return) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        json_err('csrf_invalid', 403);
    }

    [, $avis, $stats] = mongo();
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';

    try {
        try { $id = new ObjectId((string)$_POST['id']); }
        catch (\Throwable $e) { json_err('invalid_id', 400); }

        $updated = $avis->findOneAndUpdate(
            ['_id' => $id],
            ['$set' => [
                'status'     => $action,
                'updated_at' => new UTCDateTime(),
                'moderation' => [
                    'moderator_id' => (int)($_POST['moderator_id'] ?? 0),
                    'decided_at'   => new UTCDateTime(),
                    'reason'       => trim((string)($_POST['reason'] ?? '')),
                ],
            ]],
            ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );
        if (!$updated) json_err('avis_not_found', 404);

        $driverId = (int)($updated['driver_id'] ?? 0);
        if ($driverId <= 0) json_err('driver_id_missing', 400);

        $agg = $avis->aggregate([
            ['$match' => ['driver_id' => $driverId, 'status' => 'approved']],
            ['$group' => ['_id' => '$driver_id', 'avg' => ['$avg' => '$rating'], 'count' => ['$sum' => 1]]],
        ])->toArray();

        $avg   = $agg ? (float)$agg[0]['avg']   : 0.0;
        $count = $agg ? (int)$agg[0]['count']   : 0;

        $stats->updateOne(
            ['driver_id' => $driverId],
            ['$set' => ['avg_rating' => $avg, 'count' => $count, 'updated_at' => new UTCDateTime()]],
            ['upsert' => true]
        );

        json_ok(['status' => $action, 'driver_id' => $driverId, 'avg' => $avg, 'count' => $count]);
    } catch (\Throwable $e) {
        // error_log($e);
        json_err('db_error', 500);
    }
}


/*
 | Admin controller helpers
 | - All functions use prepared statements (PDO)
 | - CSRF is enforced by callers for POST routes
*/

function admin_pending_reviews(array $query): array
{
    [, $avis] = mongo();

    $limit = 15;
    $page = max(1, (int)($query['page'] ?? 1));
    $filter = ['status' => 'pending'];

    if (isset($query['min']) && $query['min'] !== '') {
        $min = (int)$query['min'];
        if ($min > 0) {
            $filter['rating'] = ['$gte' => $min];
        }
    }

    if (isset($query['driver']) && $query['driver'] !== '') {
        $driverId = (int)$query['driver'];
        if ($driverId > 0) {
            $filter['driver_id'] = $driverId;
        }
    }

    $total = (int)$avis->countDocuments($filter);
    if ($total === 0) {
        return [[], 0, 0, 1];
    }

    $pages = (int)ceil($total / $limit);
    if ($pages > 0 && $page > $pages) {
        $page = $pages;
    }
    $page = max(1, $page);
    $skip = ($page - 1) * $limit;

    if ($total > 0 && $skip >= $total) {
        $page = max(1, $pages);
        $skip = ($page - 1) * $limit;
    }

    $cursor = $avis->find($filter, [
        'sort' => ['created_at' => -1],
        'skip' => $skip,
        'limit' => $limit,
        'projection' => [
            'rating' => 1,
            'comment' => 1,
            'driver_id' => 1,
            'rider_id' => 1,
            'created_at' => 1,
        ],
    ]);

    $pending = iterator_to_array($cursor, false);

    return [$pending, $total, $pages, $page];
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf'];
}

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


