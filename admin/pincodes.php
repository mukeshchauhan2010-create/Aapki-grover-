<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'settings')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'add';
    if ($action === 'add') {
        $pin = preg_replace('/\D+/', '', $_POST['pincode'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if (!preg_match('/^[1-9][0-9]{5}$/', $pin)) {
            flash('error', 'Enter a valid 6-digit pincode.');
        } else {
            try {
                $pdo->prepare('INSERT INTO service_pincodes(pincode,label,is_active) VALUES(?,?,1)')
                    ->execute([$pin, $label ?: null]);
                flash('success', "Pincode $pin added.");
            } catch (Throwable $e) {
                flash('error', "Pincode $pin already exists.");
            }
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE service_pincodes SET is_active=1-is_active WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'Pincode status updated.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM service_pincodes WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'Pincode removed.');
    }
    redirect('admin/pincodes.php');
}

$pins = $pdo->query('SELECT * FROM service_pincodes ORDER BY is_active DESC, pincode')->fetchAll();
$activeCount = 0; foreach ($pins as $p) { if ($p['is_active']) $activeCount++; }

$adminTitle='Service Pincodes'; $adminSection='pincodes'; $adminCrumbs=[['label'=>'Service Pincodes']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Add a service pincode</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Only customers in an <b>active</b> pincode can add products to the cart and check out. Add new areas here as you expand.</p>
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Pincode *</label><input class="ap-input" name="pincode" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" placeholder="110084" required></div>
        <div class="ap-field"><label class="ap-label">Area label (optional)</label><input class="ap-input" name="label" placeholder="Burari, Delhi"></div>
      </div>
      <div><button class="ap-btn ap-btn-primary">+ Add pincode</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Service areas</h2><span style="font-size:13px;color:#7a8a7b;"><?= $activeCount ?> active / <?= count($pins) ?> total</span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead><tr><th>Pincode</th><th>Area</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$pins): ?>
          <tr><td colspan="4"><span class="ap-hint" style="padding:12px;display:block">No pincodes yet. Add one above.</span></td></tr>
        <?php endif; ?>
        <?php foreach ($pins as $p): ?>
          <tr>
            <td><b><?= e($p['pincode']) ?></b></td>
            <td><?= e($p['label'] ?? '—') ?></td>
            <td><span class="ap-badge <?= $p['is_active'] ? 'ap-badge-green' : 'ap-badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td>
              <div style="display:flex;gap:6px;">
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="ap-btn ap-btn-sm"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Remove this pincode?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
