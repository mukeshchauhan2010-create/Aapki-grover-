<?php
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'catalog') && !user_can($pdo, 'orders')) redirect('admin/');

// Date range (default: today).
$from = trim($_GET['from'] ?? date('Y-m-d'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

/**
 * Build the product-wise report for the range:
 *  - Purchased qty + cost (purchase_items by purchase_date)
 *  - Sold qty + revenue (order_items joined to orders.created_at date)
 *  - Est. COGS = sold_qty * latest cost_price (latest-cost method)
 *  - Profit = revenue - COGS
 */
$rows = [];

// Purchases aggregated by product
$pq = $pdo->prepare(
    'SELECT pi.product_id, pi.product_name,
            SUM(pi.qty) AS bought_qty, SUM(pi.line_total) AS bought_cost
     FROM purchase_items pi
     WHERE pi.purchase_date BETWEEN ? AND ?
     GROUP BY pi.product_id, pi.product_name'
);
$pq->execute([$from, $to]);
foreach ($pq->fetchAll() as $r) {
    $rows[$r['product_id']] = [
        'name'=>$r['product_name'],
        'bought_qty'=>(float)$r['bought_qty'], 'bought_cost'=>(float)$r['bought_cost'],
        'sold_qty'=>0.0, 'revenue'=>0.0,
    ];
}

// Sales aggregated by product (exclude cancelled/returned orders)
$sq = $pdo->prepare(
    "SELECT oi.product_id, oi.product_name,
            SUM(oi.qty) AS sold_qty, SUM(oi.line_total) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id=oi.order_id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
       AND o.status NOT IN('cancelled','returned')
     GROUP BY oi.product_id, oi.product_name"
);
$sq->execute([$from, $to]);
foreach ($sq->fetchAll() as $r) {
    $pid = $r['product_id'];
    if (!isset($rows[$pid])) $rows[$pid] = ['name'=>$r['product_name'],'bought_qty'=>0.0,'bought_cost'=>0.0,'sold_qty'=>0.0,'revenue'=>0.0];
    $rows[$pid]['sold_qty'] = (float)$r['sold_qty'];
    $rows[$pid]['revenue']  = (float)$r['revenue'];
}

// Latest cost price + current stock per product
if ($rows) {
    $ids = array_keys($rows);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $cp  = $pdo->prepare('SELECT id, cost_price, stock FROM products WHERE id IN('.$in.')');
    $cp->execute($ids);
    foreach ($cp->fetchAll() as $r) {
        $rows[$r['id']]['cost_price'] = (float)$r['cost_price'];
        $rows[$r['id']]['stock']      = (float)$r['stock'];
    }
}

// Compute COGS + profit
$tot = ['bought_qty'=>0,'bought_cost'=>0,'sold_qty'=>0,'revenue'=>0,'cogs'=>0,'profit'=>0];
foreach ($rows as $pid=>&$r) {
    $r['cost_price'] = $r['cost_price'] ?? 0;
    $r['stock']      = $r['stock'] ?? 0;
    $r['cogs']       = round($r['sold_qty'] * $r['cost_price'], 2);
    $r['profit']     = round($r['revenue'] - $r['cogs'], 2);
    $tot['bought_qty'] += $r['bought_qty']; $tot['bought_cost'] += $r['bought_cost'];
    $tot['sold_qty']   += $r['sold_qty'];   $tot['revenue']     += $r['revenue'];
    $tot['cogs']       += $r['cogs'];       $tot['profit']      += $r['profit'];
}
unset($r);
uasort($rows, fn($a,$b)=>$b['profit']<=>$a['profit']);

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-report-'.$from.'_to_'.$to.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Product','Bought Qty','Bought Cost','Sold Qty','Revenue','Cost Price','COGS','Profit','Current Stock']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'],$r['bought_qty'],$r['bought_cost'],$r['sold_qty'],$r['revenue'],$r['cost_price'],$r['cogs'],$r['profit'],$r['stock']]);
    }
    fputcsv($out, ['TOTAL',$tot['bought_qty'],$tot['bought_cost'],$tot['sold_qty'],$tot['revenue'],'',$tot['cogs'],$tot['profit'],'']);
    fclose($out); exit;
}

