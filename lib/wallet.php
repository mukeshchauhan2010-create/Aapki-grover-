<?php
declare(strict_types=1);
/**
 * Money wallet helpers (₹). The wallet_transactions table is the ledger
 * (source of truth); users.wallet_balance is a cached running total.
 *
 * Every mutation writes a ledger row AND updates users.wallet_balance in the
 * same DB transaction, so they never drift apart.
 */

/** Current wallet balance in rupees. */
function wallet_balance(PDO $pdo, int $userId): float {
    $s = $pdo->prepare('SELECT wallet_balance FROM users WHERE id=?');
    $s->execute([$userId]);
    return (float)($s->fetchColumn() ?: 0);
}

/**
 * Apply a wallet change. $amount is always POSITIVE; $type decides direction.
 * Returns the new balance. Throws RuntimeException on insufficient funds (debit).
 *
 * Pass $externalTx=true when the caller already opened a transaction
 * (e.g. during checkout) so we don't nest begin/commit.
 */
function wallet_apply(PDO $pdo, int $userId, float $amount, string $type, string $reason, ?int $orderId = null, bool $externalTx = false): float {
    if ($amount <= 0) throw new InvalidArgumentException('Wallet amount must be positive.');
    if (!in_array($type, ['credit','debit'], true)) throw new InvalidArgumentException('Invalid wallet type.');

    $own = !$externalTx && !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        // Lock the row to avoid race conditions on concurrent updates.
        $cur = $pdo->prepare('SELECT wallet_balance FROM users WHERE id=? FOR UPDATE');
        $cur->execute([$userId]);
        $balance = (float)($cur->fetchColumn() ?: 0);

        $delta = $type === 'credit' ? $amount : -$amount;
        if ($type === 'debit' && $amount > $balance + 0.0001) {
            throw new RuntimeException('Insufficient wallet balance.');
        }
        $newBalance = round($balance + $delta, 2);

        $pdo->prepare('UPDATE users SET wallet_balance=? WHERE id=?')->execute([$newBalance, $userId]);
        $pdo->prepare(
            'INSERT INTO wallet_transactions(user_id,amount,type,reason,order_id,balance_after) VALUES(?,?,?,?,?,?)'
        )->execute([$userId, round($amount,2), $type, $reason, $orderId, $newBalance]);

        if ($own) $pdo->commit();
        return $newBalance;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Convenience wrappers. */
function wallet_credit(PDO $pdo, int $userId, float $amount, string $reason, ?int $orderId = null, bool $externalTx = false): float {
    return wallet_apply($pdo, $userId, $amount, 'credit', $reason, $orderId, $externalTx);
}
function wallet_debit(PDO $pdo, int $userId, float $amount, string $reason, ?int $orderId = null, bool $externalTx = false): float {
    return wallet_apply($pdo, $userId, $amount, 'debit', $reason, $orderId, $externalTx);
}

/** Recent ledger rows for a user. */
function wallet_transactions(PDO $pdo, int $userId, int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    $s = $pdo->prepare('SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT '.$limit);
    $s->execute([$userId]);
    return $s->fetchAll();
}
