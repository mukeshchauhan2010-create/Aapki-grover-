<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    try {
        $vid = (int)$_POST['variant_id'];
        $new = max(0, (float)$_POST['stock']);

        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT stock, product_id FROM product_variants WHERE id=? FOR UPDATE');
        $s->execute([$vid]);
        $old = $s->fetch();
        if (!$old) throw new RuntimeException('Variant not found.');

        $delta = $new - (float)$old['stock'];
        $pdo->prepare('UPDATE product_variants SET stock=? WHERE id=?')->execute([$new, $vid]);
        $pdo->prepare(
            'UPDATE products p
             JOIN product_variants v ON v.product_id=p.id
             SET p.stock=(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id=p.id)
             WHERE v.id=?'
        )->execute([$vid]);
        $pdo->prepare(
            'INSERT INTO stock_movements(product_id,variant_id,change_qty,stock_after,reason,admin_user_id)
             VALUES(?,?,?,?,?,?)'
        )->execute([$old['product_id'], $vid, $delta, $new, 'manual_adjustment', user_id()]);
        $pdo->commit();
        flash('success', 'Inventory updated.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
    redirect('admin/inventory.php');
}

$rows = $pdo->query(
    'SELECT v.*, p.name, p.sku, p.low_stock_threshold
     FROM product_variants v
     JOIN products p ON p.id=v.product_id
     ORDER BY p.name, v.id'
)->fetchAll();

// Summary counts
$outCount  = count(array_filter($rows, fn($r) => (float)$r['stock'] <= 0));
$lowCount  = count(array_filter($rows, fn($r) => (float)$r['stock'] > 0 && (float)$r['stock'] <= (float)($r['low_stock_threshold'] ?? 5)));

$adminTitle   = 'Inventory';
$adminSection = 'inventory';
$adminCrumbs  = [['label'=>'Inventory']];
include __DIR__.'/../includes/admin-header.php';
?>

<?php if ($err): ?>
  <div class="ap-alert"><?= e($err) ?></div>
<?php endif; ?>

<!-- Summary pills -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
  <div class="ap-stat" style="flex:0 0 auto;padding:14px 22px;gap:4px;">
    <div class="ap-stat-label">Total variants</div>
    <div class="ap-stat-value" style="font-size:22px;"><?= count($rows) ?></div>
  </div>
  <div class="ap-stat" style="flex:0 0 auto;padding:14px 22px;gap:4px;border-color:#ffc6c2;">
    <div class="ap-stat-label" style="color:#c0392b;">Out of stock</div>
    <div class="ap-stat-value" style="font-size:22px;color:#c0392b;"><?= $outCount ?></div>
  </div>
  <div class="ap-stat" style="flex:0 0 auto;padding:14px 22px;gap:4px;border-color:#ffe0a0;">
    <div class="ap-stat-label" style="color:#9a6400;">Low stock</div>
    <div class="ap-stat-value" style="font-size:22px;color:#9a6400;"><?= $lowCount ?></div>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Stock levels</h2>
    <a class="ap-btn ap-btn-sm" href="<?= url('admin/products.php') ?>">Edit products</a>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Variant</th>
            <th>Current stock</th>
            <th>Adjust stock</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $isOut = (float)$r['stock'] <= 0;
            $isLow = !$isOut && (float)$r['stock'] <= (float)($r['low_stock_threshold'] ?? 5);
          ?>
          <tr>
            <td><b><?= e($r['name']) ?></b></td>
            <td><small style="font-family:monospace;"><?= e($r['sku']) ?></small></td>
            <td><?= e($r['label']) ?></td>
            <td>
              <?php if ($isOut): ?>
                <span class="ap-badge ap-badge-red">Out of stock</span>
              <?php elseif ($isLow): ?>
                <span class="ap-badge ap-badge-amber"><?= $r['stock'] ?> (low)</span>
              <?php else: ?>
                <span style="font-weight:700;color:#2a7d1e;"><?= $r['stock'] ?></span>
              <?php endif; ?>
            </td>
            <td>
              <form class="ap-inline" method="post">
                <input type="hidden" name="csrf"       value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="variant_id" value="<?= $r['id'] ?>">
                <input class="ap-input" name="stock" type="number" step="0.001" min="0"
                       value="<?= $r['stock'] ?>" style="width:110px;">
                <button class="ap-btn ap-btn-sm ap-btn-primary">Update</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
