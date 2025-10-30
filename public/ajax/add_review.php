<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../app/Services/AvisService.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $svc = new AvisService();
    $svc->addReview([
        'driver_id'    => (int)$input['driver_id'],
        'passenger_id' => (int)$input['passenger_id'],
        'trip_id'      => (int)$input['trip_id'],
        'rating'       => (int)$input['rating'],
        'comment'      => trim((string)$input['comment']),
        'status'       => 'approved',
        'moderation'   => [],
    ]);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
