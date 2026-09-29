<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'settings')) redirect('admin/');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    try {
        $pdo->prepare(
            'INSERT INTO coupons(code,type,value,min_order,max_discount,usage_limit,start_at,end_at,is_active)
             VALUES(?,?,?,?,?,?,?,?,1)'
        )->execute([
            strtoupper(trim($_POST['code'])),
            $_POST['type'],
            $_POST['value'],
            $_POST['min_order'],
            $_POST['max_discount'] ?: null,
            $_POST['usage_limit']  ?: null,
            $_POST['start_at']     ?: null,
            $_POST['end_at']       ?: null,
        ]);
        flash('success', 'Coupon created.');
        redirect('admin/coupons.php');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$coupons = $pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();

$adminTitle   = 'Coupons';
$adminSection = 'coupons';
$adminCrumbs  = [['label'=>'Coupons']];
include __DIR__.'/../includes/admin-header.php';
?>

<?php if ($err): ?>
  <div class="ap-alert"><?= e($err) ?></div>
<?php endif; ?>

<!-- ── Create form ────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Create coupon</h2>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Coupon code *</label>
          <input class="ap-input" name="code" placeholder="FRESH20" required
                 style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
          <span class="ap-hint">Customers will enter this at checkout.</span>
        </div>
        <div class="ap-field">
          <label class="ap-label">Discount type *</label>
          <select class="ap-select" name="type">
            <option value="percent">Percent (%) off</option>
            <option value="flat">Flat (₹) off</option>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">Discount value *</label>
          <input class="ap-input" type="number" step="0.01" min="0" name="value" required placeholder="e.g. 20">
        </div>
        <div class="ap-field">
          <label class="ap-label">Minimum order (₹)</label>
          <input class="ap-input" type="number" step="0.01" min="0" name="min_order" value="0">
        </div>
        <div class="ap-field">
          <label class="ap-label">Maximum discount (₹) <span style="font-weight:400;color:#9a9">optional</span></label>
          <input class="ap-input" type="number" step="0.01" min="0" name="max_discount" placeholder="Leave blank for no cap">
        </div>
        <div class="ap-field">
          <label class="ap-label">Usage limit <span style="font-weight:400;color:#9a9">optional</span></label>
          <input class="ap-input" type="number" min="1" name="usage_limit" placeholder="Leave blank for unlimited">
        </div>
        <div class="ap-field">
          <label class="ap-label">Start date</label>
          <input class="ap-input" type="date" name="start_at">
        </div>
        <div class="ap-field">
          <label class="ap-label">End date</label>
          <input class="ap-input" type="date" name="end_at">
        </div>
      </div>

      <div>
        <button class="ap-btn ap-btn-primary">🎟️ Create coupon</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Coupon list ─────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Active coupons</h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= count($coupons) ?> total</span>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Type</th>
            <th>Value</th>
            <th>Min order</th>
            <th>Max discount</th>
            <th>Limit</th>
            <th>Used</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($coupons as $c): ?>
          <tr>
            <td><b style="font-family:monospace;font-size:14px;"><?= e($c['code']) ?></b></td>
            <td>
              <span class="ap-badge <?= $c['type'] === 'percent' ? 'ap-badge-blue' : 'ap-badge-amber' ?>">
                <?= $c['type'] === 'percent' ? $c['value'].'% off' : '₹'.$c['value'].' off' ?>
              </span>
            </td>
            <td><?= $c['value'] ?></td>
            <td>₹<?= number_format((float)$c['min_order'], 2) ?></td>
            <td><?= $c['max_discount'] ? '₹'.number_format((float)$c['max_discount'],2) : '—' ?></td>
            <td><?= $c['usage_limit'] ?? '∞' ?></td>
            <td><?= $c['used_count'] ?></td>
            <td>
              <span class="ap-badge <?= $c['is_active'] ? 'ap-badge-green' : 'ap-badge-red' ?>">
                <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
