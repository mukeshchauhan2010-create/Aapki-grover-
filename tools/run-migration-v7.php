<?php
/**
 * Migration v7
 *  - categories.listing_description : long text shown above the footer on
 *    category listing pages (separate from the short SEO meta_description).
 *  - users.avatar : profile photo URL (e.g. from Google login) shown in the header.
 *
 * Safe to run multiple times (checks columns before adding).
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

/* ── categories.listing_description ───────────────────────── */
$catCols = array_column($pdo->query('DESCRIBE categories')->fetchAll(), 'Field');
if (!in_array('listing_description', $catCols, true)) {
    $pdo->exec('ALTER TABLE categories ADD COLUMN listing_description TEXT NULL AFTER meta_keywords');
    echo "✓ categories.listing_description column added\n";
} else {
    echo "ℹ categories.listing_description already exists\n";
}

/* ── users.avatar ─────────────────────────────────────────── */
$userCols = array_column($pdo->query('DESCRIBE users')->fetchAll(), 'Field');
if (!in_array('avatar', $userCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(500) NULL AFTER phone');
    echo "✓ users.avatar column added\n";
} else {
    echo "ℹ users.avatar already exists\n";
}

echo "\nMigration v7 complete.\n";
