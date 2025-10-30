<?php
// public/ajax/avis_list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../app/Services/AvisService.php';

    $driverId = (int)($_GET['driver_id'] ?? $_GET['chauffeur_id'] ?? 0);
    $tripId   = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : (isset($_GET['voyage_id']) ? (int)$_GET['voyage_id'] : null);
    $limit    = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;

    if ($driverId <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_driver_id']); exit; }

    $svc   = new AvisService();
    $data  = $svc->listForDriver($driverId, $tripId, $limit);
    $stats = $svc->driverStats($driverId);

    echo json_encode(['ok'=>true, 'data'=>$data, 'stats'=>$stats], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
