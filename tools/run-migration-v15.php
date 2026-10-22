<?php
/**
 * Migration v15 — Fix blank created_at / updated_at timestamps.
 *
 * Root cause: on older MySQL (< 5.6.5) a table may have only ONE column with a
 * DEFAULT CURRENT_TIMESTAMP. When a table defines both created_at AND updated_at
 * that way, the create can fail silently / the default never fires, leaving the
 * timestamps blank (0000-00-00 or NULL) on insert.
 *
 * This migration:
 *   1) Re-declares created_at / updated_at on users, orders, products with the
 *      correct modern definition (works on MySQL 5.6.5+ and all MariaDB).
 *   2) Backfills any existing rows whose created_at is NULL / zero, using a
 *      sensible timestamp (updated_at if present, otherwise NOW()).
 *
 * The application INSERTs now also set NOW() explicitly, so new rows are correct
 * regardless of server version — this migration repairs the schema + old data.
 *
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

function col_exists(PDO $pdo, string $table, string $col): bool {
    try {
        $rows = $pdo->query('DESCRIBE `'.$table.'`')->fetchAll();
        foreach ($rows as $r) { if (strcasecmp($r['Field'], $col) === 0) return true; }
    } catch (Throwable $e) {}
    return false;
}

$tables = ['users', 'orders', 'products'];

foreach ($tables as $t) {
    // 1) Repair column definitions (ignore errors on servers that already match).
    if (col_exists($pdo, $t, 'created_at')) {
        try {
            $pdo->exec("ALTER TABLE `$t` MODIFY `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            echo "✓ $t.created_at default repaired\n";
        } catch (Throwable $e) { echo "ℹ $t.created_at: ".$e->getMessage()."\n"; }
    }
    if (col_exists($pdo, $t, 'updated_at')) {
        try {
            $pdo->exec("ALTER TABLE `$t` MODIFY `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "✓ $t.updated_at default repaired\n";
        } catch (Throwable $e) { echo "ℹ $t.updated_at: ".$e->getMessage()."\n"; }
    }

    // 2) Backfill blank/zero created_at on existing rows.
    if (col_exists($pdo, $t, 'created_at')) {
        try {
            $hasUpdated = col_exists($pdo, $t, 'updated_at');
            $fallback = $hasUpdated
                ? "COALESCE(NULLIF(`updated_at`,'0000-00-00 00:00:00'), NOW())"
                : "NOW()";
            $sql = "UPDATE `$t`
                    SET `created_at` = $fallback
                    WHERE `created_at` IS NULL
                       OR `created_at` = '0000-00-00 00:00:00'";
            $n = $pdo->exec($sql);
            echo "✓ $t: backfilled $n row(s) with blank created_at\n";
        } catch (Throwable $e) { echo "ℹ $t backfill: ".$e->getMessage()."\n"; }
    }

    // Backfill blank updated_at = created_at where possible.
    if (col_exists($pdo, $t, 'updated_at') && col_exists($pdo, $t, 'created_at')) {
        try {
            $n = $pdo->exec("UPDATE `$t`
                             SET `updated_at` = `created_at`
                             WHERE `updated_at` IS NULL
                                OR `updated_at` = '0000-00-00 00:00:00'");
            echo "✓ $t: backfilled $n row(s) with blank updated_at\n";
        } catch (Throwable $e) { echo "ℹ $t updated_at backfill: ".$e->getMessage()."\n"; }
    }
}

echo "\nMigration v15 complete.\n";
