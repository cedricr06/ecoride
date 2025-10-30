<?php
// app/Entities/Avis.php
declare(strict_types=1);

final class Avis
{
    public int $driver_id;
    public int $passenger_id;
    public int $trip_id;
    public int $rating;           
    public string $comment;       
    public string $status = 'approved';
    /** @var array<string,mixed> */
    public array $moderation = [];

    public function __construct(
        int $driver_id, int $passenger_id, int $trip_id,
        int $rating, string $comment, string $status='approved', array $moderation=[]
    ) {
        $this->driver_id   = $driver_id;
        $this->passenger_id= $passenger_id;
        $this->trip_id     = $trip_id;
        $this->rating      = $rating;
        $this->comment     = $comment;
        $this->status      = $status;
        $this->moderation  = $moderation;
    }

    /** @return array<string,mixed> */
    public function toDocument(): array {
        $now = new MongoDB\BSON\UTCDateTime();
        return [
            'driver_id'   => $this->driver_id,
            'passenger_id'=> $this->passenger_id,
            'trip_id'     => $this->trip_id,
            'rating'      => $this->rating,
            'comment'     => $this->comment,
            'status'      => $this->status,
            'moderation'  => $this->moderation,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
    }
}