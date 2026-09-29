<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/image.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

$uploadDir = __DIR__.'/../uploads/products';
$publicDir = 'uploads/products';

$editId = (int)($_GET['edit'] ?? 0);
$edit   = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $s->execute([$editId]);
    $edit = $s->fetch();
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    try {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $pdo->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
            flash('success', 'Product archived.');
            redirect('admin/products.php');
        }

        if ($action === 'save') {
            $id    = (int)($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            $cat   = (int)$_POST['category_id'];
            $sku   = trim($_POST['sku'] ?? '');
            $slug  = slugify($name).'-'.substr(sha1($sku ?: $name), 0, 6);
            $image = $edit['image']     ?? null;
            $webp  = $edit['image_webp'] ?? null;

            if (!empty($_FILES['image']['name'])) {
                $im    = save_product_image($_FILES['image'], $uploadDir, $publicDir);
                $image = $im['source'];
                $webp  = $im['webp'];
            }

            $fields = [
                $cat, $name, $slug, $sku,
                trim($_POST['description']),
                trim($_POST['search_terms']),
                $image, $webp,
                trim($_POST['image_alt']),
                $_POST['mrp'], $_POST['price'], $_POST['stock'],
                $_POST['low_stock_threshold'],
                isset($_POST['featured']) ? 1 : 0,
                isset($_POST['active'])   ? 1 : 0,
                trim($_POST['meta_title']),
                trim($_POST['meta_description']),
                trim($_POST['meta_keywords']),
            ];

            if ($id) {
                $fields[] = $id;
                $pdo->prepare(
                    'UPDATE products SET category_id=?,name=?,slug=?,sku=?,description=?,search_terms=?,
                     image=?,image_webp=?,image_alt=?,mrp=?,price=?,stock=?,low_stock_threshold=?,
                     is_featured=?,is_active=?,meta_title=?,meta_description=?,meta_keywords=? WHERE id=?'
                )->execute($fields);
                $pid = $id;
            } else {
                $pdo->prepare(
                    'INSERT INTO products(category_id,name,slug,sku,description,search_terms,image,image_webp,
                     image_alt,mrp,price,stock,low_stock_threshold,is_featured,is_active,meta_title,
                     meta_description,meta_keywords) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute($fields);
                $pid = (int)$pdo->lastInsertId();
            }

            // Variants
            $pdo->prepare('DELETE FROM product_variants WHERE product_id=?')->execute([$pid]);
            $labels  = $_POST['variant_label']  ?? [];
            $types   = $_POST['variant_type']   ?? [];
            $quant   = $_POST['variant_qty']    ?? [];
            $mrps    = $_POST['variant_mrp']    ?? [];
            $prices  = $_POST['variant_price']  ?? [];
            $stocks  = $_POST['variant_stock']  ?? [];
            $default = (int)($_POST['default_variant'] ?? 0);
            foreach ($labels as $i => $label) {
                $label = trim($label);
                if ($label === '') continue;
                $pdo->prepare(
                    'INSERT INTO product_variants(product_id,label,unit_type,quantity,mrp,price,stock,is_default)
                     VALUES(?,?,?,?,?,?,?,?)'
                )->execute([
                    $pid, $label, $types[$i] ?? 'g', $quant[$i] ?? 1,
                    $mrps[$i] ?? 0, $prices[$i] ?? 0, $stocks[$i] ?? 0,
                    $i === $default ? 1 : 0,
                ]);
            }

            // Sync aggregate stock
            $pdo->prepare(
                'UPDATE products SET stock=(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id=?) WHERE id=?'
            )->execute([$pid, $pid]);

            // Synonyms
            $pdo->prepare('DELETE FROM product_synonyms WHERE product_id=?')->execute([$pid]);
            foreach (preg_split('/[\n,]+/', trim($_POST['search_terms'])) as $term) {
                $term = trim($term);
                if ($term) $pdo->prepare('INSERT INTO product_synonyms(product_id,term) VALUES(?,?)')->execute([$pid, $term]);
            }

            flash('success', $id ? 'Product updated.' : 'Product created.');
            redirect('admin/products.php?edit='.$pid);
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$cats     = $pdo->query('SELECT * FROM categories ORDER BY sort_order,id')->fetchAll();
$products = $pdo->query(
    'SELECT p.*,c.name category_name FROM products p
     JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC'
)->fetchAll();
$variants = [];
if ($edit) {
    $s = $pdo->prepare('SELECT * FROM product_variants WHERE product_id=? ORDER BY id');
    $s->execute([$edit['id']]);
    $variants = $s->fetchAll();
}

$adminTitle   = $edit ? 'Edit Product' : 'Products';
$adminSection = 'products';
$adminCrumbs  = $edit
    ? [['label'=>'Products','url'=>url('admin/products.php')], ['label'=>$edit['name']]]
    : [['label'=>'Products']];
include __DIR__.'/../includes/admin-header.php';
?>

<?php if ($err): ?>
  <div class="ap-alert"><?= e($err) ?></div>
<?php endif; ?>

<!-- ── Edit / Create form ──────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title"><?= $edit ? 'Edit product' : 'Add new product' ?></h2>
    <?php if ($edit): ?>
      <a class="ap-btn ap-btn-sm" href="<?= url('admin/products.php') ?>">+ New product</a>
    <?php endif; ?>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id"     value="<?= $edit['id'] ?? 0 ?>">

      <!-- Row 1: name + category + SKU -->
      <div class="ap-form-grid-3">
        <div class="ap-field">
          <label class="ap-label">Product name *</label>
          <input class="ap-input" name="name" value="<?= e($edit['name'] ?? '') ?>" required>
        </div>
        <div class="ap-field">
          <label class="ap-label">Category *</label>
          <select class="ap-select" name="category_id" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">SKU *</label>
          <input class="ap-input" name="sku" value="<?= e($edit['sku'] ?? 'AG-'.strtoupper(bin2hex(random_bytes(3)))) ?>" required>
        </div>
      </div>

      <!-- Row 2: description + image -->
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Description</label>
          <textarea class="ap-textarea" name="description"><?= e($edit['description'] ?? '') ?></textarea>
        </div>
        <div class="ap-field">
          <label class="ap-label">Product image (JPG / PNG / WEBP / SVG)</label>
          <input class="ap-input" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/svg+xml">
          <?php if (!empty($edit['image_webp']) || !empty($edit['image'])): ?>
            <img class="ap-img-preview" src="<?= e(product_image($edit)) ?>" alt="<?= e($edit['name']) ?>">
          <?php endif; ?>
          <label class="ap-label" style="margin-top:10px">Image alt text</label>
          <input class="ap-input" name="image_alt" value="<?= e($edit['image_alt'] ?? '') ?>">
        </div>
      </div>

      <!-- Row 3: pricing -->
      <div class="ap-section-title">Pricing &amp; stock</div>
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Base MRP (₹)</label>
          <input class="ap-input" name="mrp" type="number" step="0.01" value="<?= e($edit['mrp'] ?? 0) ?>">
        </div>
        <div class="ap-field">
          <label class="ap-label">Base price (₹)</label>
          <input class="ap-input" name="price" type="number" step="0.01" value="<?= e($edit['price'] ?? 0) ?>">
        </div>
        <div class="ap-field">
          <label class="ap-label">Base stock</label>
          <input class="ap-input" name="stock" type="number" step="0.001" value="<?= e($edit['stock'] ?? 0) ?>">
        </div>
        <div class="ap-field">
          <label class="ap-label">Low stock threshold</label>
          <input class="ap-input" name="low_stock_threshold" type="number" step="0.001" value="<?= e($edit['low_stock_threshold'] ?? 5) ?>">
        </div>
      </div>

      <!-- Variants -->
      <div class="ap-section-title">Variants / pack sizes</div>
      <div style="overflow-x:auto;">
        <div style="display:grid;grid-template-columns:1.6fr .75fr .75fr .9fr .9fr .9fr auto;gap:8px;padding:0 0 6px;min-width:600px;">
          <span class="ap-label">Label</span>
          <span class="ap-label">Unit</span>
          <span class="ap-label">Qty</span>
          <span class="ap-label">MRP (₹)</span>
          <span class="ap-label">Price (₹)</span>
          <span class="ap-label">Stock</span>
          <span class="ap-label">Default</span>
        </div>
        <div id="variants" style="min-width:600px;">
          <?php
          $rows = $variants ?: [['label'=>'1 KG','unit_type'=>'kg','quantity'=>1,'mrp'=>$edit['mrp']??0,'price'=>$edit['price']??0,'stock'=>$edit['stock']??0,'is_default'=>1]];
          foreach ($rows as $i => $v): ?>
          <div class="ap-variant-row">
            <input class="ap-input" name="variant_label[]" value="<?= e($v['label']) ?>" placeholder="1 KG">
            <select class="ap-select" name="variant_type[]">
              <option value="g"  <?= $v['unit_type']==='g'  ? 'selected':'' ?>>g</option>
              <option value="kg" <?= $v['unit_type']==='kg' ? 'selected':'' ?>>kg</option>
              <option value="pc" <?= $v['unit_type']==='pc' ? 'selected':'' ?>>pc</option>
            </select>
            <input class="ap-input" name="variant_qty[]"   type="number" step="0.001" value="<?= e((string)$v['quantity']) ?>">
            <input class="ap-input" name="variant_mrp[]"   type="number" step="0.01"  value="<?= e((string)$v['mrp']) ?>">
            <input class="ap-input" name="variant_price[]" type="number" step="0.01"  value="<?= e((string)$v['price']) ?>">
            <input class="ap-input" name="variant_stock[]" type="number" step="0.001" value="<?= e((string)$v['stock']) ?>">
            <label class="ap-check-row">
              <input type="radio" name="default_variant" value="<?= $i ?>" <?= $v['is_default'] ? 'checked' : '' ?>>
              Default
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <button type="button" class="ap-btn ap-btn-sm" onclick="addVariant()">+ Add variant</button>
      </div>

      <!-- SEO -->
      <div class="ap-section-title">SEO</div>
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Meta title</label>
          <input class="ap-input" name="meta_title" value="<?= e($edit['meta_title'] ?? '') ?>" placeholder="Meta title">
        </div>
        <div class="ap-field">
          <label class="ap-label">Search synonyms</label>
          <input class="ap-input" name="search_terms" value="<?= e($edit['search_terms'] ?? '') ?>" placeholder="potato, aalu, alu, आलू">
          <span class="ap-hint">Comma or newline separated — used for search.</span>
        </div>
        <div class="ap-field ap-form-wide">
          <label class="ap-label">Meta description</label>
          <textarea class="ap-textarea" name="meta_description"><?= e($edit['meta_description'] ?? '') ?></textarea>
        </div>
        <div class="ap-field ap-form-wide">
          <label class="ap-label">Meta keywords</label>
          <textarea class="ap-textarea" name="meta_keywords"><?= e($edit['meta_keywords'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Flags + submit -->
      <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;padding-top:8px;border-top:1px solid #edf2ea;margin-top:4px;">
        <label class="ap-check-row">
          <input type="checkbox" name="featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>>
          Featured product
        </label>
        <label class="ap-check-row">
          <input type="checkbox" name="active" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>>
          Active (visible on store)
        </label>
        <button class="ap-btn ap-btn-primary" style="margin-left:auto;">
          💾 Save product
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Product list ────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">All products</h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= count($products) ?> total</span>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>Image</th>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <img class="thumb" src="<?= e(product_image($p)) ?>" alt="">
            </td>
            <td>
              <b><?= e($p['name']) ?></b>
              <small><?= e($p['sku']) ?></small>
            </td>
            <td><?= e($p['category_name']) ?></td>
            <td>₹<?= number_format((float)$p['price'], 2) ?></td>
            <td class="<?= (float)$p['stock'] <= 0 ? 'stock-out' : ((float)$p['stock'] <= (float)$p['low_stock_threshold'] ? 'stock-low' : '') ?>">
              <?= $p['stock'] ?>
            </td>
            <td>
              <div style="display:flex;gap:8px;align-items:center;">
                <a class="ap-btn ap-btn-sm" href="?edit=<?= $p['id'] ?>">Edit</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $p['id'] ?>">
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
