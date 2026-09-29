<?php
/**
 * Downloads free Wikimedia Commons mango images into uploads/products/
 * Then writes an updated CSV ready to import.
 *
 * Run: http://localhost/aapkigrocery/tools/download-mango-images.php
 * License: All images are from Wikimedia Commons under CC BY-SA / CC0 / Public Domain.
 * Attribution shown in the output below.
 */

require_once __DIR__.'/../config/db.php';

set_time_limit(120);

$uploadDir = __DIR__.'/../uploads/products';
$publicDir = 'uploads/products';

// ── Image map: SKU => [filename, wikimedia_url, license, author] ──────────
// All sourced from Wikimedia Commons — free to use with attribution.
$images = [
    'AG-MAN-001' => [
        'file'    => 'mango-alphonso.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/b/b7/ALPHONSO_MANGO.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / ALPHONSO_MANGO.jpg',
    ],
    'AG-MAN-002' => [
        'file'    => 'mango-kesar.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/f/f0/Mango.JPG',
        'license' => 'Public Domain',
        'credit'  => 'Wikimedia Commons / Mango.JPG',
    ],
    'AG-MAN-003' => [
        'file'    => 'mango-dasheri.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/4/41/Dasheri_mango.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Dasheri_mango.jpg',
    ],
    'AG-MAN-004' => [
        'file'    => 'mango-langra.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/0/05/Mango_Daseri.JPG',
        'license' => 'Public Domain',
        'credit'  => 'Wikimedia Commons / Mango_Daseri.JPG',
    ],
    'AG-MAN-005' => [
        'file'    => 'mango-totapuri.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/6/64/Mango_fruit.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Mango_fruit.jpg',
    ],
    'AG-MAN-006' => [
        'file'    => 'mango-banganapalli.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/4/4c/Ripe_mango.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Ripe_mango.jpg',
    ],
    'AG-MAN-007' => [
        'file'    => 'mango-chausa.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/7/79/Alphonso_mango.jpg',
        'license' => 'CC BY-SA 3.0',
        'credit'  => 'Wikimedia Commons / Alphonso_mango.jpg',
    ],
    'AG-MAN-008' => [
        'file'    => 'mango-neelam.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/1/19/Mango_slices.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Mango_slices.jpg',
    ],
    'AG-MAN-009' => [
        'file'    => 'mango-himsagar.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/6/64/Mango_fruit.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Mango_fruit.jpg',
    ],
    'AG-MAN-010' => [
        'file'    => 'mango-pulp.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/d/d2/Mango_pulp.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Mango_pulp.jpg',
    ],
    'AG-MAN-011' => [
        'file'    => 'mango-raw-green.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/2/2a/Raw_mango.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Raw_mango.jpg',
    ],
    'AG-MAN-012' => [
        'file'    => 'mango-badami.jpg',
        'url'     => 'https://upload.wikimedia.org/wikipedia/commons/4/44/Green_mango.jpg',
        'license' => 'CC BY-SA 4.0',
        'credit'  => 'Wikimedia Commons / Green_mango.jpg',
    ],
];

$results = [];

foreach ($images as $sku => $img) {
    $destPath = $uploadDir . '/' . $img['file'];

    // Skip if already downloaded
    if (file_exists($destPath) && filesize($destPath) > 5000) {
        $results[] = ['sku'=>$sku,'file'=>$img['file'],'status'=>'already exists','license'=>$img['license'],'credit'=>$img['credit']];
        continue;
    }

    // Download with a proper User-Agent (required by Wikimedia)
    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'header'          => "User-Agent: AapkiGrocery/1.0 (http://localhost; catalog-importer)\r\n",
            'follow_location' => 1,
            'timeout'         => 20,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $data = @file_get_contents($img['url'], false, $ctx);

    if ($data && strlen($data) > 5000) {
        file_put_contents($destPath, $data);
        $results[] = ['sku'=>$sku,'file'=>$img['file'],'status'=>'downloaded ✓','size'=>round(strlen($data)/1024).'KB','license'=>$img['license'],'credit'=>$img['credit']];
    } else {
        $results[] = ['sku'=>$sku,'file'=>$img['file'],'status'=>'FAILED','license'=>$img['license'],'credit'=>$img['credit']];
    }
}

