<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/wallet.php';
$pdo = db();
if (!user_can($pdo, 'users')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'role';
    if ($action === 'wallet') {
        $targetId = (int)($_POST['id'] ?? 0);
        $amount   = round((float)($_POST['amount'] ?? 0), 2);
        $type     = in_array($_POST['wallet_type'] ?? 'credit', ['credit','debit'], true) ? $_POST['wallet_type'] : 'credit';
        $reason   = trim($_POST['reason'] ?? '') ?: ($type === 'credit' ? 'Admin credit' : 'Admin debit');
        if ($targetId > 0 && $amount > 0) {
            try {
                wallet_apply($pdo, $targetId, $amount, $type, $reason);
                flash('success', ucfirst($type).' of ₹'.number_format($amount,2).' applied.');
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', 'Enter a valid amount.');
        }
        redirect('admin/users.php');
    }
    // Default: role update
    $pdo->prepare('UPDATE users SET role_slug=? WHERE id=?')
        ->execute([$_POST['role'], (int)$_POST['id']]);
    flash('success', 'Role updated.');
    redirect('admin/users.php');
}

$users = $pdo->query(
    'SELECT id, name, email, role_slug, points, wallet_balance, is_active, created_at
     FROM users ORDER BY id DESC LIMIT 300'
)->fetchAll();
$roles = $pdo->query('SELECT slug, name FROM roles')->fetchAll();

$adminTitle   = 'Users & Roles';
$adminSection = 'users';
$adminCrumbs  = [['label'=>'Users & Roles']];
include __DIR__.'/../includes/admin-header.php';
?>

<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Users &amp; roles</h2>
    <span style="font-size:13px;color:#7a8a7b;"><?= count($users) ?> shown (latest 300)</span>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Role</th>
            <th>Points</th>
            <th>Wallet (₹)</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Change role</th>
            <th>Wallet credit / debit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><b><?= e($u['name']) ?></b></td>
            <td><small><?= e($u['email'] ?? '—') ?></small></td>
            <td>
              <span class="ap-badge <?= $u['role_slug'] === 'customer' ? 'ap-badge-gray' : 'ap-badge-blue' ?>">
                <?= e(ucwords(str_replace('_', ' ', $u['role_slug']))) ?>
              </span>
            </td>
            <td><?= number_format((int)$u['points']) ?></td>
            <td><b>₹<?= number_format((float)($u['wallet_balance'] ?? 0), 2) ?></b></td>
            <td>
              <span class="ap-badge <?= $u['is_active'] ? 'ap-badge-green' : 'ap-badge-red' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td><small><?= e(substr($u['created_at'], 0, 10)) ?></small></td>
            <td>
              <form class="ap-inline" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id"   value="<?= $u['id'] ?>">
                <select class="ap-select" name="role" style="min-width:140px;">
                  <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['slug']) ?>" <?= $r['slug'] === $u['role_slug'] ? 'selected' : '' ?>>
                      <?= e($r['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="ap-btn ap-btn-sm ap-btn-primary">Save</button>
              </form>
            </td>
            <td>
              <form class="ap-inline" method="post" style="flex-wrap:wrap;gap:4px;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="action" value="wallet">
                <select class="ap-select" name="wallet_type" style="min-width:84px;">
                  <option value="credit">Credit</option>
                  <option value="debit">Debit</option>
                </select>
                <input class="ap-input" name="amount" type="number" min="1" step="0.01" placeholder="₹" style="width:80px;">
                <input class="ap-input" name="reason" placeholder="Reason" style="width:120px;">
                <button class="ap-btn ap-btn-sm">Apply</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
