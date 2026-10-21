<?php
require_once __DIR__.'/config/db.php'; $pdo=db();

// ── Add a new delivery address directly from checkout ──
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_address']) && verify_csrf($_POST['csrf']??null)){
  if(!is_logged_in()){flash('error','Please sign in first.');redirect('login.php?return=cart/');}
  $uid=user_id();
  $type=in_array($_POST['address_type']??'Home',['Home','Office','Other'],true)?$_POST['address_type']:'Home';
  $isDefault=!empty($_POST['is_default'])?1:0;
  if($isDefault){$pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=?')->execute([$uid]);}
  $us=$pdo->prepare('SELECT first_name,last_name,name,phone FROM users WHERE id=?');$us->execute([$uid]);$uu=$us->fetch();
  $fullName=trim((($uu['first_name']??'').' '.($uu['last_name']??'')))?:($uu['name']??'');

  // ── Server-side validation (mandatory fields) ──
  $apt   = trim($_POST['apartment_no']??'');
  $area  = trim($_POST['area']??'');
  $street= trim($_POST['street_details']??'');
  $city  = trim($_POST['city']??'');
  $state = trim($_POST['state']??'');
  $pin   = preg_replace('/\D+/','',$_POST['pincode']??'');
  $phone = preg_replace('/\D+/','',$_POST['phone']??'');
  $errA  = '';
  if($apt===''||$area===''||$street===''||$city===''||$state==='')      $errA='Please fill all required address fields.';
  elseif(!preg_match('/^[1-9][0-9]{5}$/',$pin))                          $errA='Please enter a valid 6-digit pincode.';
  elseif(!preg_match('/^[6-9][0-9]{9}$/',$phone))                        $errA='Please enter a valid 10-digit mobile number.';
  if($errA){ flash('error',$errA); redirect('cart/'); }

  $st=$pdo->prepare('INSERT INTO addresses(user_id,label,name,phone,alt_phone,apartment_no,apartment_name,area,landmark,address_line,city,state,pincode,address_type,is_default) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $st->execute([$uid,$type,$fullName,$phone,preg_replace('/\D+/','',$_POST['alt_phone']??'')?:null,$apt,trim($_POST['apartment_name']??''),$area,trim($_POST['landmark']??''),$street,$city,$state,$pin,$type,$isDefault]);
  flash('success','Delivery address added.');
  redirect('cart/');
}

if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['add_address']) && verify_csrf($_POST['csrf']??null)){
  if(!is_logged_in()){flash('error','Please login before placing your order.');redirect('login.php?return=cart/');}
  require_once __DIR__.'/lib/wallet.php';
  require_once __DIR__.'/lib/razorpay.php';
  $items=json_decode($_POST['cart_json']??'[]',true) ?: []; $address=(int)($_POST['address_id']??0); $coupon=trim($_POST['coupon']??''); $payment=in_array($_POST['payment_method']??'cod',['cod','razorpay','wallet'],true)?$_POST['payment_method']:'cod'; $pointsUsed=max(0,(int)($_POST['points_used']??0)); $err='';
  $as=$pdo->prepare('SELECT * FROM addresses WHERE id=? AND user_id=?');$as->execute([$address,user_id()]);$addr=$as->fetch(); if(!$addr)$err='Please select a delivery address.';
  $valid=[];$subtotal=0;
  if(!$err){$pdo->beginTransaction();try{foreach($items as $i){$vid=(int)($i['variant_id']??0);$qty=(float)($i['qty']??0);if($vid<1||$qty<=0)continue;$s=$pdo->prepare('SELECT v.*,p.id product_id,p.name FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? AND p.is_active=1 FOR UPDATE');$s->execute([$vid]);$v=$s->fetch();if(!$v||$qty>(float)$v['stock'])throw new RuntimeException('One or more products are out of stock.');$valid[]=['v'=>$v,'qty'=>$qty];$subtotal+=$qty*(float)$v['price'];}if(!$valid)throw new RuntimeException('Your cart is empty.');
    $discount=0;if($coupon){$cs=$pdo->prepare('SELECT * FROM coupons WHERE code=? AND is_active=1 AND (start_at IS NULL OR start_at<=NOW()) AND (end_at IS NULL OR end_at>=NOW()) AND (usage_limit IS NULL OR used_count<usage_limit)');$cs->execute([$coupon]);$c=$cs->fetch();if(!$c||$subtotal<(float)$c['min_order'])throw new RuntimeException('Invalid or inapplicable coupon.');$discount=$c['type']==='percent'?min($subtotal*(float)$c['value']/100,(float)($c['max_discount']??PHP_INT_MAX)):(float)$c['value'];$discount=min($discount,$subtotal);}
    $pointDiscount=0;$balance=(int)$pdo->query('SELECT points FROM users WHERE id='.(int)user_id())->fetchColumn();if($pointsUsed){if(!setting_bool($pdo,'points_enabled')||!setting_bool($pdo,'redeem_enabled')||$pointsUsed<((int)setting($pdo,'redeem_min_points','100'))||$pointsUsed>$balance)throw new RuntimeException('Invalid points redemption.');$pointDiscount=min($pointsUsed*(float)setting($pdo,'point_value_rupees','1'),max(0,$subtotal-$discount));$discount+=$pointDiscount;}
    $handling=(float)setting($pdo,'handling_charge','15');$delivery=max(0,$subtotal-$discount)>499?0:40;$total=max(0,$subtotal-$discount)+$handling+$delivery;
    // Order number: AG + yy + mm + dd + daily incrementing sequence (4 digits).
    $orderPrefix='AG'.date('ymd');
    $seqStmt=$pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(order_no,9) AS UNSIGNED)),0)+1 FROM orders WHERE order_no LIKE ?');
    $seqStmt->execute([$orderPrefix.'%']);
    $seq=(int)$seqStmt->fetchColumn();
    $orderNo=$orderPrefix.str_pad((string)$seq,4,'0',STR_PAD_LEFT);
    $earn=setting_bool($pdo,'points_enabled')?(int)floor($subtotal/100*(float)setting($pdo,'points_per_100','1')):0;
    // Wallet payment: ensure sufficient balance before creating the order.
    $payStatus='pending';
    if($payment==='wallet'){$wbal=wallet_balance($pdo,(int)user_id());if($wbal + 0.0001 < $total)throw new RuntimeException('Insufficient wallet balance. Available ₹'.number_format($wbal,2).', order total ₹'.number_format($total,2).'.');$payStatus='paid';}
    $os=$pdo->prepare('INSERT INTO orders(order_no,user_id,address_id,subtotal,handling_charge,delivery_fee,discount,points_used,points_earned,coupon_code,total,payment_method,payment_status,status,delivery_slot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$os->execute([$orderNo,user_id(),$address,$subtotal,$handling,$delivery,$discount,$pointsUsed,$earn,$coupon?:null,$total,$payment,$payStatus,'placed',trim($_POST['delivery_slot']??'Any available slot')]);$oid=(int)$pdo->lastInsertId();
    if($payment==='wallet'){wallet_debit($pdo,(int)user_id(),$total,'Order payment '.$orderNo,$oid,true);}$is=$pdo->prepare('INSERT INTO order_items(order_id,product_id,variant_id,product_name,variant_label,qty,unit_price,line_total) VALUES(?,?,?,?,?,?,?,?)');$ss=$pdo->prepare('UPDATE product_variants SET stock=stock-? WHERE id=? AND stock>=?');foreach($valid as $x){$v=$x['v'];$q=$x['qty'];$ss->execute([$q,$v['id'],$q]);if($ss->rowCount()!==1)throw new RuntimeException('Inventory changed. Please retry.');$is->execute([$oid,$v['product_id'],$v['id'],$v['name'],$v['label'],$q,$v['price'],$q*$v['price']]);}$pdo->prepare('UPDATE coupons SET used_count=used_count+1 WHERE code=? AND ?<>""')->execute([$coupon,$coupon]);if($pointsUsed){$pdo->prepare('UPDATE users SET points=points-? WHERE id=?')->execute([$pointsUsed,user_id()]);$pdo->prepare('INSERT INTO point_transactions(user_id,points,reason,order_id) VALUES(?,?,?,?)')->execute([user_id(),-$pointsUsed,'Points redeemed',$oid]);}if($earn){$pdo->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$earn,user_id()]);$pdo->prepare('INSERT INTO point_transactions(user_id,points,reason,order_id) VALUES(?,?,?,?)')->execute([user_id(),$earn,'Order points',$oid]);}$pdo->commit();

    // ── Razorpay online payment: open Checkout popup instead of instant success ──
    if($payment==='razorpay'){
      if(!razorpay_enabled($pdo)) { flash('error','Online payment is not available right now. Please choose COD.'); redirect('cart/'); }
      try {
        $rzp = razorpay_create_order($pdo, (float)$total, $orderNo);
        $pdo->prepare('UPDATE orders SET razorpay_order_id=? WHERE id=?')->execute([$rzp['id'],$oid]);
      } catch(Throwable $e) {
        // Order exists as pending; let the user retry from account.
        flash('error','Could not start the online payment. Your order '.$orderNo.' is pending; please retry payment.');
        redirect('account/orders.php');
      }
      $u = $pdo->prepare('SELECT first_name,last_name,email,phone FROM users WHERE id=?'); $u->execute([user_id()]); $u=$u->fetch();
      $keyId   = razorpay_key_id($pdo);
      $amountP = (int)round($total*100);
      $verifyUrl = url('api/razorpay-verify.php');
      ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Complete payment</title><link rel="stylesheet" href="<?=e(url('assets/css/style.css'))?>"><script src="https://checkout.razorpay.com/v1/checkout.js"></script></head>
      <body><main class="container page"><div class="panel" style="max-width:520px;margin:40px auto;text-align:center">
        <span class="eyebrow">PAYMENT</span><h1>Complete your payment</h1>
        <p class="hint">Order <strong><?=e($orderNo)?></strong> · ₹<?=number_format($total,2)?></p>
        <p class="hint" id="rzpMsg">Opening secure payment…</p>
        <form id="verifyForm" method="post" action="<?=e($verifyUrl)?>" style="display:none">
          <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
          <input type="hidden" name="order_no" value="<?=e($orderNo)?>">
          <input type="hidden" name="razorpay_order_id" id="f_oid" value="<?=e($rzp['id'])?>">
          <input type="hidden" name="razorpay_payment_id" id="f_pid">
          <input type="hidden" name="razorpay_signature" id="f_sig">
        </form>
        <a class="btn" href="<?=e(url('cart/'))?>">Cancel &amp; return to cart</a>
      </div></main>
      <script>
      (function(){
        var opts={
          key:<?=json_encode($keyId)?>,
          amount:<?=json_encode($amountP)?>,
          currency:'INR',
          name:<?=json_encode(APP_NAME)?>,
          description:'Order <?=e($orderNo)?>',
          order_id:<?=json_encode($rzp['id'])?>,
          prefill:{
            name:<?=json_encode(trim(($u['first_name']??'').' '.($u['last_name']??'')))?>,
            email:<?=json_encode($u['email']??'')?>,
            contact:<?=json_encode($u['phone']??'')?>
          },
          theme:{color:'#12652f'},
          handler:function(resp){
            document.getElementById('f_pid').value=resp.razorpay_payment_id;
            document.getElementById('f_sig').value=resp.razorpay_signature;
            document.getElementById('rzpMsg').textContent='Verifying payment…';
            document.getElementById('verifyForm').submit();
          },
          modal:{ondismiss:function(){document.getElementById('rzpMsg').textContent='Payment cancelled. You can retry from your orders, or choose COD.';}}
        };
        try{ var rzp=new Razorpay(opts); rzp.on('payment.failed',function(){document.getElementById('rzpMsg').textContent='Payment failed. Please retry.';}); rzp.open(); }
        catch(e){ document.getElementById('rzpMsg').textContent='Could not load the payment window.'; }
      })();
      </script>
      </body></html><?php
      exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="3;url='.e(url('account/')).'"><title>Order placed</title><link rel="stylesheet" href="'.e(url('assets/css/style.css')).'"></head><body><main class="container page"><div class="panel success-order"><div class="success-icon">✓</div><span class="eyebrow">ORDER CONFIRMED</span><h1>Thank you for your order!</h1><p>Your order <strong>'.e($orderNo).'</strong> has been created.</p><p class="hint">Returning to your account…</p><a class="btn btn-primary" href="'.e(url('account/')).'">View My Account</a></div></main></body></html>';exit;
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e->getMessage();}}
}
$title='Cart & Checkout | '.APP_NAME;include __DIR__.'/includes/header.php';
$addresses=[];$userPoints=0;$userWallet=0.0;if(is_logged_in()){$st=$pdo->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$st->execute([user_id()]);$addresses=$st->fetchAll();$ur=$pdo->prepare('SELECT points,wallet_balance FROM users WHERE id=?');$ur->execute([user_id()]);$urow=$ur->fetch();$userPoints=(int)($urow['points']??0);$userWallet=(float)($urow['wallet_balance']??0);}
?>
<section class="container page"><div class="section-head"><div><span class="eyebrow">YOUR BASKET</span><h1>Cart & checkout</h1><p class="hint">Review your basket, choose delivery and go directly to payment.</p></div><a class="btn" href="<?=url()?>">Continue shopping</a></div>
<?php if($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?>
<div id="cart-root" class="cart-checkout-layout"><div class="cart-main"><div class="panel"><div id="cartItems"><p class="hint">Loading your basket…</p></div></div>
<?php if(is_logged_in()): ?>
<!-- ── Add / manage delivery address (separate form — must NOT be nested in the order form) ── -->
<div class="panel checkout-panel">
  <h2>Delivery details</h2>
  <?php if($addresses): ?>
    <form method="post" id="orderForm" class="order-form-inline">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="cart_json" id="cartJson">
      <div class="address-options">
        <?php foreach($addresses as $a): ?>
          <label class="address-choice"><input type="radio" name="address_id" value="<?=$a['id']?>" data-pincode="<?=e(preg_replace('/\D+/','',$a['pincode']))?>" <?=$a['is_default']?'checked':''?>><span><b><?=e($a['address_type']??$a['label'])?></b> <?=($a['is_default']?'<small class="default-badge">Default</small>':'')?><br><?=e($a['apartment_no'])?> <?=e($a['apartment_name'])?>, <?=e($a['area'])?><br><?=e($a['address_line'])?>, <?=e($a['city'])?> - <?=e($a['pincode'])?><br>📞 <?=e($a['phone'])?><?=!empty($a['alt_phone'])?' · Alt: '.e($a['alt_phone']):''?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field-grid compact"><label>Delivery slot<select name="delivery_slot"><option>Any available slot</option><option>7 AM - 10 AM</option><option>10 AM - 1 PM</option><option>5 PM - 8 PM</option><option>8 PM - 10 PM</option></select></label><label>Payment method<div class="payment-options"><label><input type="radio" name="payment_method" value="cod" checked> COD</label><label><input type="radio" name="payment_method" value="razorpay"> UPI / Online</label><label><input type="radio" name="payment_method" value="wallet"> 💰 Wallet (₹<?=number_format($userWallet,2)?>)</label></div></label></div>
      <div class="coupon-points"><div><label>Coupon code<div class="input-action"><input name="coupon" placeholder="Enter coupon"><button type="button" class="btn btn-outline" id="applyCoupon">Apply</button></div></label></div><div><label>Points to redeem (<?=number_format($userPoints)?> available)<input name="points_used" type="number" min="0" step="1" value="0"></label></div></div>
      <button type="submit" class="btn btn-primary btn-block pay-btn" id="payBtn">Go to payment →</button>
    </form>
  <?php else: ?>
    <div class="empty-mini">No delivery address saved yet. Please add one below to continue to payment.</div>
  <?php endif; ?>

  <details class="add-address-inline" <?=$addresses?'':'open'?>>
    <summary class="btn btn-outline btn-block"><?= $addresses ? '+ Add another delivery address' : '+ Add your delivery address' ?></summary>
    <form method="post" class="address-form" id="addressForm">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="add_address" value="1">
      <div class="field-grid"><label>Apartment / House No. *<input name="apartment_no" required></label><label>Apartment name<input name="apartment_name"></label></div>
      <div class="field-grid"><label>Area *<input name="area" required></label><label>Landmark<input name="landmark"></label></div>
      <label>Street / address details *<input name="street_details" required></label>
      <div class="field-grid"><label>City *<input name="city" value="Delhi" required></label><label>State *<input name="state" value="Delhi" required></label></div>
      <div class="field-grid"><label>Pincode *<input name="pincode" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" placeholder="6-digit PIN" required></label><label>Phone number *<input name="phone" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" placeholder="10-digit mobile" required></label></div>
      <div class="field-grid"><label>Alternate mobile (optional)<input name="alt_phone" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" placeholder="Backup number for delivery"></label></div>
      <div class="radio-row"><span>Type</span><label><input type="radio" name="address_type" value="Home" checked> Home</label><label><input type="radio" name="address_type" value="Office"> Office</label><label><input type="radio" name="address_type" value="Other"> Other</label></div>
      <label class="check-row"><input type="checkbox" name="is_default" value="1" <?=empty($addresses)?'checked':''?>> Set as default</label>
      <button type="submit" class="btn btn-primary btn-block">Save address</button>
    </form>
  </details>
</div>
<?php else: ?><div class="panel login-checkout"><h2>Sign in to place your order</h2><p class="hint">Your basket is saved. Continue with Google to check out in seconds, then add your delivery address.</p><a class="social-btn google-btn" href="<?=url('auth/google.php?return=cart/')?>"><span class="social-ico" aria-hidden="true">G</span><span>Continue with Google</span></a><p class="hint" style="margin-top:12px;">Prefer email? <a href="<?=url('register.php?return=cart/')?>">Create an account</a> or <a href="<?=url('login.php?return=cart/')?>">login</a>.</p></div><?php endif; ?></div>
<aside class="panel cart-summary"><h2>Order summary</h2><div class="summary-row"><span>Subtotal</span><b id="cartSubtotal">₹0.00</b></div><div class="summary-row"><span>Handling charge <button type="button" class="info-tip" id="handlingInfo" aria-label="Handling charge information">i</button></span><b id="cartHandling">₹0.00</b></div><div id="handlingTip" class="tooltip-box" role="tooltip">A small handling charge helps cover packing, sorting and order processing.</div><div class="summary-row"><span>Delivery</span><b id="cartDelivery">₹0.00</b></div><div class="summary-row total"><span>Total</span><b id="cartTotal">₹0.00</b></div><p class="hint">Taxes and final payment amount are confirmed before payment.</p></aside></div></section>
<script>
(function(){
  var orderForm=document.getElementById('orderForm');
  var AG=(window.AAPKI||{}); var AGBASE=AG.base||'/aapkigrocery/';
  var SERVED=(AG.servedPincodes||[]).map(String);

  function agCheckoutPopup(message){
    var wrap=document.createElement('div'); wrap.className='ag-modal';
    wrap.innerHTML='<div class="ag-modal-card"><div class="ag-modal-bar"><span></span></div><div class="ag-modal-body">'+message+'</div><div class="ag-modal-count">Closing in <b>5</b>s…</div></div>';
    document.body.appendChild(wrap);
    var bar=wrap.querySelector('.ag-modal-bar span'); var cnt=wrap.querySelector('.ag-modal-count b');
    requestAnimationFrame(function(){bar.style.transition='width 5s linear';bar.style.width='0%';});
    var left=5,t=setInterval(function(){left--;if(cnt)cnt.textContent=left;if(left<=0)clearInterval(t);},1000);
    setTimeout(function(){try{localStorage.removeItem('aapki_cart_v2');}catch(_){}; window.location.replace(AGBASE);},5000);
  }

  if(orderForm){
    orderForm.addEventListener('submit',function(e){
      // Attach the current cart from localStorage
      var cj=document.getElementById('cartJson');
      cj.value=localStorage.getItem('aapki_cart_v2')||'[]';
      var c; try{c=JSON.parse(cj.value||'[]');}catch(_){c=[];}
      if(!c.length){e.preventDefault();alert('Your cart is empty.');return;}
      // Require a selected delivery address
      var selected=orderForm.querySelector('input[name="address_id"]:checked');
      if(!selected){
        e.preventDefault();
        alert('Please add and select a delivery address before continuing to payment.');
        var add=document.querySelector('.add-address-inline');
        if(add){add.setAttribute('open','');add.scrollIntoView({behavior:'smooth',block:'center'});}
        return;
      }
      // Serviceability: validate the SELECTED delivery address's pincode against
      // the active served list. This is accurate for both members and guests
      // (we check where the order will actually be delivered). If it's not a
      // served area, clear the cart and redirect home (auto-close popup).
      if(SERVED.length){
        var selPin=((selected && selected.getAttribute('data-pincode'))||'').replace(/\D+/g,'');
        if(!selPin || SERVED.indexOf(selPin)===-1){
          e.preventDefault();
          agCheckoutPopup('We are currently working to extend our services to your region. Please submit your inquiry via our Contact Us page. Your cart will be cleared and you will be redirected to the home page.');
          return;
        }
      }
    });
  }
  // Coupon apply submits the order form (which carries the coupon field)
  document.getElementById('applyCoupon')?.addEventListener('click',function(){
    var f=document.getElementById('orderForm'); if(f) f.requestSubmit();
  });
  // Handling-charge tooltip
  document.getElementById('handlingInfo')?.addEventListener('click',function(){
    document.getElementById('handlingTip')?.classList.toggle('show');
  });
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
