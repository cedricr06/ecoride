<?php 
require_once __DIR__ . '/../core/mongo.php';

[$db, $avis, $stats] = mongo();

$avis->insertOne([
  'driver_id'    => (int)$driverId,
  'passenger_id' => (int)$userId,
  'trip_id'      => (int)$tripId,
  'rating'       => max(1, min(5, (int)$rating)),
  'comment'      => (string)$comment,
  'status'       => 'pending',
  'created_at'   => new MongoDB\BSON\UTCDateTime()
]);