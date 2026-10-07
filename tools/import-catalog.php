<?php
/**
 * Catalog CSV Importer
 * Open in browser: http://localhost/aapkigrocery/tools/import-catalog.php
 *
 * CSV columns:
 *   name, category_slug, sku, description, search_terms,
 *   image, mrp, price, stock, variants
 *
 * variants = pipe-separated labels e.g. "500 GM|1 KG|2 KG"
 *   - unit auto-detected from label (g/kg/pc/ml/l)
 *   - quantity auto-parsed from label number
 */

require_once __DIR__.'/../config/db.php';
$pdo = db();

// ── Auth: only admin can run this ─────────────────────────────
if (!is_logged_in() || !in_array($_SESSION['role'] ?? '', ['super_admin','manager','catalog_editor'], true)) {
    die('<p style="font-family:sans-serif;color:red;padding:30px">❌ Admin login required. <a href="/aapkigrocery/admin/">Login here</a></p>');
}

$csvName = basename($_GET['csv'] ?? 'mangoes-catalog.csv');
$csvFile = __DIR__.'/'.$csvName;
if (!file_exists($csvFile)) {
    die('<p style="font-family:sans-serif;color:red;padding:30px">❌ CSV file not found: '.$csvFile.'</p>');
}

// ── Helper: parse variant label → unit + qty ──────────────────
function parseVariant(string $label): array {
    $label = trim($label);
    $num   = (float) preg_replace('/[^0-9.]/', '', $label);
    $unit  = 'pc';
    $lc    = strtolower($label);
    if (str_contains($lc,'kg'))       $unit = 'kg';
    elseif (str_contains($lc,'gm') || str_contains($lc,'gms') || str_contains($lc,'g')) $unit = 'g';
    elseif (str_contains($lc,'ml'))   $unit = 'ml';
    elseif (str_contains($lc,'ltr') || str_contains($lc,'litre') || str_contains($lc,'l')) $unit = 'l';
    elseif (str_contains($lc,'pcs') || str_contains($lc,'pc') || str_contains($lc,'pkt')) $unit = 'pc';
    return ['label' => $label, 'unit' => $unit, 'qty' => $num ?: 1];
}

$dryRun  = !isset($_POST['confirm']);
$results = [];
$errors  = [];
$total   = 0;
$skipped = 0;
$imported= 0;

