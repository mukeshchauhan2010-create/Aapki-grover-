<?php
/**
 * Admin layout header — include at the top of every admin page.
 *
 * Expects the calling page to have already run:
 *   require_once __DIR__.'/../config/db.php';
 *   $pdo = db();
 *   (plus any access check before including this file)
 *
 * Optional variables the page can set before including:
 *   $adminTitle   — page <title> string  (e.g. "Products")
 *   $adminSection — active nav key       (e.g. "products")
 *   $adminCrumbs  — array of ['label'=>…,'url'=>…] breadcrumb items
 */
$adminTitle   = $adminTitle   ?? 'Admin';
$adminSection = $adminSection ?? '';
$adminCrumbs  = $adminCrumbs  ?? [];

// Fetch current user info for the top-bar pill
$_au = null;
if (is_logged_in()) {
    $_s = $pdo->prepare('SELECT first_name, last_name, role_slug FROM users WHERE id = ? LIMIT 1');
    $_s->execute([user_id()]);
    $_au = $_s->fetch();
}
$_auName  = trim(($_au['first_name'] ?? '') . ' ' . ($_au['last_name'] ?? '')) ?: 'Admin';
$_auRole  = ucwords(str_replace('_', ' ', $_au['role_slug'] ?? 'admin'));
$_auInit  = strtoupper(mb_substr($_auName, 0, 1));

// Nav items: [section-key, icon, label, url, permission]
$_navItems = [
    ['dashboard', '🏠', 'Dashboard',  url('admin/'),                 null],
    ['orders',    '📦', 'Orders',     url('admin/orders.php'),       'orders'],
    ['products',  '🥬', 'Products',   url('admin/products.php'),     'catalog'],
    ['categories','🗂️',  'Categories', url('admin/categories.php'),  'catalog'],
    ['inventory', '📊', 'Inventory',  url('admin/inventory.php'),    'catalog'],
    ['coupons',   '🎟️',  'Coupons',   url('admin/coupons.php'),      'settings'],
    ['content',   '🖼️',  'Content',   url('admin/content.php'),      'content'],
    ['pages',     '📄', 'Pages',      url('admin/pages.php'),        'content'],
    ['faqs',      '❓', 'FAQs',       url('admin/faqs.php'),         'content'],
    ['users',     '👥', 'Users',      url('admin/users.php'),        'users'],
    ['settings',  '⚙️',  'Settings',  url('admin/settings.php'),    'settings'],
];

$_flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($adminTitle) ?> | <?= e(APP_NAME) ?> Admin</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="icon" href="<?= url('assets/favicon.svg') ?>" type="image/svg+xml">
  <link rel="icon" type="image/png" sizes="512x512" href="<?= url('assets/favicon-512.png') ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= url('assets/favicon-192.png') ?>">
  <link rel="icon" type="image/png" sizes="64x64"   href="<?= url('assets/favicon-64.png') ?>">
  <link rel="icon" type="image/png" sizes="32x32"   href="<?= url('assets/favicon-32.png') ?>">
  <link rel="icon" type="image/png" sizes="16x16"   href="<?= url('assets/favicon-16.png') ?>">
  <link rel="apple-touch-icon" sizes="180x180"      href="<?= url('assets/favicon-180.png') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= time() ?>">
</head>
<body class="admin-body">

<!-- Mobile overlay -->
<div class="admin-sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-shell">

  <!-- ── Sidebar ─────────────────────────────────────── -->
  <aside class="admin-sidebar" id="adminSidebar">

    <div class="admin-brand">
      <img src="<?= url('assets/logo.png') ?>" alt="<?= e(APP_NAME) ?>">
    </div>

    <nav class="admin-nav">
      <div class="admin-nav-section">Main</div>
      <?php foreach ($_navItems as [$key, $icon, $label, $href, $perm]): ?>
        <?php if ($perm && !user_can($pdo, $perm)) continue; ?>
        <a href="<?= e($href) ?>" class="<?= $adminSection === $key ? 'active' : '' ?>">
          <span class="nav-icon"><?= $icon ?></span>
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar-footer">
      <a href="<?= url() ?>" target="_blank">
        <span class="nav-icon">🌿</span> View storefront
      </a>
      <a href="<?= url('admin/logout.php') ?>">
        <span class="nav-icon">↪</span> Sign out
      </a>
    </div>

  </aside><!-- /sidebar -->

  <!-- ── Main ────────────────────────────────────────── -->
  <div class="admin-main">

    <!-- Top bar -->
    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="admin-menu-toggle" id="menuToggle" aria-label="Open menu">☰</button>
        <?php if ($adminCrumbs): ?>
          <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('admin/') ?>">Dashboard</a>
            <?php foreach ($adminCrumbs as $_crumb): ?>
              <span class="sep">/</span>
              <?php if (!empty($_crumb['url'])): ?>
                <a href="<?= e($_crumb['url']) ?>"><?= e($_crumb['label']) ?></a>
              <?php else: ?>
                <span class="current"><?= e($_crumb['label']) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </nav>
        <?php else: ?>
          <nav class="admin-breadcrumb">
            <span class="current"><?= e($adminTitle) ?></span>
          </nav>
        <?php endif; ?>
      </div>

      <div class="admin-topbar-right">
        <div class="admin-user-pill">
          <div class="admin-avatar"><?= e($_auInit) ?></div>
          <span><?= e($_auName) ?> &middot; <small><?= e($_auRole) ?></small></span>
        </div>
        <a href="<?= url('admin/logout.php') ?>">Sign out</a>
      </div>
    </header>

    <!-- Flash message -->
    <?php if ($_flash): ?>
      <div style="padding:14px 28px 0;">
        <div class="ap-alert <?= $_flash[0] === 'success' ? 'ap-alert-success' : '' ?>">
          <?= e($_flash[1]) ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Page content -->
    <div class="admin-content">
