<?php
/**
 * Razorpay payment verification (client redirect from Checkout.js handler).
 * Verifies the signature server-side, marks the order paid, shows success.
 */
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/razorpay.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? null)) {
    flash('error','Invalid payment session.'); redirect('cart/');
}
if (!is_logged_in()) { redirect('login.php?return=account/orders.php'); }

$orderNo = trim($_POST['order_no'] ?? '');
$rzpOrder = trim($_POST['razorpay_order_id'] ?? '');
$rzpPay   = trim($_POST['razorpay_payment_id'] ?? '');
$rzpSig   = trim($_POST['razorpay_signature'] ?? '');

// Load the order and confirm it belongs to this user and matches the RZP order.
$s = $pdo->prepare('SELECT * FROM orders WHERE order_no=? AND user_id=? LIMIT 1');
$s->execute([$orderNo, user_id()]);
$order = $s->fetch();

if (!$order || $order['razorpay_order_id'] !== $rzpOrder) {
    flash('error','Order not found for verification.'); redirect('account/orders.php');
}

$ok = $rzpPay !== '' && $rzpSig !== '' &&
      razorpay_verify_payment($rzpOrder, $rzpPay, $rzpSig, razorpay_key_secret($pdo));

if (!$ok) {
    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")->execute([(int)$order['id']]);
    flash('error','Payment could not be verified. If money was deducted it will be auto-refunded.');
    redirect('account/orders.php');
}

// Mark paid (idempotent — safe if the webhook already set it).
$pdo->prepare("UPDATE orders SET payment_status='paid', razorpay_payment_id=?, payment_reference=? WHERE id=?")
    ->execute([$rzpPay, 'checkout', (int)$order['id']]);

$title = 'Payment successful | '.APP_NAME;
include __DIR__.'/../includes/header.php';
?>
<section class="container page">
  <div class="panel success-order" style="max-width:560px;margin:40px auto;text-align:center">
    <div class="success-icon">✓</div>
    <span class="eyebrow">PAYMENT SUCCESSFUL</span>
    <h1>Thank you! Payment received.</h1>
    <p>Your order <strong><?=e($orderNo)?></strong> is confirmed and paid.</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:14px;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?=e(url('account/orders.php'))?>">View my orders</a>
      <a class="btn" href="<?=e(url())?>">Continue shopping</a>
    </div>
  </div>
</section>
<script>try{localStorage.removeItem('aapki_cart_v2');}catch(e){}</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
