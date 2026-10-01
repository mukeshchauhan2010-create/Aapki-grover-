<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'content')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $id    = (int)($_POST['id'] ?? 0);
    $slug  = slugify(trim($_POST['slug'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $body  = (string)($_POST['body'] ?? '');
    $mt    = trim($_POST['meta_title'] ?? '');
    $md    = trim($_POST['meta_description'] ?? '');
    $active= isset($_POST['is_active']) ? 1 : 0;

    if ($title === '' || $slug === '') {
        flash('error', 'Title and slug are required.');
    } elseif ($id) {
        $pdo->prepare('UPDATE pages SET slug=?,title=?,body=?,meta_title=?,meta_description=?,is_active=? WHERE id=?')
            ->execute([$slug,$title,$body,$mt?:null,$md?:null,$active,$id]);
        flash('success', 'Page updated.');
    } else {
        $pdo->prepare('INSERT INTO pages(slug,title,body,meta_title,meta_description,is_active) VALUES(?,?,?,?,?,?)')
            ->execute([$slug,$title,$body,$mt?:null,$md?:null,$active]);
        flash('success', 'Page created.');
    }
    redirect('admin/pages.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM pages WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

$pages = $pdo->query('SELECT * FROM pages ORDER BY title')->fetchAll();

$adminTitle='Pages'; $adminSection='pages'; $adminCrumbs=[['label'=>'Pages']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title"><?= $edit ? 'Edit page' : 'Add / edit page' ?></h2>
    <?php if ($edit): ?><a class="ap-btn ap-btn-sm" href="<?= url('admin/pages.php') ?>">+ New page</a><?php endif; ?>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Title *</label><input class="ap-input" name="title" value="<?= e($edit['title'] ?? '') ?>" required></div>
        <div class="ap-field"><label class="ap-label">Slug (URL) *</label><input class="ap-input" name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="about-us" required><span class="ap-hint">Opens at /slug/ e.g. about-us → /about-us/</span></div>
        <div class="ap-field" style="align-self:end;"><label class="ap-check-row"><input type="checkbox" name="is_active" <?= (!isset($edit['is_active'])||$edit['is_active'])?'checked':'' ?>> Active</label></div>
      </div>
      <div class="ap-field ap-form-wide"><label class="ap-label">Body (HTML allowed)</label><textarea class="ap-textarea" name="body" rows="16"><?= e($edit['body'] ?? '') ?></textarea></div>
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Meta title</label><input class="ap-input" name="meta_title" value="<?= e($edit['meta_title'] ?? '') ?>"></div>
        <div class="ap-field ap-form-wide"><label class="ap-label">Meta description</label><textarea class="ap-textarea" name="meta_description"><?= e($edit['meta_description'] ?? '') ?></textarea></div>
      </div>
      <div><button class="ap-btn ap-btn-primary">💾 Save page</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">All pages</h2><span style="font-size:13px;color:#7a8a7b;"><?= count($pages) ?> total</span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
          <tr>
            <td><b><?= e($p['title']) ?></b></td>
            <td><code>/<?= e($p['slug']) ?>/</code></td>
            <td><span class="ap-badge <?= $p['is_active']?'ap-badge-green':'ap-badge-gray' ?>"><?= $p['is_active']?'Active':'Hidden' ?></span></td>
            <td><small><?= e(substr((string)$p['updated_at'],0,16)) ?></small></td>
            <td><div style="display:flex;gap:6px;"><a class="ap-btn ap-btn-sm" href="?edit=<?= $p['id'] ?>">Edit</a><a class="ap-btn ap-btn-sm" target="_blank" href="<?= e(url($p['slug'].'/')) ?>">View</a></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
