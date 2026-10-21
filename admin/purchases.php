<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'catalog')) redirect('admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $date       = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $invoice    = trim($_POST['invoice_no'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    $pids   = $_POST['product_id'] ?? [];
    $qtys   = $_POST['qty'] ?? [];
    $units  = $_POST['unit'] ?? [];
    $costs  = $_POST['cost_price'] ?? [];

    // Build valid line items.
    $lines = [];
    foreach ($pids as $i => $pid) {
        $pid  = (int)$pid;
        $qty  = (float)($qtys[$i] ?? 0);
        $cost = (float)($costs[$i] ?? 0);
        $unit = trim($units[$i] ?? 'kg') ?: 'kg';
        if ($pid > 0 && $qty > 0) {
            $lines[] = ['pid'=>$pid,'qty'=>$qty,'unit'=>$unit,'cost'=>$cost,'total'=>round($qty*$cost,2)];
        }
    }

    if (!$lines) {
        flash('error', 'Add at least one product line with quantity.');
        redirect('admin/purchases.php');
    }

    $pdo->beginTransaction();
    try {
        $total = array_sum(array_column($lines, 'total'));
        $ins = $pdo->prepare('INSERT INTO purchases(supplier_id,purchase_date,invoice_no,notes,total_cost,created_by) VALUES(?,?,?,?,?,?)');
        $ins->execute([$supplierId, $date, $invoice?:null, $notes?:null, $total, user_id()]);
        $purchaseId = (int)$pdo->lastInsertId();

        $li   = $pdo->prepare('INSERT INTO purchase_items(purchase_id,product_id,product_name,qty,unit,cost_price,line_total,purchase_date) VALUES(?,?,?,?,?,?,?,?)');
        $pname= $pdo->prepare('SELECT name FROM products WHERE id=?');
        $upd  = $pdo->prepare('UPDATE products SET stock=stock+?, cost_price=? WHERE id=?');
        foreach ($lines as $l) {
            $pname->execute([$l['pid']]);
            $nm = (string)($pname->fetchColumn() ?: 'Product #'.$l['pid']);
            $li->execute([$purchaseId, $l['pid'], $nm, $l['qty'], $l['unit'], $l['cost'], $l['total'], $date]);
            // Auto-add stock + update latest cost price.
            $upd->execute([$l['qty'], $l['cost'], $l['pid']]);
        }
        $pdo->commit();
        flash('success', 'Purchase saved: '.count($lines).' item(s), total ₹'.number_format($total,2).'. Stock updated.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Could not save purchase: '.$e->getMessage());
    }
    redirect('admin/purchases.php');
}

$suppliers = $pdo->query('SELECT id,name,type FROM suppliers WHERE is_active=1 ORDER BY name')->fetchAll();
$products  = $pdo->query('SELECT id,name,cost_price FROM products WHERE is_active=1 ORDER BY name')->fetchAll();
$recent    = $pdo->query('SELECT pu.*, s.name supplier_name FROM purchases pu LEFT JOIN suppliers s ON s.id=pu.supplier_id ORDER BY pu.id DESC LIMIT 30')->fetchAll();

