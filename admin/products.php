<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/image.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

// Archive (soft-delete) handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([(int)$_POST['id']]);
    flash('success', 'Product archived.');
    redirect('admin/products.php'.(isset($_POST['qs']) && $_POST['qs'] ? '?'.$_POST['qs'] : ''));
}

// ── Filters ──────────────────────────────────────────────────
$fName = trim($_GET['q'] ?? '');
$fCat  = (int)($_GET['cat'] ?? 0);

$where = ['p.is_active=1']; $params = [];   // active products only
if ($fName !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = '%'.$fName.'%'; $params[] = '%'.$fName.'%'; }
if ($fCat > 0)     { $where[] = 'p.category_id = ?'; $params[] = $fCat; }
$whereSql = ' WHERE '.implode(' AND ', $where);
$hasFilter = ($fName !== '' || $fCat > 0);

// Count archived for the toolbar link
$archivedCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active=0')->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT p.*,c.name category_name FROM products p
     JOIN categories c ON c.id=p.category_id'.$whereSql.' ORDER BY p.id DESC'
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$cats = $pdo->query('SELECT * FROM categories ORDER BY sort_order,id')->fetchAll();
$qs   = http_build_query(array_filter(['q'=>$fName,'cat'=>$fCat?:''], fn($v)=>$v!==''));

$adminTitle   = 'Products';
$adminSection = 'products';
$adminCrumbs  = [['label'=>'Products']];
include __DIR__.'/../includes/admin-header.php';
?>

<!-- ── Toolbar: Add Product + filters ─────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Product list</h2>
    <div style="display:flex;gap:8px;">
      <a class="ap-btn ap-btn-sm" href="<?= url('admin/products-archived.php') ?>">🗄️ Archived<?= $archivedCount ? ' ('.$archivedCount.')' : '' ?></a>
      <a class="ap-btn ap-btn-primary ap-btn-sm" href="<?= url('admin/product-add.php') ?>">+ Add Product</a>
    </div>
  </div>
  <div class="ap-card-body">
    <form method="get" class="ap-form">
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Search by name / SKU</label>
          <input class="ap-input" name="q" value="<?= e($fName) ?>" placeholder="e.g. Potato or AG-VEG-001">
        </div>
        <div class="ap-field">
          <label class="ap-label">Category</label>
          <select class="ap-select" name="cat">
            <option value="">All categories</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $fCat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="ap-btn ap-btn-primary">🔍 Search</button>
        <?php if ($hasFilter): ?><a class="ap-btn" href="<?= url('admin/products.php') ?>">✕ Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Product list ───────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title"><?= $hasFilter ? 'Filtered products' : 'All products' ?></h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= count($products) ?> <?= $hasFilter ? 'match(es)' : 'total' ?></span>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$products): ?>
            <tr><td colspan="7"><span class="ap-hint" style="display:block;padding:14px">No products match your filters. <a href="<?= url('admin/product-add.php') ?>">Add one?</a></span></td></tr>
          <?php endif; ?>
          <?php foreach ($products as $p): ?>
          <tr>
            <td><img class="thumb" src="<?= e(product_image($p)) ?>" alt=""></td>
            <td><b><?= e($p['name']) ?></b><small><?= e($p['sku']) ?></small></td>
            <td><?= e($p['category_name']) ?></td>
            <td>₹<?= number_format((float)$p['price'], 2) ?></td>
            <td class="<?= (float)$p['stock'] <= 0 ? 'stock-out' : ((float)$p['stock'] <= (float)$p['low_stock_threshold'] ? 'stock-low' : '') ?>"><?= $p['stock'] ?></td>
            <td><span class="ap-badge <?= $p['is_active']?'ap-badge-green':'ap-badge-gray' ?>"><?= $p['is_active']?'Active':'Archived' ?></span></td>
            <td>
              <div style="display:flex;gap:8px;align-items:center;">
                <a class="ap-btn ap-btn-sm" href="<?= url('admin/product-add.php?edit='.$p['id']) ?>">Edit</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $p['id'] ?>">
                  <input type="hidden" name="qs"     value="<?= e($qs) ?>">
                  <button class="ap-btn ap-btn-sm ap-btn-danger" onclick="return confirm('Archive this product?')">Archive</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
