<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/image.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

$uploadDir = __DIR__.'/../uploads/categories';
$publicDir = 'uploads/categories';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    try {
        $action    = $_POST['action'] ?? 'save';
        $id        = (int)($_POST['id'] ?? 0);
        $parentId  = (int)($_POST['parent_id'] ?? 0) ?: null;

        if ($action === 'delete') {
            // Detach children before archiving parent
            $pdo->prepare('UPDATE categories SET parent_id=NULL WHERE parent_id=?')->execute([$id]);
            $pdo->prepare('UPDATE categories SET is_active=0 WHERE id=?')->execute([$id]);
            flash('success', 'Category archived.');
            redirect('admin/categories.php');
        }

        $name  = trim($_POST['name']);
        $slug  = slugify($name);
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $im    = save_product_image($_FILES['image'], $uploadDir, $publicDir);
            $image = $im['webp'];
        }

        if ($id) {
            // Prevent setting a category as its own parent or child as parent
            if ($parentId === $id) $parentId = null;
            if ($image) {
                $pdo->prepare(
                    'UPDATE categories SET parent_id=?,name=?,slug=?,image=?,sort_order=?,is_active=?,
                     meta_title=?,meta_description=?,meta_keywords=?,listing_description=? WHERE id=?'
                )->execute([$parentId,$name,$slug,$image,$_POST['sort_order'],isset($_POST['active'])?1:0,
                    trim($_POST['meta_title']),trim($_POST['meta_description']),trim($_POST['meta_keywords']),
                    trim($_POST['listing_description'] ?? '') ?: null,$id]);
            } else {
                $pdo->prepare(
                    'UPDATE categories SET parent_id=?,name=?,slug=?,sort_order=?,is_active=?,
                     meta_title=?,meta_description=?,meta_keywords=?,listing_description=? WHERE id=?'
                )->execute([$parentId,$name,$slug,$_POST['sort_order'],isset($_POST['active'])?1:0,
                    trim($_POST['meta_title']),trim($_POST['meta_description']),trim($_POST['meta_keywords']),
                    trim($_POST['listing_description'] ?? '') ?: null,$id]);
            }
        } else {
            $pdo->prepare(
                'INSERT INTO categories(parent_id,name,slug,image,sort_order,is_active,meta_title,meta_description,meta_keywords,listing_description)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([$parentId,$name,$slug,$image,$_POST['sort_order'],1,
                trim($_POST['meta_title']),trim($_POST['meta_description']),trim($_POST['meta_keywords']),
                trim($_POST['listing_description'] ?? '') ?: null]);
        }
        flash('success', 'Category saved.');
        redirect('admin/categories.php');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM categories WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

// Load all categories for the parent dropdown and tree
$allCats = $pdo->query('SELECT * FROM categories ORDER BY sort_order,id')->fetchAll();

// Build parent → children tree
$parents  = array_filter($allCats, fn($c) => !$c['parent_id']);
$children = [];
foreach ($allCats as $c) {
    if ($c['parent_id']) $children[$c['parent_id']][] = $c;
}

// Top-level only for parent picker (can't pick a subcategory as parent)
$topLevel = array_values($parents);

$adminTitle   = 'Categories';
$adminSection = 'categories';
$adminCrumbs  = [['label' => 'Categories']];
include __DIR__.'/../includes/admin-header.php';
?>

<?php if ($err): ?>
  <div class="ap-alert"><?= e($err) ?></div>
<?php endif; ?>

