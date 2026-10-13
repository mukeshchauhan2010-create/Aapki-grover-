<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'orders')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    if (isset($_POST['status'])) {
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')
            ->execute([$_POST['status'], (int)$_POST['id']]);
        flash('success', 'Order status updated.');
    }
    redirect('admin/orders.php');
}

$statusOptions  = ['placed','confirmed','packed','out_for_delivery','delivered','cancelled','returned'];
$paymentOptions = ['pending','paid','failed'];

// ── Filters ──────────────────────────────────────────────────
$fOrder   = trim($_GET['q'] ?? '');
$fStatus  = trim($_GET['status'] ?? '');
$fPayment = trim($_GET['payment'] ?? '');
$fCust    = trim($_GET['cust'] ?? '');
$fFrom    = trim($_GET['from'] ?? '');   // YYYY-MM-DD or YYYY-MM-DDTHH:MM
$fTo      = trim($_GET['to'] ?? '');

$where = []; $params = [];
if ($fOrder !== '')   { $where[] = 'o.order_no LIKE ?'; $params[] = '%'.$fOrder.'%'; }
if ($fStatus !== '' && in_array($fStatus, $statusOptions, true))   { $where[] = 'o.status = ?'; $params[] = $fStatus; }
if ($fPayment !== '' && in_array($fPayment, $paymentOptions, true)){ $where[] = 'o.payment_status = ?'; $params[] = $fPayment; }
if ($fCust !== '')    { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $params[] = '%'.$fCust.'%'; $params[] = '%'.$fCust.'%'; }
if ($fFrom !== '')    { $where[] = 'o.created_at >= ?'; $params[] = str_replace('T', ' ', $fFrom).(strlen($fFrom) <= 10 ? ' 00:00:00' : ':00'); }
if ($fTo !== '')      { $where[] = 'o.created_at <= ?'; $params[] = str_replace('T', ' ', $fTo).(strlen($fTo) <= 10 ? ' 23:59:59' : ':59'); }
$whereSql = $where ? (' WHERE '.implode(' AND ', $where)) : '';
$hasFilter = ($fOrder !== '' || $fStatus !== '' || $fPayment !== '' || $fCust !== '' || $fFrom !== '' || $fTo !== '');

// Query-string of active filters (to preserve across pagination / export links)
$qsParts = array_filter(['q'=>$fOrder,'status'=>$fStatus,'payment'=>$fPayment,'cust'=>$fCust,'from'=>$fFrom,'to'=>$fTo], fn($v)=>$v!=='');
$filterQS = http_build_query($qsParts);

// ── CSV export (respects filters; no pagination) ─────────────
if (($_GET['export'] ?? '') === 'csv') {
    $ex = $pdo->prepare(
        'SELECT o.order_no,o.created_at,u.name user_name,u.email,o.total,o.payment_method,o.payment_status,o.status,o.delivery_slot
         FROM orders o LEFT JOIN users u ON u.id=o.user_id'.$whereSql.' ORDER BY o.id DESC'
    );
    $ex->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-'.date('Ymd-His').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order No','Date','Customer','Email','Total','Payment Method','Payment Status','Status','Delivery Slot']);
    foreach ($ex as $r) {
        fputcsv($out, [$r['order_no'],$r['created_at'],$r['user_name']??'Guest',$r['email']??'',$r['total'],$r['payment_method'],$r['payment_status'],$r['status'],$r['delivery_slot']]);
    }
    fclose($out);
    exit;
}

// ── Pagination ───────────────────────────────────────────────
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));
$cnt = $pdo->prepare('SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id=o.user_id'.$whereSql);
$cnt->execute($params);
$totalOrders = (int)$cnt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalOrders / $perPage));
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    'SELECT o.*, u.name user_name, u.email, u.phone user_phone,
            a.name addr_name, a.phone addr_phone, a.alt_phone addr_alt,
            a.apartment_no, a.apartment_name, a.area, a.landmark, a.address_line, a.city, a.state, a.pincode
     FROM orders o
     LEFT JOIN users u ON u.id=o.user_id
     LEFT JOIN addresses a ON a.id=o.address_id'
    .$whereSql.
    ' ORDER BY o.id DESC LIMIT '.$perPage.' OFFSET '.$offset
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Preload items for the hovered order-id tooltip
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $its = $pdo->prepare('SELECT order_id, product_name, variant_label, qty, unit_price, line_total FROM order_items WHERE order_id IN('.$in.')');
    $its->execute($ids);
    foreach ($its->fetchAll() as $it) { $itemsByOrder[$it['order_id']][] = $it; }
}

$adminTitle   = 'Orders';
$adminSection = 'orders';
$adminCrumbs  = [['label'=>'Orders']];
include __DIR__.'/../includes/admin-header.php';

