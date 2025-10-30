<?php
// app/Services/AvisService.php
declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../Models/AvisRepository.php';

final class AvisService
{
    private AvisRepository $repo;

    public function __construct() { $this->repo = new AvisRepository(); }

    /** @return array<int, array<string,mixed>> */
    public function listForDriver(int $driverId, ?int $tripId=null, int $limit=20): array
    {
        if ($driverId <= 0) return [];
        $rows = $this->repo->findApprovedByDriver($driverId, $tripId, $limit);

        // Normalisation JSON (dates → ISO string)
        foreach ($rows as &$r) {
            $r['created_at'] = $this->toIso($r['created_at'] ?? null);
            $r['updated_at'] = $this->toIso($r['updated_at'] ?? null);
        }
        return $rows;
    }

    /** @return array{avg_rating:float,count:int}|null */
    public function driverStats(int $driverId): ?array
    {
        $s = $this->repo->getDriverStats($driverId);
        if (!$s) return null;
        return [
            'avg_rating' => (float)$s['avg_rating'],
            'count'      => (int)$s['count'],
        ];
    }

    /** @param array<string,mixed> $payload */
    public function addReview(array $payload): void
    {
        // Validation minimale
        foreach (['driver_id','passenger_id','trip_id','rating','comment'] as $k) {
            if (!isset($payload[$k])) throw new InvalidArgumentException("missing $k");
        }
        $payload['status'] = $payload['status'] ?? 'approved';

        $this->repo->insertOne($payload);
        $this->repo->recomputeDriverStats((int)$payload['driver_id']);
    }

    /** @param UTCDateTime|string|null $d */
    private function toIso($d): ?string
    {
        if ($d instanceof UTCDateTime) return $d->toDateTime()->format(DATE_ATOM);
        if (is_string($d)) return $d;
        return null;
    }
}
