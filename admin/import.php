<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

$uploadDir  = __DIR__.'/../uploads/products';
$publicDir  = 'uploads/products';
$tmpCsvPath = sys_get_temp_dir().'/aapki_import_'.(int)user_id().'.csv';

/* ── Helpers ──────────────────────────────────────────────── */
function parseVariant(string $label): array {
    $label = trim($label);
    $num = (float)preg_replace('/[^0-9.]/','',$label);
    $lc = strtolower($label); $unit='pc';
    if (str_contains($lc,'kg')) $unit='kg';
    elseif (str_contains($lc,'ml')) $unit='ml';
    elseif (str_contains($lc,'ltr')||str_contains($lc,'litre')||str_contains($lc,'l')) $unit='l';
    elseif (str_contains($lc,'gm')||str_contains($lc,'gms')||str_contains($lc,'g')) $unit='g';
    elseif (str_contains($lc,'pcs')||str_contains($lc,'pc')||str_contains($lc,'pkt')) $unit='pc';
    return ['label'=>$label,'unit'=>$unit,'qty'=>$num?:1];
}
function unique_slug(PDO $pdo, string $name): string {
    $base = function_exists('slugify') ? slugify($name) : trim(preg_replace('/[^a-z0-9]+/i','-',strtolower($name)),'-');
    $slug=$base; $n=1; $chk=$pdo->prepare('SELECT COUNT(*) FROM products WHERE slug=?');
    while(true){$chk->execute([$slug]); if((int)$chk->fetchColumn()===0) break; $slug=$base.'-'.$n; $n++;}
    return $slug;
}
/** Fetch an image from a URL and save as <slug>.webp. Returns public path or null. */
function fetch_image(string $url, string $slug, string $uploadDir, string $publicDir): ?string {
    $url = trim($url);
    if ($url==='' || !preg_match('~^https?://~i',$url)) return null;
    if (!function_exists('curl_init')) return null;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_MAXREDIRS=>3,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_USERAGENT=>'AapkiGrocery-Importer/1.0']);
    $data=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $ctype=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($code!==200 || !$data || strlen($data)>8*1024*1024) return null;
    if (stripos($ctype,'image/')!==0) return null;
    $img=@imagecreatefromstring($data);
    if(!$img) return null;
    if(!is_dir($uploadDir)) mkdir($uploadDir,0775,true);
    imagepalettetotruecolor($img); imagealphablending($img,false); imagesavealpha($img,true);
    $path=$uploadDir.'/'.$slug.'.webp';
    imagewebp($img,$path,90); imagedestroy($img);
    return $publicDir.'/'.$slug.'.webp';
}

$err=''; $stage='upload'; $rows=[]; $previewRows=[];

/* ── Step 1: receive uploaded CSV, stash to temp, go to preview ── */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_upload']) && verify_csrf($_POST['csrf']??null)) {
    if (empty($_FILES['csv']['tmp_name']) || ($_FILES['csv']['error']??1)!==UPLOAD_ERR_OK) {
        $err='Please choose a CSV file to upload.';
    } else {
        $ext=strtolower(pathinfo($_FILES['csv']['name'],PATHINFO_EXTENSION));
        if ($ext!=='csv') { $err='Only .csv files are allowed.'; }
        else { move_uploaded_file($_FILES['csv']['tmp_name'],$tmpCsvPath); $stage='preview'; }
    }
}
/* ── Step 3: confirm import from the stashed temp CSV ── */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_import']) && verify_csrf($_POST['csrf']??null)) {
    $stage='done';
}

/* ── Load category map ── */
$cats=[]; foreach($pdo->query('SELECT id,slug,name FROM categories ORDER BY name') as $c){$cats[$c['slug']]=$c;}

