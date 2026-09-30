<?php
require_once __DIR__.'/../config/db.php'; $pdo=db();
if(!is_logged_in()){ redirect('login.php?return=account/'); }
$uid=user_id();
$s=$pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$uid]); $u=$s->fetch();
$addressesStmt=$pdo->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC,id DESC'); $addressesStmt->execute([$uid]); $addresses=$addressesStmt->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf($_POST['csrf']??null)){
    if(isset($_POST['profile'])){
        $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $phone=preg_replace('/\D+/','',$_POST['phone']??'');
        if($first===''){flash('error','First name is required.'); redirect('account/');}
        $st=$pdo->prepare('UPDATE users SET first_name=?,last_name=?,name=?,phone=? WHERE id=?');
        $st->execute([$first,$last,trim($first.' '.$last),$phone?:null,$uid]); flash('success','Profile updated.'); redirect('account/');
    }
    if(isset($_POST['address'])){
        $type=in_array($_POST['address_type']??'Home',['Home','Office','Other'],true)?$_POST['address_type']:'Home';
        $isDefault=!empty($_POST['is_default'])?1:0;
        if($isDefault){$pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=?')->execute([$uid]);}
        $st=$pdo->prepare('INSERT INTO addresses(user_id,label,name,phone,alt_phone,apartment_no,apartment_name,area,landmark,address_line,city,state,pincode,address_type,is_default) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$uid,$type,trim(($u['first_name']??$u['name']).' '.($u['last_name']??'')),trim($_POST['phone']??$u['phone']??''),preg_replace('/\D+/','',$_POST['alt_phone']??'')?:null,trim($_POST['apartment_no']??''),trim($_POST['apartment_name']??''),trim($_POST['area']??''),trim($_POST['landmark']??''),trim($_POST['street_details']??''),trim($_POST['city']??''),trim($_POST['state']??''),trim($_POST['pincode']??''),$type,$isDefault]);
        flash('success','Address added.'); redirect('account/');
    }
}
$title='My Account | '.APP_NAME; include __DIR__.'/../includes/header.php';
?>
<section class="container page account-page">
<div class="account-shell">
<aside class="account-sidebar panel">
  <div class="account-user"><div class="avatar"><?=e(strtoupper(substr(($u['first_name']??$u['name']??'U'),0,1)))?></div><div><b><?=e(trim(($u['first_name']??'').' '.($u['last_name']??''))?:$u['name'])?></b><small><?=e($u['email'])?></small></div></div>
  <a class="active" href="<?=url('account/')?>">👤 My Profile</a><a href="#points">⭐ My Mobile & Points</a><a href="#orders">📦 My Orders</a><a href="#addresses">📍 My Addresses</a><a href="#privacy">🔒 Account Privacy</a><a href="<?=url('logout.php')?>">↪ Logout</a>
</aside>
<main class="account-main">
<section class="panel" id="profile"><div class="panel-head"><div><span class="eyebrow">MY PROFILE</span><h2>Personal details</h2></div></div>
<form method="post" class="account-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="profile" value="1">
<div class="field-grid"><label>First name *<input name="first_name" value="<?=e($u['first_name']??$u['name']??'')?>" required></label><label>Last name<input name="last_name" value="<?=e($u['last_name']??'')?>"></label></div>
<div class="field-grid"><label>Phone number <div class="phone-row"><input id="accountPhone" name="phone" value="<?=e($u['phone']??'')?>" inputmode="numeric"><button type="button" class="btn otp-btn" id="sendOtp">Verify OTP</button></div><small id="otpMsg" class="hint"></small></label><label>Email<input value="<?=e($u['email']??'')?>" disabled></label></div>
<button class="btn btn-primary">Save changes</button></form></section>
<section class="panel" id="points"><div class="points-card"><span>⭐</span><div><small>MY POINTS</small><strong><?=number_format((int)$u['points'])?> Points</strong></div></div><p class="hint">Use points at checkout when your balance reaches the minimum set by Aapki Grocery.</p></section>
<section class="panel" id="addresses"><div class="panel-head"><div><span class="eyebrow">DELIVERY</span><h2>My addresses</h2></div></div>
<?php foreach($addresses as $a): ?><div class="saved-address"><div><b><?=e($a['address_type']??$a['label'])?></b><?php if($a['is_default']): ?><span class="default-badge">Default</span><?php endif; ?><p><?=e($a['name'])?> · <?=e($a['phone'])?><?=!empty($a['alt_phone'])?' · Alt: '.e($a['alt_phone']):''?><br><?=e($a['apartment_no'])?> <?=e($a['apartment_name'])?>, <?=e($a['area'])?><br><?=e($a['address_line'])?> <?=e($a['landmark']?' · '.$a['landmark']:'')?><br><?=e($a['city'])?>, <?=e($a['state'])?> - <?=e($a['pincode'])?></p></div></div><?php endforeach; ?>
<h3>Add delivery address</h3><form method="post" class="address-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="address" value="1">
<div class="location-row"><button type="button" class="btn" id="useLocation">📍 Use current location</button><span id="locationStatus" class="hint">We can prefill your address when browser location permission is available.</span></div>
<div class="field-grid"><label>Apartment / House No.<input name="apartment_no" required></label><label>Apartment name<input name="apartment_name"></label></div><div class="field-grid"><label>Area<input name="area" required></label><label>Street Details / Landmark<input name="landmark"></label></div><label>Street / address details<input name="street_details" required></label><div class="field-grid"><label>City<input id="addrCity" name="city" value="Delhi" required></label><label>State<input id="addrState" name="state" value="Delhi" required></label></div><div class="field-grid"><label>Pincode<input id="addrPincode" name="pincode" inputmode="numeric" required></label><label>Phone number<input name="phone" value="<?=e($u['phone']??'')?>" required></label></div>
<div class="field-grid"><label>Alternate mobile (optional)<input name="alt_phone" inputmode="numeric" placeholder="Backup number for delivery"><small class="hint">Used by the delivery agent if the primary number isn't reachable.</small></label></div>
<div class="radio-row"><span>Address Type</span><label><input type="radio" name="address_type" value="Home" checked> Home</label><label><input type="radio" name="address_type" value="Office"> Office</label><label><input type="radio" name="address_type" value="Other"> Other</label></div><label class="check-row"><input type="checkbox" name="is_default" value="1" <?=empty($addresses)?'checked':''?>> Set this as default address</label><button class="btn btn-primary">Save address</button></form></section>
<section class="panel" id="orders"><h2>My Orders</h2><p class="hint">Your order history will appear here after checkout.</p><a class="btn" href="<?=url('cart/')?>">Go to shopping cart</a></section>
<section class="panel" id="privacy"><h2>Account Privacy</h2><p class="hint">Your account information is used to manage orders, delivery and customer support. You can contact us to request account changes.</p></section>
</main></div></section>
<script>
const locBtn=document.getElementById('useLocation'),locStatus=document.getElementById('locationStatus');
locBtn?.addEventListener('click',()=>{if(!navigator.geolocation){locStatus.textContent='Location is not supported by this browser.';return;}locStatus.textContent='Getting your location…';navigator.geolocation.getCurrentPosition(async p=>{locStatus.textContent='Location found. Please confirm the address fields before saving.';try{const r=await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+encodeURIComponent(p.coords.latitude)+'&lon='+encodeURIComponent(p.coords.longitude),{headers:{'Accept':'application/json'}});const d=await r.json();const a=d.address||{};const map={addrCity:a.city||a.town||a.village||'',addrState:a.state||'',addrPincode:a.postcode||''};Object.entries(map).forEach(([id,v])=>{if(v&&document.getElementById(id))document.getElementById(id).value=v});const area=document.querySelector('[name="area"]');if(area&& !area.value)area.value=a.suburb||a.neighbourhood||a.city_district||'';const street=document.querySelector('[name="street_details"]');if(street&&!street.value)street.value=[a.road,a.house_number].filter(Boolean).join(' ');}catch(e){locStatus.textContent='Location found, but address lookup is unavailable. Please enter address manually.';}},()=>{locStatus.textContent='Location permission was not granted. Please enter the address manually.'},{enableHighAccuracy:false,timeout:8000});});
const otp=document.getElementById('sendOtp');otp?.addEventListener('click',async()=>{const phone=document.getElementById('accountPhone').value.trim();const m=document.getElementById('otpMsg');if(phone.length<10){m.textContent='Enter a valid mobile number first.';return;}m.textContent='OTP request prepared. Connect your SMS provider in the OTP API before live use.';});
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