<!-- ── Add / Edit form ──────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title"><?= $edit ? 'Edit category' : 'Add new category' ?></h2>
    <?php if ($edit): ?>
      <a class="ap-btn ap-btn-sm" href="<?= url('admin/categories.php') ?>">+ New category</a>
    <?php endif; ?>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id"   value="<?= $edit['id'] ?? 0 ?>">

      <div class="ap-form-grid">

        <div class="ap-field">
          <label class="ap-label">Category name *</label>
          <input class="ap-input" name="name" value="<?= e($edit['name'] ?? '') ?>" required>
        </div>

        <div class="ap-field">
          <label class="ap-label">
            Parent category
            <span style="font-weight:400;color:#9aaa9b">(leave blank for top-level)</span>
          </label>
          <select class="ap-select" name="parent_id">
            <option value="">— Top-level category —</option>
            <?php foreach ($topLevel as $p):
              // Don't show the category being edited as a parent option
              if ($edit && $p['id'] === (int)$edit['id']) continue;
            ?>
              <option value="<?= $p['id'] ?>"
                <?= ($edit['parent_id'] ?? null) == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="ap-hint">e.g. select "Fresh Fruits" to make this a subcategory under it.</span>
        </div>

        <div class="ap-field">
          <label class="ap-label">Sort order</label>
          <input class="ap-input" name="sort_order" type="number"
                 value="<?= e((string)($edit['sort_order'] ?? 0)) ?>">
        </div>

        <div class="ap-field" style="align-self:end;">
          <label class="ap-check-row">
            <input type="checkbox" name="active"
                   <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>>
            Active (visible on store)
          </label>
        </div>

        <div class="ap-field">
          <label class="ap-label">Image (JPG / PNG / WEBP / SVG)</label>
          <input class="ap-input" type="file" name="image"
                 accept="image/jpeg,image/png,image/webp,image/svg+xml">
          <?php if (!empty($edit['image'])): ?>
            <img class="ap-img-preview" src="<?= e(asset_url($edit['image'])) ?>" alt="" style="margin-top:8px;">
          <?php endif; ?>
        </div>

      </div>

      <div class="ap-section-title">SEO</div>
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Meta title</label>
          <input class="ap-input" name="meta_title"
                 value="<?= e($edit['meta_title'] ?? '') ?>" placeholder="Meta title">
        </div>
        <div class="ap-field">
          <label class="ap-label">Meta keywords</label>
          <textarea class="ap-textarea" name="meta_keywords"><?= e($edit['meta_keywords'] ?? '') ?></textarea>
        </div>
        <div class="ap-field ap-form-wide">
          <label class="ap-label">Meta description</label>
          <textarea class="ap-textarea" name="meta_description"><?= e($edit['meta_description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="ap-section-title">Listing page content</div>
      <div class="ap-form-grid">
        <div class="ap-field ap-form-wide">
          <label class="ap-label">Listing description</label>
          <textarea class="ap-textarea" name="listing_description" rows="4"
                    placeholder="Shown in a box above the footer on this category's listing page. Leave blank to hide the box."><?= e($edit['listing_description'] ?? '') ?></textarea>
          <span class="ap-hint">Longer, customer-facing description displayed above the footer on the category page. If empty, no box is shown.</span>
        </div>
      </div>

      <div><button class="ap-btn ap-btn-primary">💾 Save category</button></div>
    </form>
  </div>
</div>

<!-- ── Category tree ────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">All categories</h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= count($allCats) ?> total</span>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>Image</th>
            <th>Name</th>
            <th>URL slug</th>
            <th>Sort</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($parents as $parent): ?>
          <!-- Parent row -->
          <tr style="background:#f8fbf6;">
            <td>
              <img class="thumb" src="<?= e(asset_url($parent['image'])) ?>" alt="">
            </td>
            <td>
              <b style="font-size:14px;">📁 <?= e($parent['name']) ?></b>
              <?php $childCount = count($children[$parent['id']] ?? []); ?>
              <?php if ($childCount): ?>
                <small style="color:#7a8a7b;"><?= $childCount ?> subcategories</small>
              <?php endif; ?>
            </td>
            <td><code style="font-size:12px;color:#4a6a4c;">/<?= e($parent['slug']) ?>/</code></td>
            <td><?= $parent['sort_order'] ?></td>
            <td>
              <span class="ap-badge <?= $parent['is_active'] ? 'ap-badge-green' : 'ap-badge-gray' ?>">
                <?= $parent['is_active'] ? 'Active' : 'Archived' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <a class="ap-btn ap-btn-sm" href="?edit=<?= $parent['id'] ?>">Edit</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $parent['id'] ?>">
                  <button class="ap-btn ap-btn-sm ap-btn-danger"
                          onclick="return confirm('Archive this category? Subcategories will become top-level.')">
                    Archive
                  </button>
                </form>
              </div>
            </td>
          </tr>

          <!-- Child rows -->
          <?php foreach (($children[$parent['id']] ?? []) as $child): ?>
          <tr>
            <td style="padding-left:30px;">
              <img class="thumb" src="<?= e(asset_url($child['image'])) ?>" alt="">
            </td>
            <td style="padding-left:30px;">
              <span style="color:#9aaa9b;margin-right:6px;">└─</span>
              <?= e($child['name']) ?>
              <span class="ap-badge ap-badge-blue" style="font-size:10px;margin-left:6px;">sub</span>
            </td>
            <td>
              <code style="font-size:12px;color:#4a6a4c;">/<?= e($parent['slug']) ?>/<?= e($child['slug']) ?>/</code>
            </td>
            <td><?= $child['sort_order'] ?></td>
            <td>
              <span class="ap-badge <?= $child['is_active'] ? 'ap-badge-green' : 'ap-badge-gray' ?>">
                <?= $child['is_active'] ? 'Active' : 'Archived' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <a class="ap-btn ap-btn-sm" href="?edit=<?= $child['id'] ?>">Edit</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $child['id'] ?>">
                  <button class="ap-btn ap-btn-sm ap-btn-danger"
                          onclick="return confirm('Archive this subcategory?')">
                    Archive
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>

          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
