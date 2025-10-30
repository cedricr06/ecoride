<?php
// public/ajax/getCredits.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../app/core/db.php';

try {
    $pdo = db();
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($uid<=0) { echo json_encode(['ok'=>false,'error'=>'not_auth']); exit; }

    $st = $pdo->prepare("SELECT credits FROM utilisateurs WHERE id=:id");
    $st->execute([':id'=>$uid]);
    $credits = (int)($st->fetchColumn() ?: 0);

    echo json_encode(['ok'=>true,'credits'=>$credits], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
