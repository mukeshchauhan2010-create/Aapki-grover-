<?php
declare(strict_types=1);

/**
 * Razorpay helpers.
 *
 * Keys are read from the config constants first (set in the git-ignored
 * config/config.local.php) and fall back to the admin Settings table. This
 * keeps secrets out of git/DB while still allowing admin-panel configuration.
 */

function razorpay_key_id(PDO $pdo): string {
    return (defined('RAZORPAY_KEY_ID') && RAZORPAY_KEY_ID !== '') ? RAZORPAY_KEY_ID : setting($pdo,'razorpay_key_id');
}
function razorpay_key_secret(PDO $pdo): string {
    return (defined('RAZORPAY_KEY_SECRET') && RAZORPAY_KEY_SECRET !== '') ? RAZORPAY_KEY_SECRET : setting($pdo,'razorpay_key_secret');
}
function razorpay_webhook_secret(PDO $pdo): string {
    return (defined('RAZORPAY_WEBHOOK_SECRET') && RAZORPAY_WEBHOOK_SECRET !== '') ? RAZORPAY_WEBHOOK_SECRET : setting($pdo,'razorpay_webhook_secret');
}

function razorpay_enabled(PDO $pdo): bool {
    return setting_bool($pdo,'razorpay_enabled',false)
        && razorpay_key_id($pdo) !== ''
        && razorpay_key_secret($pdo) !== '';
}

function razorpay_create_order(PDO $pdo, float $amount, string $receipt): array {
    if(!razorpay_enabled($pdo)) throw new RuntimeException('Razorpay is not configured.');
    $payload = json_encode(['amount'=>(int)round($amount*100),'currency'=>'INR','receipt'=>$receipt,'payment_capture'=>1], JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => razorpay_key_id($pdo).':'.razorpay_key_secret($pdo),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if($code < 200 || $code >= 300 || empty($data['id'])) {
        throw new RuntimeException('Payment gateway order creation failed.');
    }
    return $data;
}

function razorpay_verify_payment(string $orderId, string $paymentId, string $signature, string $secret): bool {
    return hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $secret), $signature);
}

function razorpay_verify_webhook(string $raw, string $signature, string $secret): bool {
    return hash_equals(hash_hmac('sha256', $raw, $secret), $signature);
}
