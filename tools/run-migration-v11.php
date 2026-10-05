<?php
/**
 * Migration v11 — account deletion support
 *  - users.remarks : free-text reason captured when a user deletes their account.
 *
 * Deletion is a soft-delete: we set users.is_active=0 and store the reason here.
 * Orders and payment records are intentionally left untouched.
 *
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

$cols = array_column($pdo->query('DESCRIBE users')->fetchAll(), 'Field');
if (!in_array('remarks', $cols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN remarks TEXT NULL AFTER is_active');
    echo "✓ users.remarks column added\n";
} else {
    echo "ℹ users.remarks already exists\n";
}

echo "\nMigration v11 complete.\n";
