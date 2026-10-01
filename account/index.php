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
}
$walletBalance=(float)($u['wallet_balance'] ?? 0);
$title='My Account | '.APP_NAME; include __DIR__.'/../includes/header.php';
?>
<section class="container page account-page">
<div class="account-shell">
<aside class="account-sidebar panel">
  <div class="account-user"><div class="avatar"><?=e(strtoupper(substr(($u['first_name']??$u['name']??'U'),0,1)))?></div><div><b><?=e(trim(($u['first_name']??'').' '.($u['last_name']??''))?:$u['name'])?></b><small><?=e($u['email'])?></small></div></div>
  <a class="wallet-link" href="<?=url('account/wallet.php')?>">💰 My Wallet<span class="wallet-badge">₹<?=number_format($walletBalance,0)?></span></a>
  <a class="active" href="<?=url('account/')?>">👤 My Profile</a>
  <a href="<?=url('account/orders.php')?>">📦 My Orders</a>
  <a href="#addresses">📍 My Addresses</a>
  <a href="#privacy">🔒 Account Privacy</a>
  <a href="<?=url('logout.php')?>">↪ Logout</a>
</aside>
<main class="account-main">
<section class="panel" id="profile"><div class="panel-head"><div><span class="eyebrow">MY PROFILE</span><h2>Personal details</h2></div></div>
<form method="post" class="account-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="profile" value="1">
<div class="field-grid"><label>First name *<input name="first_name" value="<?=e($u['first_name']??$u['name']??'')?>" required></label><label>Last name<input name="last_name" value="<?=e($u['last_name']??'')?>"></label></div>
<div class="field-grid"><label>Phone number <div class="phone-row"><input id="accountPhone" name="phone" value="<?=e($u['phone']??'')?>" inputmode="numeric"><button type="button" class="btn otp-btn" id="sendOtp">Verify OTP</button></div><small id="otpMsg" class="hint"></small></label><label>Email<input value="<?=e($u['email']??'')?>" disabled></label></div>
<button class="btn btn-primary">Save changes</button></form></section>

<section class="panel" id="wallet"><div class="wallet-card"><div class="wallet-icon">💰</div><div><small>MY WALLET</small><strong>₹<?=number_format($walletBalance,2)?></strong></div></div><p class="hint">Use your wallet balance to pay at checkout. <a href="<?=url('account/wallet.php')?>">Open My Wallet →</a></p></section>

<section class="panel" id="addresses"><div class="panel-head"><div><span class="eyebrow">DELIVERY</span><h2>My addresses</h2></div></div>
<?php if($addresses): ?>
<?php foreach($addresses as $a): ?><div class="saved-address"><div><b><?=e($a['address_type']??$a['label'])?></b><?php if($a['is_default']): ?><span class="default-badge">Default</span><?php endif; ?><p><?=e($a['name'])?> · <?=e($a['phone'])?><?=!empty($a['alt_phone'])?' · Alt: '.e($a['alt_phone']):''?><br><?=e($a['apartment_no'])?> <?=e($a['apartment_name'])?>, <?=e($a['area'])?><br><?=e($a['address_line'])?> <?=e($a['landmark']?' · '.$a['landmark']:'')?><br><?=e($a['city'])?>, <?=e($a['state'])?> - <?=e($a['pincode'])?></p></div></div><?php endforeach; ?>
<?php else: ?><p class="hint">No delivery address saved yet. You can add one during checkout.</p><?php endif; ?>
</section>

<section class="panel" id="privacy"><h2>Account Privacy</h2><p class="hint">Your account information is used to manage orders, delivery and customer support. You can contact us to request account changes.</p></section>
</main></div></section>
<script>
const otp=document.getElementById('sendOtp');otp?.addEventListener('click',async()=>{const phone=document.getElementById('accountPhone').value.trim();const m=document.getElementById('otpMsg');if(phone.length<10){m.textContent='Enter a valid mobile number first.';return;}m.textContent='OTP request prepared. Connect your SMS provider in the OTP API before live use.';});
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
