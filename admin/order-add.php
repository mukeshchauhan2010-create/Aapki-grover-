<?php
/**
 * Admin — create an order on behalf of a customer (phone / WhatsApp orders).
 *
 * Staff pick the customer + delivery address, add product line items, choose a
 * payment method/status, and the order is created server-side exactly like the
 * storefront checkout (stock is decremented atomically, order number uses the
 * AG + yymmdd + 4-digit daily sequence, points earned are credited).
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'orders')) redirect('admin/');

// ── AJAX: return a customer's saved addresses (for the dropdown) ──
if (isset($_GET['addresses'])) {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)$_GET['addresses'];
    $out = [];
    if ($uid > 0) {
        $as = $pdo->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
        $as->execute([$uid]);
        foreach ($as->fetchAll() as $a) {
            $line = trim(implode(', ', array_filter([
                $a['apartment_no'], $a['apartment_name'], $a['area'],
                $a['address_line'], ($a['city'] ? $a['city'].' - '.$a['pincode'] : ''), $a['state']
            ])));
            $out[] = ['id'=>(int)$a['id'], 'is_default'=>(int)$a['is_default'],
                      'text'=>$a['address_type'].': '.$line.' · 📞 '.$a['phone']];
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$err = '';
$createdOrderNo = null;
$preUserId = (int)($_GET['user_id'] ?? 0);   // pre-selected (e.g. just created a user)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $uid      = (int)($_POST['user_id'] ?? 0);
    $addressId= (int)($_POST['address_id'] ?? 0);
    $payment  = in_array($_POST['payment_method'] ?? 'cod', ['cod','razorpay','wallet'], true) ? $_POST['payment_method'] : 'cod';
    $payStatus= in_array($_POST['payment_status'] ?? 'pending', ['pending','paid','failed'], true) ? $_POST['payment_status'] : 'pending';
    $status   = in_array($_POST['status'] ?? 'placed', ['placed','confirmed','packed','out_for_delivery','delivered','cancelled','returned'], true) ? $_POST['status'] : 'placed';
    $slot     = trim($_POST['delivery_slot'] ?? 'Any available slot') ?: 'Any available slot';
    $notes    = trim($_POST['notes'] ?? '');
    $freeDelivery = !empty($_POST['free_delivery']);

    // Line items: parallel arrays variant_id[] + qty[]
    $vids = $_POST['variant_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $lineItems = [];
    foreach ($vids as $i => $rawVid) {
        $vid = (int)$rawVid;
        $qty = (float)($qtys[$i] ?? 0);
        if ($vid > 0 && $qty > 0) $lineItems[$vid] = ($lineItems[$vid] ?? 0) + $qty; // merge dups
    }

    // ── Validation ──
    if ($uid < 1) {
        $err = 'Please select a customer.';
    } else {
        $us = $pdo->prepare('SELECT id FROM users WHERE id=? LIMIT 1'); $us->execute([$uid]);
        if (!$us->fetchColumn()) $err = 'Selected customer was not found.';
    }
    if (!$err && $addressId > 0) {
        $as = $pdo->prepare('SELECT id FROM addresses WHERE id=? AND user_id=?'); $as->execute([$addressId, $uid]);
        if (!$as->fetchColumn()) $err = 'Selected address does not belong to this customer.';
    }
    if (!$err && !$lineItems) $err = 'Add at least one product with a quantity.';

    if (!$err) {
        try {
            $pdo->beginTransaction();

            // Lock variants, validate stock, compute subtotal.
            $valid = []; $subtotal = 0;
            $vs = $pdo->prepare('SELECT v.*, p.id product_id, p.name FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? AND p.is_active=1 FOR UPDATE');
            foreach ($lineItems as $vid => $qty) {
                $vs->execute([$vid]); $v = $vs->fetch();
                if (!$v) throw new RuntimeException('A selected product is no longer available.');
                if ($qty > (float)$v['stock']) throw new RuntimeException('Not enough stock for "'.$v['name'].' ('.$v['label'].')". Available: '.(0+$v['stock']).'.');
                $valid[] = ['v'=>$v, 'qty'=>$qty];
                $subtotal += $qty * (float)$v['price'];
            }

            // Charges (same rules as storefront checkout).
            $handling = (float)setting($pdo, 'handling_charge', '15');
            $delivery = $freeDelivery ? 0 : ($subtotal > 499 ? 0 : 40);
            $total    = $subtotal + $handling + $delivery;

            // Points earned (same formula as checkout).
            $earn = setting_bool($pdo, 'points_enabled') ? (int)floor($subtotal / 100 * (float)setting($pdo, 'points_per_100', '1')) : 0;

            // Order number: AG + yymmdd + 4-digit daily sequence.
            $orderPrefix = 'AG'.date('ymd');
            $seqStmt = $pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(order_no,9) AS UNSIGNED)),0)+1 FROM orders WHERE order_no LIKE ?');
            $seqStmt->execute([$orderPrefix.'%']);
            $orderNo = $orderPrefix.str_pad((string)(int)$seqStmt->fetchColumn(), 4, '0', STR_PAD_LEFT);

            $os = $pdo->prepare(
                'INSERT INTO orders(order_no,user_id,address_id,subtotal,handling_charge,delivery_fee,discount,points_used,points_earned,coupon_code,total,payment_method,payment_status,status,delivery_slot,notes,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,0,0,?,NULL,?,?,?,?,?,?,NOW(),NOW())'
            );
            $os->execute([$orderNo,$uid,$addressId?:null,$subtotal,$handling,$delivery,$earn,$total,$payment,$payStatus,$status,$slot,$notes?:null]);
            $oid = (int)$pdo->lastInsertId();

            // Items + atomic stock decrement.
            $is = $pdo->prepare('INSERT INTO order_items(order_id,product_id,variant_id,product_name,variant_label,qty,unit_price,line_total) VALUES(?,?,?,?,?,?,?,?)');
            $ss = $pdo->prepare('UPDATE product_variants SET stock=stock-? WHERE id=? AND stock>=?');
            foreach ($valid as $x) {
                $v = $x['v']; $q = $x['qty'];
                $ss->execute([$q, $v['id'], $q]);
                if ($ss->rowCount() !== 1) throw new RuntimeException('Inventory changed while saving. Please retry.');
                $is->execute([$oid, $v['product_id'], $v['id'], $v['name'], $v['label'], $q, $v['price'], $q * (float)$v['price']]);
            }

            // Credit points earned (mirrors checkout).
            if ($earn) {
                $pdo->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$earn, $uid]);
                $pdo->prepare('INSERT INTO point_transactions(user_id,points,reason,order_id) VALUES(?,?,?,?)')->execute([$uid, $earn, 'Order points', $oid]);
            }

            $pdo->commit();
            flash('success', 'Order '.$orderNo.' created for the customer.');
            redirect('admin/orders.php?q='.$orderNo);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = $e->getMessage();
            $preUserId = $uid;  // keep the selected customer on error
        }
    } else {
        $preUserId = $uid;
    }
}

// Data for the form
$customers = $pdo->query('SELECT id, name, phone, email FROM users ORDER BY id DESC LIMIT 1000')->fetchAll();
$products  = $pdo->query(
    'SELECT v.id vid, v.label, v.price, v.stock, p.name pname
     FROM product_variants v JOIN products p ON p.id=v.product_id
     WHERE p.is_active=1 AND v.stock > 0
     ORDER BY p.name, v.price'
)->fetchAll();

// Pre-selected customer's addresses (if any)
$preAddresses = [];
if ($preUserId > 0) {
    $pa = $pdo->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
    $pa->execute([$preUserId]);
    $preAddresses = $pa->fetchAll();
}

$handlingCharge = (float)setting($pdo, 'handling_charge', '15');

$adminTitle   = 'Create Order';
$adminSection = 'orders';
$adminCrumbs  = [['label'=>'Orders','url'=>url('admin/orders.php')], ['label'=>'Create Order']];
include __DIR__.'/../includes/admin-header.php';
?>

<?php if ($err): ?><div class="ap-alert"><?= e($err) ?></div><?php endif; ?>

<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Create order (phone / WhatsApp)</h2>
    <div style="display:flex;gap:8px;">
      <a class="ap-btn ap-btn-sm" href="<?= url('admin/orders.php') ?>">📦 Order List</a>
      <a class="ap-btn ap-btn-sm ap-btn-primary" href="<?= url('admin/user-add.php') ?>">+ New customer</a>
    </div>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post" id="orderForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="ap-section-title">Customer</div>
      <div class="ap-form-grid">
        <div class="ap-field">
          <label class="ap-label">Select customer *</label>
          <select class="ap-select" name="user_id" id="userSelect" required>
            <option value="">— choose a customer —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $preUserId===(int)$c['id']?'selected':'' ?>>
                <?= e($c['name']) ?><?= $c['phone'] ? ' · '.e($c['phone']) : '' ?><?= $c['email'] ? ' · '.e($c['email']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="ap-hint">Not listed? <a href="<?= url('admin/user-add.php') ?>">Add a new customer</a> first.</span>
        </div>
        <div class="ap-field">
          <label class="ap-label">Delivery address</label>
          <select class="ap-select" name="address_id" id="addressSelect">
            <option value="">— no address on file —</option>
            <?php foreach ($preAddresses as $a):
              $line = trim(implode(', ', array_filter([$a['apartment_no'],$a['apartment_name'],$a['area'],$a['address_line'],($a['city']?$a['city'].' - '.$a['pincode']:''),$a['state']])));
            ?>
              <option value="<?= $a['id'] ?>" <?= $a['is_default']?'selected':'' ?>><?= e($a['address_type'].': '.$line.' · 📞 '.$a['phone']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="ap-hint">Addresses load for the selected customer.</span>
        </div>
      </div>

      <div class="ap-section-title">Products</div>
      <div style="overflow-x:auto;">
        <div style="display:grid;grid-template-columns:3fr 1fr auto;gap:8px;padding-bottom:6px;min-width:520px;">
          <span class="ap-label">Product &amp; pack</span><span class="ap-label">Qty</span><span class="ap-label">&nbsp;</span>
        </div>
        <div id="itemRows" style="min-width:520px;"></div>
      </div>
      <div style="margin-top:6px;"><button type="button" class="ap-btn ap-btn-sm" id="addRowBtn">+ Add product</button></div>

      <div class="ap-section-title">Payment &amp; delivery</div>
      <div class="ap-form-grid-3">
        <div class="ap-field">
          <label class="ap-label">Payment method</label>
          <select class="ap-select" name="payment_method">
            <option value="cod">COD (Cash on delivery)</option>
            <option value="razorpay">UPI / Online</option>
            <option value="wallet">Wallet</option>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">Payment status</label>
          <select class="ap-select" name="payment_status">
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">Order status</label>
          <select class="ap-select" name="status">
            <option value="placed">Placed</option>
            <option value="confirmed">Confirmed</option>
            <option value="packed">Packed</option>
            <option value="out_for_delivery">Out for delivery</option>
            <option value="delivered">Delivered</option>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">Delivery slot</label>
          <select class="ap-select" name="delivery_slot">
            <option>Any available slot</option><option>7 AM - 10 AM</option><option>10 AM - 1 PM</option><option>5 PM - 8 PM</option><option>8 PM - 10 PM</option>
          </select>
        </div>
        <div class="ap-field">
          <label class="ap-label">Free delivery?</label>
          <label class="ap-check-row"><input type="checkbox" name="free_delivery" value="1" id="freeDel"> Waive the ₹40 delivery fee</label>
        </div>
        <div class="ap-field">
          <label class="ap-label">Notes (optional)</label>
          <input class="ap-input" name="notes" placeholder="e.g. ordered via WhatsApp">
        </div>
      </div>

      <!-- Live totals -->
      <div class="ap-section-title">Summary</div>
      <div style="max-width:360px;font-size:14px;line-height:2;">
        <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><b id="sumSub">₹0.00</b></div>
        <div style="display:flex;justify-content:space-between;"><span>Handling</span><b id="sumHandling">₹<?= number_format($handlingCharge,2) ?></b></div>
        <div style="display:flex;justify-content:space-between;"><span>Delivery</span><b id="sumDelivery">₹0.00</b></div>
        <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line,#dce5da);margin-top:6px;padding-top:6px;"><span>Total</span><b id="sumTotal">₹0.00</b></div>
      </div>

      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="ap-btn ap-btn-primary" style="min-width:200px;">✅ Create order</button>
        <a class="ap-btn" href="<?= url('admin/orders.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var AG = window.AAPKI || {base:'<?= e(url()) ?>'};
  var BASE = '<?= e(url()) ?>';
  var HANDLING = <?= json_encode($handlingCharge) ?>;
  // Product variants (id, label, price, stock, name).
  var VARIANTS = <?= json_encode(array_map(function($p){ return ['vid'=>(int)$p['vid'],'label'=>$p['label'],'price'=>(float)$p['price'],'stock'=>(float)$p['stock'],'name'=>$p['pname']]; }, $products), JSON_UNESCAPED_UNICODE) ?>;

  var optionsHtml = '<option value="">— select product —</option>' + VARIANTS.map(function(v){
    return '<option value="'+v.vid+'" data-price="'+v.price+'" data-stock="'+v.stock+'">'+
      escapeHtml(v.name+' · '+v.label+' — ₹'+v.price.toFixed(2)+' (stock '+(+v.stock)+')')+'</option>';
  }).join('');

  function escapeHtml(s){ return (s+'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

  var rows = document.getElementById('itemRows');
  function addRow(){
    var div = document.createElement('div');
    div.className = 'ap-item-row';
    div.style.cssText = 'display:grid;grid-template-columns:3fr 1fr auto;gap:8px;margin-bottom:8px;';
    div.innerHTML =
      '<select class="ap-select" name="variant_id[]">'+optionsHtml+'</select>'+
      '<input class="ap-input it-qty" name="qty[]" type="number" min="0" step="0.001" value="1">'+
      '<button type="button" class="ap-btn ap-btn-sm it-del">✕</button>';
    rows.appendChild(div);
    div.querySelector('.it-del').addEventListener('click', function(){ div.remove(); recalc(); });
    div.querySelector('select').addEventListener('change', recalc);
    div.querySelector('.it-qty').addEventListener('input', recalc);
    recalc();
  }
  document.getElementById('addRowBtn').addEventListener('click', addRow);

  function recalc(){
    var sub = 0;
    rows.querySelectorAll('.ap-item-row').forEach(function(r){
      var sel = r.querySelector('select'); var opt = sel.selectedOptions[0];
      var qty = parseFloat(r.querySelector('.it-qty').value) || 0;
      if(opt && opt.value){ sub += (parseFloat(opt.dataset.price)||0) * qty; }
    });
    var free = document.getElementById('freeDel').checked;
    var delivery = free ? 0 : (sub > 499 ? 0 : (sub > 0 ? 40 : 0));
    var total = sub + HANDLING + delivery;
    document.getElementById('sumSub').textContent = '₹'+sub.toFixed(2);
    document.getElementById('sumDelivery').textContent = '₹'+delivery.toFixed(2);
    document.getElementById('sumTotal').textContent = '₹'+total.toFixed(2);
  }
  document.getElementById('freeDel').addEventListener('change', recalc);

  // Load addresses when the customer changes.
  var userSel = document.getElementById('userSelect'), addrSel = document.getElementById('addressSelect');
  userSel.addEventListener('change', function(){
    var uid = userSel.value;
    addrSel.innerHTML = '<option value="">loading…</option>';
    if(!uid){ addrSel.innerHTML = '<option value="">— no address on file —</option>'; return; }
    fetch(BASE+'admin/order-add.php?addresses='+encodeURIComponent(uid), {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.json(); })
      .then(function(list){
        if(!list || !list.length){ addrSel.innerHTML = '<option value="">— no address on file —</option>'; return; }
        addrSel.innerHTML = '<option value="">— select address —</option>' + list.map(function(a){
          return '<option value="'+a.id+'"'+(a.is_default?' selected':'')+'>'+escapeHtml(a.text)+'</option>';
        }).join('');
      }).catch(function(){ addrSel.innerHTML = '<option value="">— could not load —</option>'; });
  });

  addRow(); // start with one row
})();
</script>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
