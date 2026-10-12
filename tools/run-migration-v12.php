<?php
/**
 * Migration v12 — Service pincode (serviceability) management
 *  - service_pincodes : the pincodes/zip areas the store currently delivers to.
 *
 * Seeds the first store pincode 110084 (only if the table is empty).
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS service_pincodes (
    id         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    pincode    VARCHAR(10) NOT NULL,
    label      VARCHAR(120) DEFAULT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uniq_pincode (pincode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ service_pincodes table ready\n";

$n = (int)$pdo->query('SELECT COUNT(*) FROM service_pincodes')->fetchColumn();
if ($n === 0) {
    $pdo->prepare('INSERT INTO service_pincodes(pincode,label,is_active) VALUES(?,?,1)')
        ->execute(['110084','Burari, Delhi']);
    echo "✓ seeded pincode 110084 (Burari, Delhi)\n";
} else {
    echo "ℹ service_pincodes already has data ($n rows) — not seeding\n";
}

echo "\nMigration v12 complete.\n";