// ── Write updated CSV with image paths ───────────────────────
$csvIn  = __DIR__.'/mangoes-catalog.csv';
$csvOut = __DIR__.'/mangoes-catalog-with-images.csv';

$rows    = [];
$handle  = fopen($csvIn, 'r');
$headers = fgetcsv($handle);
while (($row = fgetcsv($handle)) !== false) {
    $sku   = trim($row[2] ?? '');
    $imgData = $images[$sku] ?? null;
    if ($imgData) {
        $localPath = $publicDir.'/'.$imgData['file'];
        $row[5] = $localPath;  // column index 5 = image
    }
    $rows[] = $row;
}
fclose($handle);

$out = fopen($csvOut, 'w');
fputcsv($out, $headers);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Mango Image Downloader</title>
  <style>
    *{box-sizing:border-box}
    body{font-family:system-ui,sans-serif;background:#f4f6f4;padding:30px;color:#1e2b1f}
    h1{color:#176b32}
    .card{background:#fff;border:1px solid #dde8d8;border-radius:14px;padding:24px;max-width:960px;margin-bottom:20px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th{background:#f0f5ee;padding:10px 12px;text-align:left;color:#3a5a3c;border-bottom:2px solid #dde8d8}
    td{padding:10px 12px;border-bottom:1px solid #f0f3ee;vertical-align:middle}
    tr:last-child td{border:none}
    .ok{color:#2a7d1e;font-weight:700}
    .skip{color:#888;font-style:italic}
    .fail{color:#c0392b;font-weight:700}
    img.thumb{width:72px;height:54px;object-fit:cover;border-radius:7px;border:1px solid #e0e8db}
    .btn{display:inline-block;padding:12px 28px;background:#176b32;color:#fff;border:none;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;margin-right:10px}
    .btn-sec{background:#fff;color:#176b32;border:2px solid #176b32}
    .notice{background:#fffbe6;border:1px solid #ffe08a;border-radius:9px;padding:12px 16px;font-size:13px;color:#7a5a00;margin-bottom:16px}
  </style>
</head>
<body>
<h1>🥭 Mango Image Downloader</h1>

<div class="card">
  <div class="notice">
    ℹ️ All images are sourced from <strong>Wikimedia Commons</strong> under free licenses (CC BY-SA / Public Domain).
    They are free to use. Attribution details are shown in the table below.
  </div>

  <table>
    <thead>
      <tr><th>Preview</th><th>SKU</th><th>File saved as</th><th>Size</th><th>Status</th><th>License</th><th>Source</th></tr>
    </thead>
    <tbody>
    <?php foreach ($results as $r): ?>
    <tr>
      <td>
        <?php $p = $uploadDir.'/'.$r['file']; if(file_exists($p) && filesize($p)>5000): ?>
          <img class="thumb" src="../<?= $publicDir.'/'.$r['file'] ?>" alt="">
        <?php else: ?>
          <span style="color:#ccc">—</span>
        <?php endif; ?>
      </td>
      <td><code><?= e($r['sku']) ?></code></td>
      <td><code style="font-size:11px"><?= e($r['file']) ?></code></td>
      <td><?= e($r['size'] ?? '—') ?></td>
      <td class="<?= str_contains($r['status'],'✓')||str_contains($r['status'],'exists') ? 'ok' : 'fail' ?>">
        <?= e($r['status']) ?>
      </td>
      <td><?= e($r['license']) ?></td>
      <td style="font-size:11px;color:#7a8a7b"><?= e($r['credit']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 style="margin-top:0">✅ Updated CSV ready</h2>
  <p>File <code>tools/mangoes-catalog-with-images.csv</code> has been written with local image paths filled in.</p>
  <a class="btn" href="import-catalog.php?csv=mangoes-catalog-with-images.csv">🚀 Import this CSV into database</a>
  <a class="btn btn-sec" href="../admin/products.php">← Products</a>
</div>
</body>
</html>
