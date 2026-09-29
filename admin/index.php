<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();

// ── Admin login gate ─────────────────────────────────────────────────────────
if (!is_logged_in() || !in_array($_SESSION['role'] ?? '', ['super_admin','manager','order_agent','catalog_editor'], true)) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
        $s = $pdo->prepare("SELECT * FROM users WHERE email=? AND is_active=1 AND role_slug<>'customer' LIMIT 1");
        $s->execute([trim($_POST['email'] ?? '')]);
        $u = $s->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$u['id'];
            $_SESSION['role']     = $u['role_slug'];
            redirect('admin/');
        }
        $err = 'Invalid admin credentials.';
    }
    // ── Login page (uses public header) ─────────────────────────────────────
    $title = 'Admin Login | '.APP_NAME;
    include __DIR__.'/../includes/header.php';
    ?>
    <section class="container auth">
      <div class="auth-card">
        <span class="eyebrow">SECURE ADMIN</span>
        <h1>Admin login</h1>
        <p>Role-based access to <?= e(APP_NAME) ?> management.</p>
        <?php if ($err): ?>
          <div class="alert"><?= e($err) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <label>Email
            <input type="email" name="email" required autocomplete="username">
          </label>
          <label>Password
            <input type="password" name="password" required autocomplete="current-password">
          </label>
          <button class="btn btn-primary full">Sign in</button>
        </form>
      </div>
    </section>
    <?php
    include __DIR__.'/../includes/footer.php';
    exit;
}

// ── Dashboard ────────────────────────────────────────────────────────────────
$stats = [
    'sales'     => $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid' OR payment_method='cod'")->fetchColumn(),
    'orders'    => $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role_slug='customer'")->fetchColumn(),
    'products'  => $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn(),
];
$recent = $pdo->query(
    "SELECT o.order_no, o.total, o.status, o.created_at, u.name user_name
     FROM orders o LEFT JOIN users u ON u.id=o.user_id
     ORDER BY o.id DESC LIMIT 6"
)->fetchAll();

$adminTitle   = 'Dashboard';
$adminSection = 'dashboard';
include __DIR__.'/../includes/admin-header.php';
?>

<!-- Stat cards -->
<div class="ap-stats">
  <div class="ap-stat">
    <div class="ap-stat-icon green">💰</div>
    <div class="ap-stat-label">Total Sales</div>
    <div class="ap-stat-value">₹<?= number_format((float)$stats['sales']) ?></div>
    <div class="ap-stat-sub">paid &amp; COD orders</div>
  </div>
  <div class="ap-stat">
    <div class="ap-stat-icon amber">📦</div>
    <div class="ap-stat-label">Orders</div>
    <div class="ap-stat-value"><?= number_format((int)$stats['orders']) ?></div>
    <div class="ap-stat-sub">all time</div>
  </div>
  <div class="ap-stat">
    <div class="ap-stat-icon blue">👥</div>
    <div class="ap-stat-label">Customers</div>
    <div class="ap-stat-value"><?= number_format((int)$stats['customers']) ?></div>
    <div class="ap-stat-sub">registered accounts</div>
  </div>
  <div class="ap-stat">
    <div class="ap-stat-icon rose">🥬</div>
    <div class="ap-stat-label">Active Products</div>
    <div class="ap-stat-value"><?= number_format((int)$stats['products']) ?></div>
    <div class="ap-stat-sub">in catalogue</div>
  </div>
</div>

<!-- Quick-access modules -->
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Quick access</h2>
  </div>
  <div class="ap-card-body">
    <div class="ap-module-grid">
      <?php if (user_can($pdo,'orders')): ?>
      <a class="ap-module" href="orders.php">
        <div class="ap-module-icon">📦</div>
        <div class="ap-module-text"><b>Orders</b><span>Order status &amp; fulfilment</span></div>
      </a>
      <?php endif; ?>
      <?php if (user_can($pdo,'catalog')): ?>
      <a class="ap-module" href="products.php">
        <div class="ap-module-icon">🥬</div>
        <div class="ap-module-text"><b>Products</b><span>Catalogue, variants &amp; search terms</span></div>
      </a>
      <a class="ap-module" href="categories.php">
        <div class="ap-module-icon">🗂️</div>
        <div class="ap-module-text"><b>Categories</b><span>Manage product categories</span></div>
      </a>
      <a class="ap-module" href="inventory.php">
        <div class="ap-module-icon">📊</div>
        <div class="ap-module-text"><b>Inventory</b><span>Adjust stock levels</span></div>
      </a>
      <?php endif; ?>
      <?php if (user_can($pdo,'content')): ?>
      <a class="ap-module" href="content.php">
        <div class="ap-module-icon">🖼️</div>
        <div class="ap-module-text"><b>Content</b><span>Banners, popup &amp; pages</span></div>
      </a>
      <?php endif; ?>
      <?php if (user_can($pdo,'settings')): ?>
      <a class="ap-module" href="settings.php">
        <div class="ap-module-icon">⚙️</div>
        <div class="ap-module-text"><b>Settings</b><span>Points, referrals &amp; SEO</span></div>
      </a>
      <a class="ap-module" href="coupons.php">
        <div class="ap-module-icon">🎟️</div>
        <div class="ap-module-text"><b>Coupons</b><span>Coupon codes for checkout</span></div>
      </a>
      <?php endif; ?>
      <?php if (user_can($pdo,'users')): ?>
      <a class="ap-module" href="users.php">
        <div class="ap-module-icon">👥</div>
        <div class="ap-module-text"><b>Users</b><span>Roles &amp; customer access</span></div>
      </a>
      <?php endif; ?>
      <a class="ap-module" href="<?= url() ?>" target="_blank">
        <div class="ap-module-icon">🌿</div>
        <div class="ap-module-text"><b>Storefront</b><span>Open customer website</span></div>
      </a>
    </div>
  </div>
</div>

<!-- Recent orders -->
<?php if ($recent && user_can($pdo,'orders')): ?>
<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Recent orders</h2>
    <a class="ap-btn ap-btn-sm" href="orders.php">View all</a>
  </div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap">
      <table class="ap-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td><b><?= e($r['order_no']) ?></b></td>
            <td><?= e($r['user_name'] ?? 'Guest') ?></td>
            <td>₹<?= number_format((float)$r['total'], 2) ?></td>
            <td>
              <?php
              $sc = match($r['status']) {
                'delivered'        => 'ap-badge-green',
                'placed','confirmed','packed' => 'ap-badge-blue',
                'out_for_delivery' => 'ap-badge-amber',
                'cancelled','returned' => 'ap-badge-red',
                default            => 'ap-badge-gray',
              };
              ?>
              <span class="ap-badge <?= $sc ?>"><?= e($r['status']) ?></span>
            </td>
            <td><small><?= e($r['created_at']) ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
