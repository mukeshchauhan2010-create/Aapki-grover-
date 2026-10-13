<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/image.php';
$pdo = db();
if (!user_can($pdo, 'settings')) redirect('admin/');

$uploadDir = __DIR__.'/../uploads/qr';
$publicDir = 'uploads/qr';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'upload';
    if ($action === 'upload') {
        if (!empty($_FILES['qr']['name'])) {
            try {
                $im = save_product_image($_FILES['qr'], $uploadDir, $publicDir);
                $makeActive = !empty($_POST['make_active']);
                if ($makeActive) $pdo->query('UPDATE payment_qr SET is_active=0');
                $pdo->prepare('INSERT INTO payment_qr(label,image,is_active) VALUES(?,?,?)')
                    ->execute([trim($_POST['label'] ?? '') ?: null, $im['webp'], $makeActive ? 1 : 0]);
                flash('success', 'QR image uploaded.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', 'Please choose a QR image to upload.');
        }
    } elseif ($action === 'activate') {
        $pdo->query('UPDATE payment_qr SET is_active=0');
        $pdo->prepare('UPDATE payment_qr SET is_active=1 WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'Active QR updated.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM payment_qr WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'QR removed.');
    }
    redirect('admin/qr.php');
}

$qrs = $pdo->query('SELECT * FROM payment_qr ORDER BY is_active DESC, id DESC')->fetchAll();

$adminTitle='Payment QR'; $adminSection='qr'; $adminCrumbs=[['label'=>'Payment QR']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Upload UPI QR</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Upload one or more UPI QR images. Exactly <b>one</b> can be active at a time — the active QR is printed on COD delivery labels.</p>
    <form class="ap-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">QR image (PNG/JPG/WEBP) *</label><input class="ap-input" type="file" name="qr" accept="image/png,image/jpeg,image/webp" required></div>
        <div class="ap-field"><label class="ap-label">Label (optional)</label><input class="ap-input" name="label" placeholder="e.g. GPay / PhonePe UPI"></div>
        <div class="ap-field" style="align-self:end;"><label class="ap-check-row"><input type="checkbox" name="make_active" checked> Make this the active QR</label></div>
      </div>
      <div><button class="ap-btn ap-btn-primary">⬆ Upload QR</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Uploaded QR codes</h2><span style="font-size:13px;color:#7a8a7b;"><?= count($qrs) ?> total</span></div>
  <div class="ap-card-body">
    <?php if(!$qrs): ?><p class="ap-hint">No QR images uploaded yet.</p><?php endif; ?>
    <div style="display:flex;flex-wrap:wrap;gap:16px;">
      <?php foreach($qrs as $q): ?>
        <div style="border:1px solid <?= $q['is_active']?'#12652f':'#e2eadc' ?>;border-radius:12px;padding:12px;width:200px;text-align:center;<?= $q['is_active']?'box-shadow:0 0 0 2px #12652f22':'' ?>">
          <img src="<?= e(asset_url($q['image'])) ?>" alt="" style="width:150px;height:150px;object-fit:contain">
          <div style="font-size:13px;font-weight:700;margin-top:6px"><?= e($q['label'] ?: 'UPI QR') ?></div>
          <div style="margin:6px 0"><span class="ap-badge <?= $q['is_active']?'ap-badge-green':'ap-badge-gray' ?>"><?= $q['is_active']?'Active':'Inactive' ?></span></div>
          <div style="display:flex;gap:6px;justify-content:center;">
            <?php if(!$q['is_active']): ?>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $q['id'] ?>"><button class="ap-btn ap-btn-sm ap-btn-primary">Activate</button></form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this QR?');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $q['id'] ?>"><button class="ap-btn ap-btn-sm ap-btn-danger">Delete</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
