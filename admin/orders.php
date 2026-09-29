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

$orders = $pdo->query(
    'SELECT o.*, u.name user_name, u.email
     FROM orders o LEFT JOIN users u ON u.id=o.user_id
     ORDER BY o.id DESC LIMIT 200'
)->fetchAll();

$statusOptions = ['placed','confirmed','packed','out_for_delivery','delivered','cancelled','returned'];

$adminTitle   = 'Orders';
$adminSection = 'orders';
$adminCrumbs  = [['label'=>'Orders']];
include __DIR__.'/../includes/admin-header.php';

// Badge helper
function order_badge(string $s): string {
    return match($s) {
        'delivered'        => 'ap-badge-green',
        'placed'           => 'ap-badge-blue',
        'confirmed','packed' => 'ap-badge-blue',
        'out_for_delivery' => 'ap-badge-amber',
        'cancelled','returned' => 'ap-badge-red',
        default            => 'ap-badge-gray',
    };
}
function payment_badge(string $s): string {
    return match($s) {
        'paid'    => 'ap-badge-green',
        'pending' => 'ap-badge-amber',
        'failed'  => 'ap-badge-red',
        default   => 'ap-badge-gray',
    };
}
?>

<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">All orders</h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= count($orders) ?> shown (latest 200)</span>
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
</div>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
