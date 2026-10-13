<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'orders')) redirect('admin/');

$id   = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'print') === 'pdf' ? 'pdf' : 'print';

$o = $pdo->prepare('SELECT o.*, u.name user_name, u.email, u.phone user_phone,
       a.name addr_name, a.phone addr_phone, a.alt_phone addr_alt,
       a.apartment_no, a.apartment_name, a.area, a.landmark, a.address_line, a.city, a.state, a.pincode
     FROM orders o LEFT JOIN users u ON u.id=o.user_id LEFT JOIN addresses a ON a.id=o.address_id
     WHERE o.id=? LIMIT 1');
$o->execute([$id]);
$order = $o->fetch();
if (!$order) { http_response_code(404); exit('Order not found'); }

$it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?');
$it->execute([$id]);
$items = $it->fetchAll();

$storeName = APP_NAME;
$storeAddr = setting($pdo, 'invoice_address', 'Aapki Grocery Pvt Ltd, Delhi NCR');
$storeContact = setting($pdo, 'invoice_contact', 'askme@aapkigrocery.com · +91 9212153207');
$mobile = $order['addr_phone'] ?: ($order['user_phone'] ?: '');
$addrLine = trim(implode(', ', array_filter([
    $order['addr_name'], $order['apartment_no'], $order['apartment_name'], $order['area'],
    $order['landmark'], $order['address_line'], ($order['city'] ? $order['city'].' - '.$order['pincode'] : ''), $order['state']
])));

/* ── PDF mode ───────────────────────────────────────────── */
if ($mode === 'pdf') {
    require_once __DIR__.'/../lib/minipdf.php';
    $pdf = new MiniPDF();
    $pdf->line($storeName, 18, true);
    $pdf->line($storeAddr, 9);
    $pdf->line($storeContact, 9);
    $pdf->gap(6); $pdf->hr();
    $pdf->line('TAX INVOICE', 13, true);
    $pdf->line('Order No: '.$order['order_no'], 10, true);
    $pdf->line('Date: '.date('d M Y, h:i A', strtotime($order['created_at'])), 10);
    $pdf->line('Payment: '.strtoupper($order['payment_method']).' ('.$order['payment_status'].')', 10);
    $pdf->gap(4);
    $pdf->line('Bill To:', 10, true);
    $pdf->line(($order['user_name'] ?: 'Customer').'  '.$mobile, 10);
    if ($addrLine) $pdf->line($addrLine, 9);
    $pdf->gap(6); $pdf->hr();
    $pdf->row('Item', 'Amount', 10, true);
    $pdf->hr();
    foreach ($items as $r) {
        $name = $r['product_name'].($r['variant_label'] ? ' ('.$r['variant_label'].')' : '').'  x'.(0+$r['qty']);
        $pdf->row($name, 'Rs. '.number_format((float)$r['line_total'], 2), 10);
    }
    $pdf->hr();
    $pdf->row('Subtotal', 'Rs. '.number_format((float)$order['subtotal'], 2));
    if ((float)$order['discount'] > 0) $pdf->row('Discount', '- Rs. '.number_format((float)$order['discount'], 2));
    $pdf->row('Handling', 'Rs. '.number_format((float)$order['handling_charge'], 2));
    $pdf->row('Delivery', 'Rs. '.number_format((float)$order['delivery_fee'], 2));
    $pdf->row('Total', 'Rs. '.number_format((float)$order['total'], 2), 12, true);
    $pdf->gap(16);
    $pdf->line('Thank you for shopping with '.$storeName.'!', 10);
    $pdf->output('invoice-'.$order['order_no'].'.pdf', true);
    exit;
}

/* ── Print (HTML) mode ──────────────────────────────────── */
?><!doctype html><html><head><meta charset="utf-8"><title>Invoice <?=e($order['order_no'])?></title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#1c261e;margin:0;padding:28px;max-width:760px}
.inv-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid #12652f;padding-bottom:14px}
.inv-store h1{margin:0;color:#12652f;font-size:22px}.inv-store p{margin:3px 0;font-size:12px;color:#555}
.inv-meta{text-align:right;font-size:12px}.inv-meta b{font-size:14px}
h2{font-size:14px;color:#12652f;margin:18px 0 6px}
table{width:100%;border-collapse:collapse;margin-top:8px;font-size:13px}
th,td{padding:8px 10px;border-bottom:1px solid #e2eadc;text-align:left}
th:last-child,td:last-child,th:nth-child(3),td:nth-child(3){text-align:right}
.tot{display:flex;justify-content:flex-end}.tot table{width:300px}
.tot td{border:0;padding:4px 10px}.tot .grand td{border-top:2px solid #12652f;font-weight:800;font-size:15px}
.inv-actions{margin:18px 0}
.inv-actions button{background:#12652f;color:#fff;border:0;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;font-size:14px}
@media print{.inv-actions{display:none}body{padding:0}}
</style></head>
<body onload="if(!location.hash)window.print()">
  <div class="inv-head">
    <div class="inv-store"><h1><?=e($storeName)?></h1><p><?=e($storeAddr)?></p><p><?=e($storeContact)?></p></div>
    <div class="inv-meta"><b>TAX INVOICE</b><br>Order: <b><?=e($order['order_no'])?></b><br><?=e(date('d M Y, h:i A', strtotime($order['created_at'])))?><br>Payment: <?=e(strtoupper($order['payment_method']))?> (<?=e($order['payment_status'])?>)</div>
  </div>

  <h2>Bill To</h2>
  <div style="font-size:13px"><b><?=e($order['user_name'] ?: 'Customer')?></b> · 📞 <?=e($mobile ?: '—')?><?php if($order['addr_alt']): ?> · Alt: <?=e($order['addr_alt'])?><?php endif; ?><?php if($addrLine): ?><br><?=e($addrLine)?><?php endif; ?><br>Delivery slot: <?=e($order['delivery_slot'] ?: 'Any slot')?></div>

  <table>
    <thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
    <tbody>
      <?php foreach($items as $r): ?>
      <tr>
        <td><?=e($r['product_name'])?><?= $r['variant_label'] ? ' · '.e($r['variant_label']) : '' ?></td>
        <td><?=e((string)(0+$r['qty']))?></td>
        <td>₹<?=number_format((float)$r['unit_price'],2)?></td>
        <td>₹<?=number_format((float)$r['line_total'],2)?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tot"><table>
    <tr><td>Subtotal</td><td>₹<?=number_format((float)$order['subtotal'],2)?></td></tr>
    <?php if((float)$order['discount']>0): ?><tr><td>Discount</td><td>- ₹<?=number_format((float)$order['discount'],2)?></td></tr><?php endif; ?>
    <tr><td>Handling</td><td>₹<?=number_format((float)$order['handling_charge'],2)?></td></tr>
    <tr><td>Delivery</td><td>₹<?=number_format((float)$order['delivery_fee'],2)?></td></tr>
    <tr class="grand"><td>Total</td><td>₹<?=number_format((float)$order['total'],2)?></td></tr>
  </table></div>

  <p style="margin-top:20px;font-size:12px;color:#555">Thank you for shopping with <?=e($storeName)?>!</p>
  <div class="inv-actions"><button onclick="window.print()">🖨 Print</button></div>
</body></html>
