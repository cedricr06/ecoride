<?php
// We are in the project root.
require_once __DIR__ . '/app/core/db.php';

echo "db.php included.\n";

try {
    $pdo = db();
    echo "DB Connection successful.\n";
} catch (\Exception $e) {
    die("DB Connection failed: " . $e->getMessage());
}


// BLOC 1 - Query 1: ALTER TABLE
try {
    $pdo->exec("
        ALTER TABLE transactions
          MODIFY reason ENUM(
            'participation_pay',
            'driver_payout',
            'site_commission',
            'refund_driver_cancel',
            'refund_passenger_cancel',
            'trip_started',
            'trip_arrived'
          ) NOT NULL;
    ");
    echo "ALTER TABLE successful.\n";
} catch (\PDOException $e) {
    // As per instructions, ignore error if it fails (e.g., already altered)
    echo "ALTER TABLE failed, probably because it was already altered. Error: " . $e->getMessage() . "\n";
}

// BLOC 1 - Repair queries
$repair_queries = [
    "UPDATE transactions t JOIN voyages v ON v.id = t.voyage_id SET t.reason = 'trip_arrived' WHERE t.reason = '' AND t.amount = 0 AND t.user_id IS NULL AND v.statut = 'valide';",
    "UPDATE transactions t JOIN (SELECT voyage_id, MIN(id) AS min_id FROM transactions WHERE reason = '' AND amount = 0 AND user_id IS NULL GROUP BY voyage_id) x ON x.min_id = t.id SET t.reason = 'trip_started';",
    "UPDATE transactions t JOIN (SELECT voyage_id, MAX(id) AS max_id FROM transactions WHERE reason = '' AND amount = 0 AND user_id IS NULL GROUP BY voyage_id) y ON y.max_id = t.id LEFT JOIN (SELECT voyage_id, MIN(id) AS min_id FROM transactions WHERE reason = '' AND amount = 0 AND user_id IS NULL GROUP BY voyage_id) x ON x.voyage_id = y.voyage_id SET t.reason = 'trip_arrived' WHERE (x.min_id IS NULL OR y.max_id <> x.min_id);"
];

foreach ($repair_queries as $query) {
    try {
        $affected_rows = $pdo->exec($query);
        echo "Query executed successfully ($affected_rows rows affected): " . substr($query, 0, 60) . "...\n";
    } catch (\PDOException $e) {
        echo "Query failed: " . $e->getMessage() . "\n";
    }
}

// BLOC 1 - Check query
echo "\n--- Check query results ---\n";
try {
    $stmt = $pdo->query("SELECT id, voyage_id, amount, direction, reason, created_at FROM transactions WHERE amount=0 ORDER BY id DESC LIMIT 10;");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "No results found for the check query.\n";
    } else {
        print_r($results);
    }
} catch (\PDOException $e) {
    echo "Check query failed: " . $e->getMessage() . "\n";
}
echo "--- End of check ---\n";

?>