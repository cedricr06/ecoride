<?php
// app/repository/AvisRepository.php
declare(strict_types=1);

use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../core/mongo.php';

final class AvisRepository
{
    private Collection $avis;
    private Collection $stats;

    public function __construct()
    {
        [, $this->avis, $this->stats] = mongo();
    }

    /** @return array<int, array<string,mixed>> */
    public function findApprovedByDriver(int $driverId, ?int $tripId=null, int $limit=20): array
    {
        $filter = ['driver_id' => $driverId, 'status' => 'approved'];
        if ($tripId) $filter['trip_id'] = $tripId;

        $cursor = $this->avis->find(
            $filter,
            [
                'sort' => ['created_at' => -1],
                'limit'=> $limit,
                'projection' => ['_id'=>0], // JSON clean
            ]
        );
        return $cursor->toArray();
    }

    /** @param array<string,mixed> $doc */
    public function insertOne(array $doc): void
    {
        $now = new UTCDateTime();
        $doc['created_at'] = $doc['created_at'] ?? $now;
        $doc['updated_at'] = $now;
        $this->avis->insertOne($doc);
    }

    /** Recalcule avg/count pour un driver */
    public function recomputeDriverStats(int $driverId): void
    {
        $pipeline = [
            ['$match' => ['driver_id' => $driverId, 'status' => 'approved']],
            ['$group' => [
                '_id'        => '$driver_id',
                'count'      => ['$sum' => 1],
                'avg_rating' => ['$avg' => '$rating'],
            ]],
        ];

        $agg = $this->avis->aggregate($pipeline)->toArray();
        $now = new UTCDateTime();

        if ($agg) {
            $row = $agg[0];
            $this->stats->updateOne(
                ['driver_id' => $driverId],
                [
                    '$set' => [
                        'avg_rating' => (float)$row['avg_rating'],
                        'count'      => (int)$row['count'],
                        'updated_at' => $now,
                    ],
                ],
                ['upsert' => true]
            );
        } else {
            // aucun avis → remettre à zéro
            $this->stats->updateOne(
                ['driver_id' => $driverId],
                [
                    '$set' => [
                        'avg_rating' => 0.0,
                        'count'      => 0,
                        'updated_at' => $now,
                    ],
                ],
                ['upsert' => true]
            );
        }
    }

    /** @return array{driver_id:int,avg_rating:float,count:int,updated_at:\MongoDB\BSON\UTCDateTime}|null */
    public function getDriverStats(int $driverId): ?array
    {
        $doc = $this->stats->findOne(['driver_id' => $driverId], ['projection' => ['_id'=>0]]);
        return $doc ?: null;
    }
}
