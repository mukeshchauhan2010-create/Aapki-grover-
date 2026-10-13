<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'orders')) redirect('admin/');

$id = (int)($_GET['id'] ?? 0);
$o = $pdo->prepare('SELECT o.*, u.name user_name, u.phone user_phone,
       a.name addr_name, a.phone addr_phone, a.alt_phone addr_alt,
       a.apartment_no, a.apartment_name, a.area, a.landmark, a.address_line, a.city, a.state, a.pincode
     FROM orders o LEFT JOIN users u ON u.id=o.user_id LEFT JOIN addresses a ON a.id=o.address_id
     WHERE o.id=? LIMIT 1');
$o->execute([$id]);
$order = $o->fetch();
if (!$order) { http_response_code(404); exit('Order not found'); }

$mobile = $order['addr_phone'] ?: ($order['user_phone'] ?: '');
$isCOD  = strtolower($order['payment_method']) === 'cod' && $order['payment_status'] !== 'paid';

// Active UPI QR (for COD labels)
$qr = null;
try {
    $qrow = $pdo->query('SELECT * FROM payment_qr WHERE is_active=1 ORDER BY id DESC LIMIT 1')->fetch();
    if ($qrow) $qr = $qrow;
} catch (Throwable $e) { /* run migration v13 */ }

$addrTop = trim(implode(', ', array_filter([$order['apartment_no'], $order['apartment_name'], $order['area']])));
$addrMid = trim(implode(', ', array_filter([$order['landmark'], $order['address_line']])));
$addrBot = trim(($order['city'] ? $order['city'].' - '.$order['pincode'] : '').($order['state'] ? ', '.$order['state'] : ''));
?><!doctype html><html><head><meta charset="utf-8"><title>Label <?=e($order['order_no'])?></title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;padding:16px;color:#111}
.label{width:384px;border:2px solid #111;border-radius:10px;padding:16px;margin:auto}
.lbl-head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px dashed #111;padding-bottom:8px}
.lbl-head b{font-size:18px;color:#12652f}
.lbl-order{font-size:13px;font-weight:800}
.lbl-sec{margin-top:10px}
.lbl-sec h4{margin:0 0 3px;font-size:11px;letter-spacing:.08em;color:#666}
.lbl-to{font-size:15px;font-weight:800}
.lbl-addr{font-size:13px;line-height:1.5}
.lbl-phone{font-size:14px;font-weight:800;margin-top:4px}
.lbl-pay{margin-top:10px;padding:8px 10px;border-radius:8px;font-weight:800;text-align:center}
.pay-cod{background:#fff3dc;color:#8a5a00;border:1px solid #f0c870}
.pay-paid{background:#e6f6e6;color:#1a7a34;border:1px solid #9ad6a0}
.lbl-qr{margin-top:12px;text-align:center;border-top:2px dashed #111;padding-top:12px}
.lbl-qr img{width:170px;height:170px;object-fit:contain}
.lbl-qr .amt{font-size:18px;font-weight:900;margin-top:6px}
.lbl-actions{text-align:center;margin:16px}
.lbl-actions button{background:#12652f;color:#fff;border:0;padding:10px 22px;border-radius:8px;font-weight:700;cursor:pointer}
@media print{.lbl-actions{display:none}body{padding:0}.label{border-width:1px}}
</style></head>
<body onload="window.print()">
  <div class="label">
    <div class="lbl-head"><b><?=e(APP_NAME)?></b><span class="lbl-order">#<?=e($order['order_no'])?></span></div>

    <div class="lbl-sec">
      <h4>DELIVER TO</h4>
      <div class="lbl-to"><?=e($order['addr_name'] ?: $order['user_name'] ?: 'Customer')?></div>
      <div class="lbl-addr">
        <?php if($addrTop): ?><?=e($addrTop)?><br><?php endif; ?>
        <?php if($addrMid): ?><?=e($addrMid)?><br><?php endif; ?>
        <?=e($addrBot)?>
      </div>
      <div class="lbl-phone">📞 <?=e($mobile ?: '—')?><?php if($order['addr_alt']): ?> · Alt: <?=e($order['addr_alt'])?><?php endif; ?></div>
    </div>

    <div class="lbl-pay <?= $isCOD ? 'pay-cod' : 'pay-paid' ?>">
      <?php if($isCOD): ?>COLLECT ON DELIVERY: ₹<?=number_format((float)$order['total'],2)?><?php else: ?>PREPAID / PAID — ₹<?=number_format((float)$order['total'],2)?><?php endif; ?>
    </div>

    <?php if($isCOD): ?>
      <div class="lbl-qr">
        <h4>SCAN TO PAY (UPI)</h4>
        <?php if($qr): ?>
          <img src="<?=e(asset_url($qr['image']))?>" alt="UPI QR">
          <div class="amt">₹<?=number_format((float)$order['total'],2)?></div>
        <?php else: ?>
          <div style="font-size:12px;color:#a33">No active UPI QR set. Upload one in Admin → Settings → Payment QR.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="lbl-actions"><button onclick="window.print()">🖨 Print Label</button></div>
</body></html>
