<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'settings')) redirect('admin/');

$keys = [
    'cart_mode','points_enabled','points_per_100','redeem_enabled','redeem_min_points',
    'point_value_rupees','referral_enabled','referral_points','store_popup_enabled',
    'default_meta_title','default_meta_description','default_meta_keywords',
    'razorpay_enabled','razorpay_key_id','razorpay_key_secret','razorpay_webhook_secret',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    foreach ($keys as $k) {
        $v = (string)($_POST[$k] ?? '');
        $pdo->prepare(
            'INSERT INTO settings(setting_key,setting_value) VALUES(?,?)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
        )->execute([$k, $v]);
    }
    flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

$adminTitle   = 'Settings';
$adminSection = 'settings';
$adminCrumbs  = [['label'=>'Settings']];
include __DIR__.'/../includes/admin-header.php';

// Helper: on/off select
function on_off(PDO $pdo, string $key, string $name): string {
    $on = setting_bool($pdo, $key);
    return '<select class="ap-select" name="'.e($name).'">
      <option value="1"'.($on  ? ' selected' : '').'>On</option>
      <option value="0"'.(!$on ? ' selected' : '').'>Off</option>
    </select>';
}
?>

<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

<!-- ── Cart ─────────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">🛒 Cart mode</h2></div>
  <div class="ap-card-body">
    <div class="ap-form-grid">
      <div class="ap-field">
        <label class="ap-label">Cart mode</label>
        <select class="ap-select" name="cart_mode">
          <option value="coming_soon" <?= setting($pdo,'cart_mode')==='coming_soon'?'selected':'' ?>>Coming Soon (disabled)</option>
          <option value="live"        <?= setting($pdo,'cart_mode')==='live'?'selected':'' ?>>Live — customers can order</option>
        </select>
        <span class="ap-hint">Switch to "Live" when ready to accept orders.</span>
      </div>
      <div class="ap-field">
        <label class="ap-label">Popup enabled</label>
        <?= on_off($pdo,'store_popup_enabled','store_popup_enabled') ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Loyalty points ────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">⭐ Loyalty points</h2></div>
  <div class="ap-card-body">
    <div class="ap-form-grid">
      <div class="ap-field">
        <label class="ap-label">Points enabled</label>
        <?= on_off($pdo,'points_enabled','points_enabled') ?>
      </div>
      <div class="ap-field">
        <label class="ap-label">Points earned per ₹100 spent</label>
        <input class="ap-input" name="points_per_100" value="<?= e(setting($pdo,'points_per_100','1')) ?>">
      </div>
      <div class="ap-field">
        <label class="ap-label">Redeem enabled</label>
        <?= on_off($pdo,'redeem_enabled','redeem_enabled') ?>
      </div>
      <div class="ap-field">
        <label class="ap-label">Minimum points to redeem</label>
        <input class="ap-input" name="redeem_min_points" value="<?= e(setting($pdo,'redeem_min_points','100')) ?>">
      </div>
      <div class="ap-field">
        <label class="ap-label">₹ value per 1 point</label>
        <input class="ap-input" name="point_value_rupees" value="<?= e(setting($pdo,'point_value_rupees','1')) ?>">
      </div>
    </div>
  </div>
</div>

<!-- ── Referral ──────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">🤝 Referral programme</h2></div>
  <div class="ap-card-body">
    <div class="ap-form-grid">
      <div class="ap-field">
        <label class="ap-label">Referral enabled</label>
        <?= on_off($pdo,'referral_enabled','referral_enabled') ?>
      </div>
      <div class="ap-field">
        <label class="ap-label">Points awarded per referral</label>
        <input class="ap-input" name="referral_points" value="<?= e(setting($pdo,'referral_points','10')) ?>">
      </div>
    </div>
  </div>
</div>

<!-- ── Razorpay ──────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">💳 Razorpay payments</h2></div>
  <div class="ap-card-body">
    <div class="ap-form-grid">
      <div class="ap-field">
        <label class="ap-label">Razorpay enabled</label>
        <?= on_off($pdo,'razorpay_enabled','razorpay_enabled') ?>
      </div>
      <div class="ap-field">
        <label class="ap-label">Key ID</label>
        <input class="ap-input" name="razorpay_key_id" value="<?= e(setting($pdo,'razorpay_key_id')) ?>" placeholder="rzp_live_…">
      </div>
      <div class="ap-field">
        <label class="ap-label">Key Secret</label>
        <input class="ap-input" type="password" name="razorpay_key_secret" value="<?= e(setting($pdo,'razorpay_key_secret')) ?>">
      </div>
      <div class="ap-field">
        <label class="ap-label">Webhook Secret</label>
        <input class="ap-input" type="password" name="razorpay_webhook_secret" value="<?= e(setting($pdo,'razorpay_webhook_secret')) ?>">
      </div>
    </div>
  </div>
</div>

<!-- ── SEO ───────────────────────────────────────────────────── -->
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">🔍 Default SEO</h2></div>
  <div class="ap-card-body">
    <div class="ap-form">
      <div class="ap-field">
        <label class="ap-label">Default meta title</label>
        <input class="ap-input" name="default_meta_title" value="<?= e(setting($pdo,'default_meta_title')) ?>">
      </div>
      <div class="ap-field">
        <label class="ap-label">Default meta description</label>
        <textarea class="ap-textarea" name="default_meta_description"><?= e(setting($pdo,'default_meta_description')) ?></textarea>
      </div>
      <div class="ap-field">
        <label class="ap-label">Default meta keywords</label>
        <textarea class="ap-textarea" name="default_meta_keywords"><?= e(setting($pdo,'default_meta_keywords')) ?></textarea>
      </div>
    </div>
  </div>
</div>

<div style="padding-bottom:10px;">
  <button class="ap-btn ap-btn-primary" style="min-width:180px;">💾 Save all settings</button>
</div>

</form>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