// Preload line items for the recent purchases (to show product details).
$itemsByPurchase = [];
if ($recent) {
    $ids = array_column($recent, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $its = $pdo->prepare('SELECT * FROM purchase_items WHERE purchase_id IN('.$in.') ORDER BY id');
    $its->execute($ids);
    foreach ($its->fetchAll() as $it) { $itemsByPurchase[$it['purchase_id']][] = $it; }
}

$adminTitle='Purchases'; $adminSection='purchases'; $adminCrumbs=[['label'=>'Purchases']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Record a purchase</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Log stock bought from farmers/vendors/market with today's cost price. Saving adds the quantity to stock and updates each product's latest cost price.</p>
    <form class="ap-form" method="post" id="purForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">Supplier</label>
          <select class="ap-select" name="supplier_id">
            <option value="">— Select supplier —</option>
            <?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=e($s['name'])?> (<?=e($s['type'])?>)</option><?php endforeach; ?>
          </select>
          <span class="ap-hint"><a href="<?=url('admin/suppliers.php')?>">+ manage suppliers</a></span>
        </div>
        <div class="ap-field"><label class="ap-label">Purchase date *</label><input class="ap-input" type="date" name="purchase_date" value="<?=e(date('Y-m-d'))?>" required></div>
        <div class="ap-field"><label class="ap-label">Invoice / Bill no.</label><input class="ap-input" name="invoice_no"></div>
        <div class="ap-field ap-form-wide"><label class="ap-label">Notes</label><input class="ap-input" name="notes"></div>
      </div>

      <div class="ap-section-title">Items purchased</div>
      <div class="ap-table-wrap">
        <table class="ap-table" id="purItems">
          <thead><tr><th style="min-width:200px">Product</th><th>Qty</th><th>Unit</th><th>Cost price (₹/unit)</th><th>Line total</th><th></th></tr></thead>
          <tbody>
            <tr class="pur-row">
              <td>
                <select class="ap-select pur-product" name="product_id[]">
                  <option value="">— Select product —</option>
                  <?php foreach($products as $p): ?><option value="<?=$p['id']?>" data-cost="<?=e((string)$p['cost_price'])?>"><?=e($p['name'])?></option><?php endforeach; ?>
                </select>
              </td>
              <td><input class="ap-input pur-qty" name="qty[]" type="number" step="0.001" min="0" style="width:90px"></td>
              <td><input class="ap-input pur-unit" name="unit[]" value="kg" style="width:70px"></td>
              <td><input class="ap-input pur-cost" name="cost_price[]" type="number" step="0.01" min="0" style="width:120px"></td>
              <td class="pur-line">₹0.00</td>
              <td><button type="button" class="ap-btn ap-btn-sm ap-btn-danger pur-del">✕</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div style="margin:10px 0;"><button type="button" class="ap-btn ap-btn-sm" id="purAddRow">+ Add item</button>
        <span style="float:right;font-weight:800">Total: <span id="purGrand">₹0.00</span></span></div>
      <div><button class="ap-btn ap-btn-primary">💾 Save purchase &amp; update stock</button></div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Recent purchases</h2>
    <a class="ap-btn ap-btn-sm" href="<?=url('admin/inventory-report.php')?>">📊 Daily report</a></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap"><table class="ap-table">
      <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th>Products purchased</th><th>Total</th></tr></thead>
      <tbody>
        <?php if(!$recent): ?><tr><td colspan="5"><span class="ap-hint" style="padding:12px;display:block">No purchases recorded yet.</span></td></tr><?php endif; ?>
        <?php foreach($recent as $r): $items=$itemsByPurchase[$r['id']] ?? []; ?>
          <tr>
            <td><?= e($r['purchase_date']) ?></td>
            <td><?= e($r['supplier_name'] ?? '—') ?></td>
            <td><?= e($r['invoice_no'] ?? '—') ?></td>
            <td>
              <?php if($items): ?>
                <div class="pur-items-list">
                <?php foreach($items as $it): ?>
                  <div class="pur-item-line">
                    <span class="pur-item-name"><?= e($it['product_name']) ?></span>
                    <span class="pur-item-meta"><?= rtrim(rtrim(number_format((float)$it['qty'],3),'0'),'.') ?> <?= e($it['unit']) ?> × ₹<?= number_format((float)$it['cost_price'],2) ?> = <b>₹<?= number_format((float)$it['line_total'],2) ?></b></span>
                  </div>
                <?php endforeach; ?>
                </div>
              <?php else: ?><span class="ap-hint">—</span><?php endif; ?>
            </td>
            <td><b>₹<?= number_format((float)$r['total_cost'],2) ?></b></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>

<script>
(function(){
  var tbody=document.querySelector('#purItems tbody');
  function recalc(){
    var grand=0;
    tbody.querySelectorAll('.pur-row').forEach(function(r){
      var q=parseFloat(r.querySelector('.pur-qty').value||0), c=parseFloat(r.querySelector('.pur-cost').value||0);
      var lt=(q*c)||0; grand+=lt; r.querySelector('.pur-line').textContent='₹'+lt.toFixed(2);
    });
    document.getElementById('purGrand').textContent='₹'+grand.toFixed(2);
  }
  tbody.addEventListener('input',recalc);
  tbody.addEventListener('change',function(e){
    if(e.target.classList.contains('pur-product')){
      var opt=e.target.selectedOptions[0], cost=opt&&opt.dataset.cost;
      var row=e.target.closest('.pur-row'), cEl=row.querySelector('.pur-cost');
      if(cost && !cEl.value) cEl.value=cost; // prefill last cost
      recalc();
    }
  });
  tbody.addEventListener('click',function(e){
    if(e.target.classList.contains('pur-del')){
      if(tbody.querySelectorAll('.pur-row').length>1) e.target.closest('.pur-row').remove(); recalc();
    }
  });
  document.getElementById('purAddRow').addEventListener('click',function(){
    var first=tbody.querySelector('.pur-row'), clone=first.cloneNode(true);
    clone.querySelectorAll('input').forEach(function(i){i.value=i.classList.contains('pur-unit')?'kg':'';});
    clone.querySelector('.pur-product').value=''; clone.querySelector('.pur-line').textContent='₹0.00';
    tbody.appendChild(clone);
  });
})();
</script>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