/* ── Read the stashed CSV for preview/import ── */
$imported=0; $skipped=0; $results=[]; $errors=[];
if (($stage==='preview'||$stage==='done') && is_file($tmpCsvPath)) {
    $fh=fopen($tmpCsvPath,'r');
    $header=fgetcsv($fh); // header row: name,category_slug,sku,description,search_terms,image,image_url,mrp,price,stock,variants
    $line=0;
    if ($stage==='done') $pdo->beginTransaction();
    try {
        while(($r=fgetcsv($fh))!==false){
            if (count(array_filter($r, fn($v)=>trim((string)$v)!==''))===0) continue;
            $line++;
            $r=array_pad($r,11,'');
            [$name,$catSlug,$sku,$desc,$search,$image,$imageUrl,$mrp,$price,$stock,$variantStr]=$r;
            $name=trim($name); $catSlug=trim($catSlug);
            if($name===''){$errors[]="Row $line: empty name"; $skipped++; continue;}
            if(!isset($cats[$catSlug])){$errors[]="Row $line ($name): category '$catSlug' not found"; $skipped++; continue;}
            $slug = unique_slug($pdo,$name);
            $sku  = trim($sku) ?: 'AG-'.strtoupper(bin2hex(random_bytes(3)));
            $image=trim($image); $imageUrl=trim($imageUrl);

            if ($stage==='preview') {
                $results[]=['name'=>$name,'cat'=>$catSlug,'slug'=>$slug,'price'=>$price,'img'=>$imageUrl?:($image?:'(slug.webp)')];
                continue;
            }
            // ── actual import ──
            $finalImage = $image ?: ('uploads/products/'.$slug.'.webp');
            if ($imageUrl!=='') { $fetched=fetch_image($imageUrl,$slug,$GLOBALS['uploadDir'],$GLOBALS['publicDir']); if($fetched) $finalImage=$fetched; }
            $catId=(int)$cats[$catSlug]['id'];
            $pdo->prepare('INSERT INTO products(category_id,name,slug,sku,description,search_terms,image,image_webp,mrp,price,stock,low_stock_threshold,is_featured,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,5,0,1)')
                ->execute([$catId,$name,$slug,$sku,trim($desc),trim($search),$finalImage,$finalImage,(float)$mrp,(float)$price,(float)$stock]);
            $pid=(int)$pdo->lastInsertId();
            // variants
            if (trim($variantStr)) {
                $parts=explode('|',$variantStr); $per=(float)$stock/max(1,count($parts));
                foreach($parts as $i=>$vl){$v=parseVariant($vl);
                    $pdo->prepare('INSERT INTO product_variants(product_id,label,unit_type,quantity,mrp,price,stock,is_default) VALUES(?,?,?,?,?,?,?,?)')
                        ->execute([$pid,$v['label'],$v['unit'],$v['qty'],(float)$mrp,(float)$price,$per,$i===0?1:0]);}
            }
            foreach(preg_split('/[\n,]+/',trim($search)) as $t){$t=trim($t); if($t) $pdo->prepare('INSERT IGNORE INTO product_synonyms(product_id,term) VALUES(?,?)')->execute([$pid,$t]);}
            $results[]=['name'=>$name,'slug'=>$slug,'pid'=>$pid]; $imported++;
        }
        if ($stage==='done') $pdo->commit();
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $err=$e->getMessage(); }
    fclose($fh);
    if ($stage==='done') @unlink($tmpCsvPath);
}

$adminTitle='Import Products'; $adminSection='import'; $adminCrumbs=[['label'=>'Import Products']];
include __DIR__.'/../includes/admin-header.php';
?>
<?php if($err): ?><div class="ap-alert"><?= e($err) ?></div><?php endif; ?>

<?php if($stage==='upload'): ?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Upload a CSV</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Upload a product CSV, preview it, then import with one click. Slugs are generated automatically (clean, with <code>-1/-2</code> only on duplicates).</p>
    <form method="post" enctype="multipart/form-data" class="ap-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="do_upload" value="1">
      <div class="ap-field"><label class="ap-label">CSV file *</label><input class="ap-input" type="file" name="csv" accept=".csv" required></div>
      <div><button class="ap-btn ap-btn-primary">⬆ Upload &amp; preview</button>
      <a class="ap-btn" href="<?= url('tools/catalog-import-template.csv') ?>" download>⬇ Download CSV template</a></div>
    </form>
    <div class="ap-hint" style="margin-top:16px;line-height:1.7">
      <b>Columns:</b> <code>name, category_slug, sku, description, search_terms, image, image_url, mrp, price, stock, variants</code><br>
      • <b>image</b>: path to an already-uploaded file (e.g. <code>uploads/products/potato.webp</code>) — leave blank to default to <code>uploads/products/&lt;slug&gt;.webp</code>.<br>
      • <b>image_url</b>: optional. If set, the image is fetched and saved as <code>&lt;slug&gt;.webp</code>. <b>Only use URLs you are licensed to use</b> (your own media, suppliers, or openly-licensed sources). Do not use images copied from other stores.<br>
      • <b>variants</b>: pipe-separated, e.g. <code>500 GM|1 KG|2 KG</code>.
    </div>
  </div>
</div>

<?php elseif($stage==='preview'): ?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Preview — <?= count($results) ?> ready, <?= $skipped ?> skipped</h2></div>
  <div class="ap-card-body no-pad">
    <?php if($errors): ?><div class="ap-alert" style="margin:14px"><?php foreach($errors as $e2): ?><div>⚠️ <?= e($e2) ?></div><?php endforeach; ?></div><?php endif; ?>
    <div class="ap-table-wrap"><table class="ap-table">
      <thead><tr><th>#</th><th>Name</th><th>Category</th><th>Slug</th><th>Price</th><th>Image</th></tr></thead>
      <tbody>
      <?php foreach($results as $i=>$r): ?>
        <tr><td><?= $i+1 ?></td><td><b><?= e($r['name']) ?></b></td><td><?= e($r['cat']) ?></td><td><code><?= e($r['slug']) ?></code></td><td>₹<?= e($r['price']) ?></td><td><small><?= e($r['img']) ?></small></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="ap-card-body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="do_import" value="1">
      <button class="ap-btn ap-btn-primary" onclick="return confirm('Import <?= count($results) ?> products now?')">🚀 Import <?= count($results) ?> products</button>
      <a class="ap-btn" href="<?= url('admin/import.php') ?>">✕ Cancel</a>
    </form>
  </div>
</div>

<?php else: /* done */ ?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">✅ Import complete — <?= $imported ?> added, <?= $skipped ?> skipped</h2></div>
  <div class="ap-card-body">
    <?php if($errors): ?><div class="ap-alert"><?php foreach($errors as $e2): ?><div>⚠️ <?= e($e2) ?></div><?php endforeach; ?></div><?php endif; ?>
    <div style="display:flex;gap:10px;">
      <a class="ap-btn ap-btn-primary" href="<?= url('admin/products.php') ?>">View products</a>
      <a class="ap-btn" href="<?= url('admin/import.php') ?>">Import another CSV</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
