<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'content')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    if (isset($_POST['banner'])) {
        $st = $pdo->prepare('INSERT INTO banners(title,subtitle,image,link,is_active,sort_order) VALUES(?,?,?,?,?,?)');
        $st->execute([trim($_POST['title']), trim($_POST['subtitle']), trim($_POST['image']), trim($_POST['link']), 1, (int)$_POST['sort_order']]);
        flash('success', 'Banner added.');
    }
    if (isset($_POST['popup'])) {
        $pdo->query('UPDATE popups SET is_active=0');
        $st = $pdo->prepare('INSERT INTO popups(title,message,button_text,button_url,is_active,start_at,end_at) VALUES(?,?,?,?,1,?,?)');
        $st->execute([trim($_POST['ptitle']), trim($_POST['message']), trim($_POST['button_text']), trim($_POST['button_url']), $_POST['start_at'] ?: null, $_POST['end_at'] ?: null]);
        flash('success', 'Popup published.');
    }
    if (isset($_POST['delete_banner'])) {
        $pdo->prepare('DELETE FROM banners WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'Banner removed.');
    }
    redirect('admin/content.php');
}

$banners = $pdo->query('SELECT * FROM banners ORDER BY sort_order,id DESC')->fetchAll();

$adminTitle='Banner & popup'; $adminSection='content'; $adminCrumbs=[['label'=>'Banner & popup']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">🖼️ Homepage banner</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Banners appear in the homepage hero slider. Use a wide farm/fresh-food image.</p>
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="banner" value="1">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Title</label><input class="ap-input" name="title"></div>
        <div class="ap-field"><label class="ap-label">Subtitle</label><input class="ap-input" name="subtitle"></div>
        <div class="ap-field ap-form-wide"><label class="ap-label">Image path / URL *</label><input class="ap-input" name="image" value="assets/images/banners/hero-farm-home.svg" required></div>
        <div class="ap-field"><label class="ap-label">Link (optional)</label><input class="ap-input" name="link"></div>
        <div class="ap-field"><label class="ap-label">Sort order</label><input class="ap-input" type="number" name="sort_order" value="1"></div>
      </div>
      <div><button class="ap-btn ap-btn-primary">+ Add banner</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">📢 Important popup</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Publishing a popup deactivates any previous one (only the latest is active).</p>
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="popup" value="1">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Title *</label><input class="ap-input" name="ptitle" required></div>
        <div class="ap-field"><label class="ap-label">Button text</label><input class="ap-input" name="button_text"></div>
        <div class="ap-field ap-form-wide"><label class="ap-label">Message *</label><textarea class="ap-textarea" name="message" required></textarea></div>
        <div class="ap-field"><label class="ap-label">Button URL</label><input class="ap-input" name="button_url"></div>
        <div class="ap-field"><label class="ap-label">Start</label><input class="ap-input" type="datetime-local" name="start_at"></div>
        <div class="ap-field"><label class="ap-label">End</label><input class="ap-input" type="datetime-local" name="end_at"></div>
      </div>
      <div><button class="ap-btn ap-btn-primary">Publish popup</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Current banners</h2><span style="font-size:13px;color:#7a8a7b;"><?= count($banners) ?> total</span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap"><table class="ap-table">
      <thead><tr><th>Preview</th><th>Title</th><th>Image</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if(!$banners): ?><tr><td colspan="6"><span class="ap-hint" style="padding:12px;display:block">No banners yet.</span></td></tr><?php endif; ?>
      <?php foreach($banners as $b): ?>
        <tr>
          <td><img class="thumb" src="<?= e(asset_url($b['image'])) ?>" alt="" style="width:90px;height:40px;object-fit:cover;border-radius:6px"></td>
          <td><b><?= e($b['title'] ?: 'Untitled') ?></b><?php if($b['subtitle']): ?><br><small class="ap-hint"><?= e($b['subtitle']) ?></small><?php endif; ?></td>
          <td><code style="font-size:11px"><?= e($b['image']) ?></code></td>
          <td><?= (int)$b['sort_order'] ?></td>
          <td><span class="ap-badge <?= $b['is_active']?'ap-badge-green':'ap-badge-gray' ?>"><?= $b['is_active']?'Active':'Hidden' ?></span></td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this banner?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="delete_banner" value="1">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button class="ap-btn ap-btn-sm ap-btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