$adminTitle='Inventory & Profit Report'; $adminSection='inventory-report'; $adminCrumbs=[['label'=>'Inventory Report']];
include __DIR__.'/../includes/admin-header.php';
?>
<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title">Daily product-wise report</h2></div>
  <div class="ap-card-body">
    <p class="ap-hint">Compares stock <b>bought</b> (purchases) vs <b>sold</b> (orders) for the date range, and estimates profit using the latest cost price per product.</p>
    <form method="get" class="ap-form">
      <div class="ap-form-grid">
        <div class="ap-field"><label class="ap-label">From</label><input class="ap-input" type="date" name="from" value="<?=e($from)?>"></div>
        <div class="ap-field"><label class="ap-label">To</label><input class="ap-input" type="date" name="to" value="<?=e($to)?>"></div>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="ap-btn ap-btn-primary">🔍 Run report</button>
        <a class="ap-btn" href="<?=url('admin/inventory-report.php?export=csv&from='.e($from).'&to='.e($to))?>">⬇ Export CSV</a>
      </div>
    </form>
  </div>
</div>

<div class="ap-card">
  <div class="ap-card-header"><h2 class="ap-card-title"><?=e($from)?> → <?=e($to)?></h2>
    <span style="font-size:13px;color:#7a8a7b;">Profit: <b style="color:<?= $tot['profit']>=0?'#1a7a34':'#c0392b' ?>">₹<?=number_format($tot['profit'],2)?></b></span></div>
  <div class="ap-card-body no-pad">
    <div class="ap-table-wrap"><table class="ap-table">
      <thead><tr><th>Product</th><th>Bought</th><th>Bought ₹</th><th>Sold</th><th>Revenue ₹</th><th>Cost/unit</th><th>COGS ₹</th><th>Profit ₹</th><th>Stock</th></tr></thead>
      <tbody>
        <?php if(!$rows): ?><tr><td colspan="9"><span class="ap-hint" style="padding:12px;display:block">No purchases or sales in this range.</span></td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><b><?= e($r['name']) ?></b></td>
            <td><?= rtrim(rtrim(number_format($r['bought_qty'],3),'0'),'.') ?></td>
            <td>₹<?= number_format($r['bought_cost'],2) ?></td>
            <td><?= rtrim(rtrim(number_format($r['sold_qty'],3),'0'),'.') ?></td>
            <td>₹<?= number_format($r['revenue'],2) ?></td>
            <td>₹<?= number_format($r['cost_price'],2) ?></td>
            <td>₹<?= number_format($r['cogs'],2) ?></td>
            <td><b style="color:<?= $r['profit']>=0?'#1a7a34':'#c0392b' ?>">₹<?= number_format($r['profit'],2) ?></b></td>
            <td><?= rtrim(rtrim(number_format($r['stock'],3),'0'),'.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if($rows): ?>
      <tfoot><tr style="font-weight:800;background:#f8fbf6">
        <td>TOTAL</td>
        <td><?= rtrim(rtrim(number_format($tot['bought_qty'],3),'0'),'.') ?></td>
        <td>₹<?= number_format($tot['bought_cost'],2) ?></td>
        <td><?= rtrim(rtrim(number_format($tot['sold_qty'],3),'0'),'.') ?></td>
        <td>₹<?= number_format($tot['revenue'],2) ?></td>
        <td>—</td>
        <td>₹<?= number_format($tot['cogs'],2) ?></td>
        <td style="color:<?= $tot['profit']>=0?'#1a7a34':'#c0392b' ?>">₹<?= number_format($tot['profit'],2) ?></td>
        <td>—</td>
      </tr></tfoot>
      <?php endif; ?>
    </table></div>
  </div>
</div>
<p class="ap-hint" style="padding:0 4px">Note: Profit uses the <b>latest cost price</b> per product (updated on each purchase). COGS = sold qty × latest cost. Cancelled/returned orders are excluded from sales.</p>
<?php include __DIR__.'/../includes/admin-footer.php'; ?>