if (!$dryRun || isset($_POST['preview'])) {

    // Load categories
    $cats = [];
    foreach ($pdo->query('SELECT id, slug FROM categories') as $c) {
        $cats[$c['slug']] = (int)$c['id'];
    }

    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle); // skip header row

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 9) continue;
        [$name, $cat_slug, $sku, $desc, $search, $image, $mrp, $price, $stock, $variantStr] = array_pad($row, 10, '');

        $name  = trim($name);
        $sku   = trim($sku)   ?: 'AG-'.strtoupper(bin2hex(random_bytes(3)));
        $image = trim($image) ?: null;
        $total++;

        if (!$name) { $errors[] = "Row $total: empty name — skipped."; $skipped++; continue; }

        $catId = $cats[$cat_slug] ?? null;
        if (!$catId) { $errors[] = "Row $total ($name): category '$cat_slug' not found — skipped."; $skipped++; continue; }

        // Clean, human-friendly slug — append -1,-2… only if it already exists.
        $base = function_exists('slugify') ? slugify($name) : trim(preg_replace('/[^a-z0-9]+/i','-',strtolower($name)),'-');
        $slug = $base; $sn = 1;
        $slchk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug=?');
        while (true) { $slchk->execute([$slug]); if ((int)$slchk->fetchColumn() === 0) break; $slug = $base.'-'.$sn; $sn++; }

        // If no image given, default to a file named after the slug in uploads/products.
        if (!$image) { $image = 'uploads/products/'.$slug.'.webp'; }

        // Check duplicate SKU
        $dup = $pdo->prepare('SELECT id FROM products WHERE sku=? LIMIT 1');
        $dup->execute([$sku]);
        if ($dup->fetchColumn()) {
            $results[] = ['status'=>'skip','name'=>$name,'sku'=>$sku,'msg'=>'SKU already exists'];
            $skipped++; continue;
        }

        // Parse variants
        $variants = [];
        if (trim($variantStr)) {
            foreach (explode('|', $variantStr) as $i => $vlabel) {
                $v = parseVariant($vlabel);
                $variants[] = [
                    'label'   => $v['label'],
                    'unit'    => $v['unit'],
                    'qty'     => $v['qty'],
                    'mrp'     => (float)$mrp,
                    'price'   => (float)$price,
                    'stock'   => (float)$stock / max(1, count(explode('|',$variantStr))),
                    'default' => $i === 0 ? 1 : 0,
                ];
            }
        }

        if (!$dryRun) {
            // Insert product
            $pdo->prepare(
                'INSERT INTO products
                 (category_id,name,slug,sku,description,search_terms,image,image_webp,
                  mrp,price,stock,low_stock_threshold,is_featured,is_active)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,5,0,1)'
            )->execute([
                $catId, $name, $slug, $sku,
                trim($desc), trim($search),
                $image, $image,
                (float)$mrp, (float)$price, (float)$stock,
            ]);
            $pid = (int)$pdo->lastInsertId();

            // Insert variants
            foreach ($variants as $v) {
                $pdo->prepare(
                    'INSERT INTO product_variants
                     (product_id,label,unit_type,quantity,mrp,price,stock,is_default)
                     VALUES(?,?,?,?,?,?,?,?)'
                )->execute([
                    $pid, $v['label'], $v['unit'], $v['qty'],
                    $v['mrp'], $v['price'], $v['stock'], $v['default'],
                ]);
            }

            // Synonyms
            foreach (preg_split('/[\n,]+/', trim($search)) as $term) {
                $term = trim($term);
                if ($term) $pdo->prepare('INSERT IGNORE INTO product_synonyms(product_id,term) VALUES(?,?)')->execute([$pid,$term]);
            }

            $results[] = ['status'=>'ok','name'=>$name,'sku'=>$sku,'variants'=>count($variants),'pid'=>$pid];
            $imported++;
        } else {
            $results[] = ['status'=>'preview','name'=>$name,'sku'=>$sku,'cat'=>$cat_slug,'price'=>$price,'variants'=>count($variants)];
        }
    }
    fclose($handle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Catalog Importer — Aapki Grocery</title>
  <style>
    *{box-sizing:border-box}
    body{font-family:system-ui,sans-serif;background:#f4f6f4;color:#1e2b1f;margin:0;padding:30px}
    h1{color:#176b32;margin-bottom:4px}
    .card{background:#fff;border:1px solid #e0e8db;border-radius:14px;padding:24px;margin-bottom:20px;max-width:900px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th{background:#f0f5ee;padding:10px 12px;text-align:left;border-bottom:2px solid #dde8d8;color:#3a5a3c}
    td{padding:9px 12px;border-bottom:1px solid #f0f3ee}
    tr:last-child td{border:none}
    .ok{color:#2a7d1e;font-weight:700}
    .skip{color:#9a6400}
    .err{color:#c0392b}
    .preview{color:#1a57d6}
    .btn{display:inline-block;padding:12px 28px;background:#176b32;color:#fff;border:none;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;margin-top:12px}
    .btn-sec{background:#fff;color:#176b32;border:1px solid #176b32}
    .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700}
    .badge-green{background:#e4f5db;color:#2a7d1e}
    .badge-amber{background:#fff4dc;color:#9a6400}
    .badge-blue{background:#e3eeff;color:#1a57d6}
    .stats{display:flex;gap:16px;flex-wrap:wrap;margin:16px 0}
    .stat{background:#f7faf5;border:1px solid #dde8d8;border-radius:10px;padding:12px 20px;text-align:center}
    .stat b{display:block;font-size:26px;color:#176b32}
    .stat small{color:#7a8a7b;font-size:12px}
    .error-box{background:#fff5f5;border:1px solid #f5c6c2;border-radius:9px;padding:12px 16px;margin:12px 0;font-size:13px;color:#c0392b}
  </style>
</head>
<body>
<h1>📦 Catalog Importer</h1>
<p style="color:#5a7060;margin-bottom:20px">File: <code><?= basename($csvFile) ?></code> &nbsp;|&nbsp; <?= $total ?> rows read</p>

<?php if ($dryRun && !isset($_POST['preview'])): ?>
<!-- ── Step 1: Preview ──────────────────────────────────── -->
<div class="card">
  <h2 style="margin-top:0">Step 1 — Preview CSV</h2>
  <p>Click Preview to see what will be imported before committing to the database.</p>
  <form method="post">
    <input type="hidden" name="preview" value="1">
    <button class="btn" type="submit">🔍 Preview CSV</button>
    <a class="btn btn-sec" href="<?= url('admin/products.php') ?>" style="margin-left:10px">← Back to Products</a>
  </form>
</div>

<?php elseif (isset($_POST['preview'])): ?>
<!-- ── Step 2: Preview results ─────────────────────────── -->
<div class="card">
  <h2 style="margin-top:0">Step 2 — Review before import</h2>

  <?php if ($errors): ?>
    <div class="error-box">
      <?php foreach($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= count($results) ?></b><small>Ready to import</small></div>
    <div class="stat"><b><?= $skipped ?></b><small>Will be skipped</small></div>
  </div>

  <table>
    <thead><tr><th>#</th><th>Product name</th><th>SKU</th><th>Category</th><th>Price (₹)</th><th>Variants</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($results as $i=>$r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><b><?= htmlspecialchars($r['name']) ?></b></td>
      <td><code style="font-size:11px"><?= htmlspecialchars($r['sku']) ?></code></td>
      <td><?= htmlspecialchars($r['cat'] ?? '—') ?></td>
      <td>₹<?= htmlspecialchars($r['price'] ?? '—') ?></td>
      <td><?= (int)($r['variants'] ?? 0) ?> packs</td>
      <td>
        <?php if($r['status']==='preview'): ?>
          <span class="badge badge-blue">✓ Ready</span>
        <?php elseif($r['status']==='skip'): ?>
          <span class="badge badge-amber">⚠ Skip: <?= htmlspecialchars($r['msg']) ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <form method="post" style="margin-top:20px;">
    <input type="hidden" name="confirm" value="1">
    <button class="btn" type="submit" onclick="return confirm('Import <?= count($results) ?> products into the database?')">
      🚀 Import <?= count($results) ?> products now
    </button>
    <a class="btn btn-sec" href="import-catalog.php" style="margin-left:10px">✕ Cancel</a>
  </form>
</div>

<?php else: ?>
<!-- ── Step 3: Import done ─────────────────────────────── -->
<div class="card">
  <h2 style="margin-top:0">✅ Import complete!</h2>

  <div class="stats">
    <div class="stat"><b style="color:#2a7d1e"><?= $imported ?></b><small>Imported</small></div>
    <div class="stat"><b style="color:#9a6400"><?= $skipped ?></b><small>Skipped</small></div>
    <div class="stat"><b><?= $total ?></b><small>Total rows</small></div>
  </div>

  <?php if ($errors): ?>
    <div class="error-box">
      <?php foreach($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <table>
    <thead><tr><th>#</th><th>Product</th><th>SKU</th><th>Variants</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($results as $i=>$r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td>
        <?php if(!empty($r['pid'])): ?>
          <a href="<?= url('admin/products.php?edit='.$r['pid']) ?>" style="color:#176b32;font-weight:700">
            <?= htmlspecialchars($r['name']) ?> ↗
          </a>
        <?php else: ?>
          <?= htmlspecialchars($r['name']) ?>
        <?php endif; ?>
      </td>
      <td><code style="font-size:11px"><?= htmlspecialchars($r['sku']) ?></code></td>
      <td><?= (int)($r['variants'] ?? 0) ?></td>
      <td>
        <?php if($r['status']==='ok'): ?>
          <span class="badge badge-green">✓ Imported</span>
        <?php elseif($r['status']==='skip'): ?>
          <span class="badge badge-amber">Skipped: <?= htmlspecialchars($r['msg'] ?? '') ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:20px;display:flex;gap:12px;">
    <a class="btn" href="<?= url('admin/products.php') ?>">← View Products</a>
    <a class="btn btn-sec" href="<?= url() ?>" target="_blank">🌿 View Storefront</a>
  </div>
</div>
<?php endif; ?>

</body>
</html>
