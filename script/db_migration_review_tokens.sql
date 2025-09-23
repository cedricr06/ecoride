-- Migration: Create review_tokens table for post-trip passenger reviews.
-- This table stores unique tokens for passengers to leave reviews for drivers after a trip.
CREATE TABLE IF NOT EXISTS review_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voyage_id INT NOT NULL,
  driver_id INT NOT NULL,
  rider_id INT NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (voyage_id), INDEX (driver_id), INDEX (rider_id), INDEX (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
