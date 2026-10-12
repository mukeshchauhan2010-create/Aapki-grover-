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
    'SELECT o.*, u.name user_name, u.email
     FROM orders o LEFT JOIN users u ON u.id=o.user_id'
    .$whereSql.
    ' ORDER BY o.id DESC LIMIT '.$perPage.' OFFSET '.$offset
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

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
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$orders): ?>
          <tr><td colspan="7"><span class="ap-hint" style="display:block;padding:14px">No orders match your filters.</span></td></tr>
          <?php endif; ?>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><b><?= e($o['order_no']) ?></b></td>
            <td>
              <?= e($o['user_name'] ?? 'Guest') ?>
              <small><?= e($o['email'] ?? '') ?></small>
            </td>
            <td><b>₹<?= number_format((float)$o['total'], 2) ?></b></td>
            <td>
              <span class="ap-badge <?= payment_badge($o['payment_status']) ?>">
                <?= e($o['payment_status']) ?>
              </span>
              <small><?= e($o['payment_method']) ?></small>
            </td>
            <td>
              <span class="ap-badge <?= order_badge($o['status']) ?>">
                <?= e(str_replace('_', ' ', $o['status'])) ?>
              </span>
            </td>
            <td>
              <form class="ap-inline" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id"   value="<?= $o['id'] ?>">
                <select class="ap-select" name="status" style="min-width:148px;">
                  <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt === $o['status'] ? 'selected' : '' ?>>
                      <?= ucwords(str_replace('_', ' ', $opt)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="ap-btn ap-btn-sm ap-btn-primary">Save</button>
              </form>
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

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
