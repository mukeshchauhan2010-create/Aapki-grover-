<?php
/**
 * Migration v8
 *  - addresses.alt_phone : optional alternate mobile number, per address.
 *    Used by the delivery agent if the primary mobile is not reachable.
 *
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

$cols = array_column($pdo->query('DESCRIBE addresses')->fetchAll(), 'Field');
if (!in_array('alt_phone', $cols, true)) {
    $pdo->exec('ALTER TABLE addresses ADD COLUMN alt_phone VARCHAR(20) NULL AFTER phone');
    echo "✓ addresses.alt_phone column added\n";
} else {
    echo "ℹ addresses.alt_phone already exists\n";
}

echo "\nMigration v8 complete.\n";
