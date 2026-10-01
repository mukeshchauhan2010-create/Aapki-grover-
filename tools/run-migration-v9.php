<?php
/**
 * Migration v9 — Money Wallet (₹)
 *  - users.wallet_balance : cached current wallet balance in rupees.
 *  - wallet_transactions  : append-only ledger (source of truth) for every
 *    credit/debit, with a running balance snapshot.
 *
 * Points system is untouched (points remain separate loyalty rewards).
 * Safe to run multiple times.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();

/* ── users.wallet_balance ─────────────────────────────────── */
$userCols = array_column($pdo->query('DESCRIBE users')->fetchAll(), 'Field');
if (!in_array('wallet_balance', $userCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER points');
    echo "✓ users.wallet_balance column added\n";
} else {
    echo "ℹ users.wallet_balance already exists\n";
}

/* ── wallet_transactions ledger ───────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
    id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT(20) UNSIGNED NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    type          ENUM('credit','debit') NOT NULL,
    reason        VARCHAR(255) NOT NULL,
    order_id      BIGINT(20) UNSIGNED DEFAULT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_wt_user (user_id),
    KEY idx_wt_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ wallet_transactions table ready\n";

echo "\nMigration v9 complete.\n";
