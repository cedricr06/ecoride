<?php
final class Avis {
    public function __construct(
        public int $trajetId,
        public int $chauffeurId,
        public int $userId,
        public int $note,
        public string $comment,
        public int $createdAt
    ) {}
}
