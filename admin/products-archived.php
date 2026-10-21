<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/image.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'restore' && $id) {
        $pdo->prepare('UPDATE products SET is_active=1 WHERE id=?')->execute([$id]);
        flash('success', 'Product restored — now active on the store.');
    } elseif ($action === 'purge' && $id) {
        // Permanent delete (product + its variants/synonyms).
        $pdo->prepare('DELETE FROM product_variants WHERE product_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM product_synonyms WHERE product_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        flash('success', 'Product permanently deleted.');
    }
    redirect('admin/products-archived.php');
}

// Filters (same as the active list, scoped to archived)
$fName = trim($_GET['q'] ?? '');
$fCat  = (int)($_GET['cat'] ?? 0);
$where = ['p.is_active=0']; $params = [];
if ($fName !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = '%'.$fName.'%'; $params[] = '%'.$fName.'%'; }
if ($fCat > 0)     { $where[] = 'p.category_id = ?'; $params[] = $fCat; }
$whereSql = ' WHERE '.implode(' AND ', $where);
$hasFilter = ($fName !== '' || $fCat > 0);

$stmt = $pdo->prepare('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id'.$whereSql.' ORDER BY p.id DESC');
$stmt->execute($params);
$products = $stmt->fetchAll();
$cats = $pdo->query('SELECT * FROM categories ORDER BY sort_order,id')->fetchAll();

$adminTitle='Archived Products'; $adminSection='products'; $adminCrumbs=[['label'=>'Products','url'=>url('admin/products.php')],['label'=>'Archived']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Archived products</h2>
    <a class="ap-btn ap-btn-sm" href="<?= url('admin/products.php') ?>">← Back to active list</a>
  </div>
  <div class="ap-card-body">
    <p class="ap-hint">Archived products are hidden from the store. <b>Restore</b> to make a product active again, or <b>Delete</b> to remove it permanently.</p>
    <form method="get" class="ap-form">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Search by name / SKU</label><input class="ap-input" name="q" value="<?= e($fName) ?>" placeholder="e.g. Potato"></div>
        <div class="ap-field"><label class="ap-label">Category</label>
          <select class="ap-select" name="cat"><option value="">All categories</option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $fCat===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="ap-btn ap-btn-primary">🔍 Search</button>
        <?php if ($hasFilter): ?><a class="ap-btn" href="<?= url('admin/products-archived.php') ?>">✕ Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title"><?= $hasFilter?'Filtered archived':'All archived' ?></h2><span style="font-size:13px;color:#7a8a7b;"><?= count($products) ?> total</span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap"><table class="ap-table">
      <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$products): ?><tr><td colspan="6"><span class="ap-hint" style="display:block;padding:14px">No archived products<?= $hasFilter?' match your filters':'' ?>.</span></td></tr><?php endif; ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><img class="thumb" src="<?= e(product_image($p)) ?>" alt=""></td>
          <td><b><?= e($p['name']) ?></b><small><?= e($p['sku']) ?></small></td>
          <td><?= e($p['category_name']) ?></td>
          <td>₹<?= number_format((float)$p['price'], 2) ?></td>
          <td><?= $p['stock'] ?></td>
          <td>
            <div style="display:flex;gap:8px;align-items:center;">
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="ap-btn ap-btn-sm ap-btn-primary">♻ Restore</button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this product? This cannot be undone.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="purge">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="ap-btn ap-btn-sm ap-btn-danger">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
