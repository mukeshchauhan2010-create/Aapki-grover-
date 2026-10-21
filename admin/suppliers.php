<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'toggle') {
        $pdo->prepare('UPDATE suppliers SET is_active=1-is_active WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success', 'Supplier status updated.');
    } else {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $type  = in_array($_POST['type'] ?? 'vendor', ['farmer','vendor','market','other'], true) ? $_POST['type'] : 'vendor';
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            flash('error', 'Supplier name is required.');
        } elseif ($id) {
            $pdo->prepare('UPDATE suppliers SET name=?,type=?,phone=?,notes=? WHERE id=?')->execute([$name,$type,$phone?:null,$notes?:null,$id]);
            flash('success', 'Supplier updated.');
        } else {
            $pdo->prepare('INSERT INTO suppliers(name,type,phone,notes,is_active) VALUES(?,?,?,?,1)')->execute([$name,$type,$phone?:null,$notes?:null]);
            flash('success', 'Supplier added.');
        }
    }
    redirect('admin/suppliers.php');
}

$edit = null;
if (!empty($_GET['edit'])) { $s=$pdo->prepare('SELECT * FROM suppliers WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); }
$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY is_active DESC, name')->fetchAll();

$adminTitle='Suppliers'; $adminSection='suppliers'; $adminCrumbs=[['label'=>'Suppliers']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title"><?= $edit?'Edit supplier':'Add supplier' ?></h2><?php if($edit): ?><a class="ap-btn ap-btn-sm" href="<?=url('admin/suppliers.php')?>">+ New</a><?php endif; ?></div>
  <div class="ap-card-body">
    <p class="ap-hint">Farmers, vendors and market sources you buy stock from.</p>
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Name *</label><input class="ap-input" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
        <div class="ap-field"><label class="ap-label">Type</label>
          <select class="ap-select" name="type">
            <?php foreach(['farmer'=>'Farmer','vendor'=>'Vendor','market'=>'Market / Mandi','other'=>'Other'] as $k=>$v): ?>
              <option value="<?=$k?>" <?=($edit['type']??'vendor')===$k?'selected':''?>><?=$v?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ap-field"><label class="ap-label">Phone</label><input class="ap-input" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
        <div class="ap-field ap-form-wide"><label class="ap-label">Notes</label><input class="ap-input" name="notes" value="<?= e($edit['notes'] ?? '') ?>"></div>
      </div>
      <div><button class="ap-btn ap-btn-primary">💾 Save supplier</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">All suppliers</h2><span style="font-size:13px;color:#7a8a7b;"><?= count($suppliers) ?> total</span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap"><table class="ap-table">
      <thead><tr><th>Name</th><th>Type</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if(!$suppliers): ?><tr><td colspan="5"><span class="ap-hint" style="padding:12px;display:block">No suppliers yet.</span></td></tr><?php endif; ?>
      <?php foreach($suppliers as $s): ?>
        <tr>
          <td><b><?= e($s['name']) ?></b><?php if($s['notes']): ?><br><small class="ap-hint"><?= e($s['notes']) ?></small><?php endif; ?></td>
          <td><span class="ap-badge ap-badge-blue"><?= e(ucfirst($s['type'])) ?></span></td>
          <td><?= e($s['phone'] ?? '—') ?></td>
          <td><span class="ap-badge <?= $s['is_active']?'ap-badge-green':'ap-badge-gray' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <div style="display:flex;gap:6px;">
              <a class="ap-btn ap-btn-sm" href="?edit=<?= $s['id'] ?>">Edit</a>
              <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="ap-btn ap-btn-sm"><?= $s['is_active']?'Deactivate':'Activate' ?></button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