// Badge helper
function order_badge(string $s): string {
    switch ($s) {
        case 'delivered':                       return 'ap-badge-green';
        case 'placed': case 'confirmed': case 'packed': return 'ap-badge-blue';
        case 'out_for_delivery':                return 'ap-badge-amber';
        case 'cancelled': case 'returned':      return 'ap-badge-red';
        default:                                return 'ap-badge-gray';
    }
}
function payment_badge(string $s): string {
    switch ($s) {
        case 'paid':    return 'ap-badge-green';
        case 'pending': return 'ap-badge-amber';
        case 'failed':  return 'ap-badge-red';
        default:        return 'ap-badge-gray';
    }
}
?>

<!-- ── Search & filters ─────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Search &amp; filter</h2></div>
  <div class="ap-card-body">
    <form method="get" class="ap-form">
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Order ID</label>
          <input class="ap-input" name="q" value="<?= e($fOrder) ?>" placeholder="e.g. AG2610120001">
        </div>
        <div class="ap-field">
          <label class="ap-label">Customer (name / email)</label>
          <input class="ap-input" name="cust" value="<?= e($fCust) ?>" placeholder="name or email">
        </div>
        <div class="ap-field">
          <label class="ap-label">Status</label>
          <select class="ap-select" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $opt): ?>
              <option value="<?= $opt ?>" <?= $fStatus === $opt ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$opt)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">Payment status</label>
          <select class="ap-select" name="payment">
            <option value="">All payments</option>
            <?php foreach ($paymentOptions as $opt): ?>
              <option value="<?= $opt ?>" <?= $fPayment === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">From (date &amp; time)</label>
          <input class="ap-input" type="datetime-local" name="from" value="<?= e($fFrom) ?>">
        </div>
        <div class="ap-field">
          <label class="ap-label">To (date &amp; time)</label>
          <input class="ap-input" type="datetime-local" name="to" value="<?= e($fTo) ?>">
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="ap-btn ap-btn-primary">🔍 Search</button>
        <?php if ($hasFilter): ?><a class="ap-btn" href="<?= url('admin/orders.php') ?>">✕ Clear</a><?php endif; ?>
        <a class="ap-btn" href="<?= url('admin/orders.php?export=csv'.($filterQS ? '&'.$filterQS : '')) ?>">⬇ Export CSV</a>
      </div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title"><?= $hasFilter ? 'Filtered orders' : 'All orders' ?></h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= number_format($totalOrders) ?> total · page <?= $page ?> of <?= $totalPages ?></span>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Update status</th>
            <th>Documents</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$orders): ?>
          <tr><td colspan="8"><span class="ap-hint" style="display:block;padding:14px">No orders match your filters.</span></td></tr>
          <?php endif; ?>
          <?php foreach ($orders as $o):
            $mobile = $o['addr_phone'] ?: ($o['user_phone'] ?: '');
            $items  = $itemsByOrder[$o['id']] ?? [];
            $addrLine = trim(implode(', ', array_filter([
                $o['apartment_no'], $o['apartment_name'], $o['area'], $o['landmark'],
                $o['address_line'], ($o['city']? $o['city'].' - '.$o['pincode'] : ''), $o['state']
            ])));
          ?>
          <tr>
            <!-- Order # with items tooltip -->
            <td>
              <span class="ap-hover">
                <b><?= e($o['order_no']) ?></b>
                <div class="ap-tip ap-tip-wide">
                  <div class="ap-tip-title">Order items</div>
                  <table class="ap-tip-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                      <tr>
                        <td><?= e($it['product_name']) ?><?= $it['variant_label'] ? ' · '.e($it['variant_label']) : '' ?></td>
                        <td><?= e((string)(0+$it['qty'])) ?></td>
                        <td>₹<?= number_format((float)$it['unit_price'],2) ?></td>
                        <td>₹<?= number_format((float)$it['line_total'],2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(!$items): ?><tr><td colspan="4">No items</td></tr><?php endif; ?>
                    </tbody>
                  </table>
                  <div class="ap-tip-row"><span>Subtotal</span><b>₹<?= number_format((float)$o['subtotal'],2) ?></b></div>
                  <div class="ap-tip-row"><span>Delivery</span><b>₹<?= number_format((float)$o['delivery_fee'],2) ?></b></div>
                  <div class="ap-tip-row"><span>Total</span><b>₹<?= number_format((float)$o['total'],2) ?></b></div>
                </div>
              </span>
            </td>
            <!-- Customer with user-info tooltip; show mobile not email -->
            <td>
              <span class="ap-hover">
                <?= e($o['user_name'] ?? 'Guest') ?>
                <small>📞 <?= e($mobile ?: '—') ?></small>
                <div class="ap-tip">
                  <div class="ap-tip-title">Customer</div>
                  <div class="ap-tip-line"><b><?= e($o['user_name'] ?? 'Guest') ?></b></div>
                  <div class="ap-tip-line">📞 <?= e($mobile ?: '—') ?><?= $o['addr_alt'] ? ' · Alt: '.e($o['addr_alt']) : '' ?></div>
                  <?php if(!empty($o['email'])): ?><div class="ap-tip-line">✉️ <?= e($o['email']) ?></div><?php endif; ?>
                  <?php if($addrLine): ?><div class="ap-tip-line">📍 <?= e($addrLine) ?></div><?php endif; ?>
                  <div class="ap-tip-line">🕒 <?= e($o['delivery_slot'] ?: 'Any slot') ?></div>
                </div>
              </span>
            </td>
            <td><b>₹<?= number_format((float)$o['total'], 2) ?></b></td>
            <td>
              <span class="ap-badge <?= payment_badge($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span>
              <small><?= e(strtoupper($o['payment_method'])) ?></small>
            </td>
            <td>
              <span class="ap-badge <?= order_badge($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span>
            </td>
            <!-- Auto-save status on change (with confirm) -->
            <td>
              <form class="ap-inline ap-status-form" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id"   value="<?= $o['id'] ?>">
                <select class="ap-select ap-status-select" name="status" data-current="<?= e($o['status']) ?>" style="min-width:148px;">
                  <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt === $o['status'] ? 'selected' : '' ?>>
                      <?= ucwords(str_replace('_', ' ', $opt)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <noscript><button class="ap-btn ap-btn-sm ap-btn-primary">Save</button></noscript>
              </form>
            </td>
            <!-- Documents -->
            <td>
              <div class="ap-doc-links">
                <a href="<?= url('admin/invoice.php?id='.$o['id'].'&mode=print') ?>" target="_blank">Print</a>
                <a href="<?= url('admin/invoice.php?id='.$o['id'].'&mode=pdf') ?>">Download</a>
                <a href="<?= url('admin/label.php?id='.$o['id']) ?>" target="_blank">Label</a>
              </div>
            </td>
            <td><small><?= e($o['created_at']) ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="ap-card-body" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-top:1px solid var(--line,#e2eadc)">
    <span class="ap-hint">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalOrders) ?> of <?= number_format($totalOrders) ?></span>
    <div style="display:flex;gap:8px;">
      <?php $pfx = url('admin/orders.php').'?'.($filterQS ? $filterQS.'&' : ''); ?>
      <?php if ($page > 1): ?><a class="ap-btn ap-btn-sm" href="<?= e($pfx.'page='.($page-1)) ?>">← Prev</a><?php endif; ?>
      <span class="ap-btn ap-btn-sm" style="pointer-events:none;background:#f0f5ee"><?= $page ?> / <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?><a class="ap-btn ap-btn-sm" href="<?= e($pfx.'page='.($page+1)) ?>">Next →</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
/* Hover tooltips */
.ap-hover{position:relative;cursor:help}
.ap-hover small{display:block;color:#7a8a7b;font-size:11px;margin-top:2px}
.ap-tip{display:none;position:absolute;left:0;top:100%;z-index:60;background:#fff;border:1px solid #dce5da;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.16);padding:12px 14px;min-width:230px;margin-top:6px;font-weight:400;text-align:left}
.ap-tip-wide{min-width:340px}
.ap-hover:hover .ap-tip{display:block}
.ap-tip-title{font-weight:800;color:#12652f;font-size:12px;letter-spacing:.04em;margin-bottom:8px}
.ap-tip-line{font-size:12.5px;margin:3px 0;color:#2b382e}
.ap-tip-table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px}
.ap-tip-table th,.ap-tip-table td{text-align:left;padding:4px 6px;border-bottom:1px solid #eef3ec}
.ap-tip-table th:nth-child(n+2),.ap-tip-table td:nth-child(n+2){text-align:right}
.ap-tip-row{display:flex;justify-content:space-between;font-size:12px;padding:2px 0}
.ap-tip-row:last-child{border-top:1px solid #eef3ec;margin-top:4px;padding-top:5px;font-weight:700}
.ap-doc-links{display:flex;gap:8px;flex-wrap:wrap}
.ap-doc-links a{font-size:12px;font-weight:700;color:#12652f;text-decoration:underline}
.ap-status-saving{opacity:.6}
</style>
<script>
(function(){
  document.querySelectorAll('.ap-status-select').forEach(function(sel){
    sel.addEventListener('change',function(){
      var cur=sel.dataset.current, next=sel.value;
      if(next===cur) return;
      var label=sel.options[sel.selectedIndex].text;
      if(confirm('Change order status to "'+label+'"?')){
        sel.classList.add('ap-status-saving'); sel.disabled=true;
        sel.form.submit();
      } else {
        sel.value=cur; // revert
      }
    });
  });
})();
</script>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
