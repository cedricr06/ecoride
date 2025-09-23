<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use DateTime;
use RuntimeException;

class ReviewToken
{
    /**
     * Generates a cryptographically secure random token.
     * @return string
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters long
    }

    /**
     * Creates a new review token in the database.
     * Implements idempotence: if an active, unused token already exists for the given voyage and rider, it returns that token instead of creating a new one.
     * @param PDO $db The PDO database connection.
     * @param int $voyageId The ID of the voyage.
     * @param int $driverId The ID of the driver.
     * @param int $riderId The ID of the rider (passenger).
     * @param DateTime $exp The expiration date and time for the token.
     * @return string The generated or existing token.
     * @throws RuntimeException If the token cannot be created.
     */
    public static function create(PDO $db, int $voyageId, int $driverId, int $riderId, DateTime $exp): string
    {
        // Idempotence check: Look for an existing active token for this voyage and rider
        $stmt = $db->prepare("
            SELECT token FROM review_tokens
            WHERE voyage_id = :voyage_id AND rider_id = :rider_id
              AND used_at IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([
            ':voyage_id' => $voyageId,
            ':rider_id'   => $riderId,
        ]);
        $existingToken = $stmt->fetchColumn();

        if ($existingToken) {
            return (string) $existingToken;
        }

        $token = self::generate();
        $stmt = $db->prepare("
            INSERT INTO review_tokens (voyage_id, driver_id, rider_id, token, expires_at)
            VALUES (:voyage_id, :driver_id, :rider_id, :token, :expires_at)
        ");
        $success = $stmt->execute([
            ':voyage_id'   => $voyageId,
            ':driver_id'    => $driverId,
            ':rider_id'     => $riderId,
            ':token'        => $token,
            ':expires_at'   => $exp->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            throw new RuntimeException("Failed to create review token.");
        }

        return $token;
    }

    /**
     * Retrieves the context for a given token if it's valid (not used and not expired).
     * @param PDO $db The PDO database connection.
     * @param string $token The token string.
     * @return array|null The token data (id, voyage_id, driver_id, rider_id) or null if invalid.
     */
    public static function getContext(PDO $db, string $token): ?array
    {
        $stmt = $db->prepare("
            SELECT id, voyage_id, driver_id, rider_id
            FROM review_tokens
            WHERE token = :token AND used_at IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $context = $stmt->fetch(PDO::FETCH_ASSOC);

        return $context ?: null;
    }

    /**
     * Marks a token as used.
     * @param PDO $db The PDO database connection.
     * @param int $id The ID of the token to mark as used.
     * @throws RuntimeException If the token cannot be marked as used.
     */
    public static function markUsed(PDO $db, int $id): void
    {
        $stmt = $db->prepare("
            UPDATE review_tokens SET used_at = NOW()
            WHERE id = :id AND used_at IS NULL
        ");
        $success = $stmt->execute([':id' => $id]);

        if (!$success || $stmt->rowCount() === 0) {
            throw new RuntimeException("Failed to mark review token {$id} as used or it was already used.");
        }
    }
}
