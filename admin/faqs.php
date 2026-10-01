<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'content')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM faqs WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'FAQ deleted.');
        redirect('admin/faqs.php');
    }
    $id       = (int)($_POST['id'] ?? 0);
    $category = trim($_POST['category'] ?? '') ?: 'General';
    $question = trim($_POST['question'] ?? '');
    $answer   = trim($_POST['answer'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if ($question === '' || $answer === '') {
        flash('error', 'Question and answer are required.');
    } elseif ($id) {
        $pdo->prepare('UPDATE faqs SET category=?,question=?,answer=?,sort_order=?,is_active=? WHERE id=?')
            ->execute([$category,$question,$answer,$sort,$active,$id]);
        flash('success', 'FAQ updated.');
    } else {
        $pdo->prepare('INSERT INTO faqs(category,question,answer,sort_order,is_active) VALUES(?,?,?,?,?)')
            ->execute([$category,$question,$answer,$sort,$active]);
        flash('success', 'FAQ added.');
    }
    redirect('admin/faqs.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM faqs WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

$faqs = $pdo->query('SELECT * FROM faqs ORDER BY category, sort_order, id')->fetchAll();
$cats = $pdo->query('SELECT DISTINCT category FROM faqs ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$adminTitle='FAQs'; $adminSection='faqs'; $adminCrumbs=[['label'=>'FAQs']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title"><?= $edit ? 'Edit FAQ' : 'Add FAQ' ?></h2>
    <?php if ($edit): ?><a class="ap-btn ap-btn-sm" href="<?= url('admin/faqs.php') ?>">+ New FAQ</a><?php endif; ?>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Category</label><input class="ap-input" name="category" list="faqCats" value="<?= e($edit['category'] ?? '') ?>" placeholder="Order"><datalist id="faqCats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
        <div class="ap-field"><label class="ap-label">Sort order</label><input class="ap-input" type="number" name="sort_order" value="<?= e((string)($edit['sort_order'] ?? 0)) ?>"></div>
        <div class="ap-field" style="align-self:end;"><label class="ap-check-row"><input type="checkbox" name="is_active" <?= (!isset($edit['is_active'])||$edit['is_active'])?'checked':'' ?>> Active</label></div>
      </div>
      <div class="ap-field ap-form-wide"><label class="ap-label">Question *</label><input class="ap-input" name="question" value="<?= e($edit['question'] ?? '') ?>" required></div>
      <div class="ap-field ap-form-wide"><label class="ap-label">Answer *</label><textarea class="ap-textarea" name="answer" rows="6" required><?= e($edit['answer'] ?? '') ?></textarea><span class="ap-hint">Line breaks are preserved on the FAQ page.</span></div>
      <div><button class="ap-btn ap-btn-primary">💾 Save FAQ</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">All FAQs</h2><span style="font-size:13px;color:#7a8a7b;"><?= count($faqs) ?> total</span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead><tr><th>Category</th><th>Question</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($faqs as $f): ?>
          <tr>
            <td><span class="ap-badge ap-badge-blue"><?= e($f['category']) ?></span></td>
            <td><?= e(mb_strimwidth($f['question'],0,80,'…')) ?></td>
            <td><?= (int)$f['sort_order'] ?></td>
            <td><span class="ap-badge <?= $f['is_active']?'ap-badge-green':'ap-badge-gray' ?>"><?= $f['is_active']?'Active':'Hidden' ?></span></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a class="ap-btn ap-btn-sm" href="?edit=<?= $f['id'] ?>">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this FAQ?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $f['id'] ?>">
                  <button class="ap-btn ap-btn-sm ap-btn-danger">Delete</button>
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
