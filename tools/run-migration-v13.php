<?php
/**
 * Migration v13 — Payment QR images (for COD delivery labels)
 *  - payment_qr : multiple uploaded UPI QR images; exactly one can be active.
 *
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS payment_qr (
    id         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    label      VARCHAR(120) DEFAULT NULL,
    image      VARCHAR(255) NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ payment_qr table ready\n";

echo "\nMigration v13 complete.\n";
