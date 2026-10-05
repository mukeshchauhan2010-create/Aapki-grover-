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
    if(isset($_POST['delete_account'])){
        $reason=trim($_POST['delete_reason']??'');
        if($reason===''){ flash('error','Please tell us the reason for deleting your account.'); redirect('account/#privacy'); }
        // Soft delete: deactivate the account and store the reason.
        // Orders and payment records are intentionally NOT deleted.
        $st=$pdo->prepare('UPDATE users SET is_active=0, remarks=? WHERE id=?');
        $st->execute([$reason,$uid]);
        session_unset(); session_destroy();
        // Start a fresh session just to carry the flash message to the home page.
        session_start();
        flash('success','Your account has been deleted. We are sorry to see you go.');
        redirect('/');
    }
}

// Phone number shown on the account page = the first (default) delivery address
// mobile, falling back to the user's own phone number.
$primaryDeliveryPhone = '';
if($addresses){ $primaryDeliveryPhone = trim((string)($addresses[0]['phone'] ?? '')); }
$accountPhone = $primaryDeliveryPhone !== '' ? $primaryDeliveryPhone : (string)($u['phone'] ?? '');

$walletBalance=(float)($u['wallet_balance'] ?? 0);
$avatar = trim((string)($u['avatar'] ?? '')) ?: null;
$initial = strtoupper(substr(trim((string)($u['first_name']??$u['name']??'U')),0,1));
$title='My Account | '.APP_NAME; include __DIR__.'/../includes/header.php';
?>
<section class="container page account-page">
<div class="account-shell">
<aside class="account-sidebar panel">
  <div class="account-user">
    <div class="avatar<?=$avatar?' avatar-photo':''?>">
      <?php if($avatar): ?><img src="<?=e($avatar)?>" alt="" referrerpolicy="no-referrer" onerror="this.onerror=null;this.parentNode.classList.remove('avatar-photo');this.parentNode.textContent='<?=e($initial)?>';"><?php else: ?><?=e($initial)?><?php endif; ?>
    </div>
    <div><b><?=e(trim(($u['first_name']??'').' '.($u['last_name']??''))?:$u['name'])?></b><small><?=e($u['email'])?></small></div>
  </div>
  <a class="wallet-link" href="<?=url('account/wallet.php')?>">💰 My Wallet<span class="wallet-badge">₹<?=number_format($walletBalance,0)?></span></a>
  <a class="active" href="<?=url('account/')?>">👤 Profile</a>
  <a href="<?=url('account/orders.php')?>">📦 My Orders</a>
  <a href="#addresses">📍 Delivery Addresses</a>
  <a href="#privacy">🔒 Account Privacy</a>
  <a href="<?=url('logout.php')?>">↪ Logout</a>
  <a class="btn btn-primary account-shop-btn" href="<?=url()?>">🛒 Buy Aapki Grocery</a>
</aside>
<main class="account-main">
<section class="panel" id="profile"><div class="panel-head"><div><h2>Personal details</h2></div></div>
<form method="post" class="account-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="profile" value="1">
<div class="field-grid"><label>First name *<input name="first_name" value="<?=e($u['first_name']??$u['name']??'')?>" required></label><label>Last name<input name="last_name" value="<?=e($u['last_name']??'')?>"></label></div>
<div class="field-grid"><label>Phone number <div class="phone-row"><input id="accountPhone" name="phone" value="<?=e($accountPhone)?>" inputmode="numeric"><button type="button" class="btn otp-btn" id="sendOtp">Verify OTP</button></div><small id="otpMsg" class="hint"><?= $primaryDeliveryPhone!=='' ? 'Showing your primary delivery mobile number.' : '' ?></small></label><label>Email<input value="<?=e($u['email']??'')?>" disabled></label></div>
<button class="btn btn-primary">Save changes</button></form></section>

<section class="panel" id="addresses"><div class="panel-head"><div><h2>Delivery Addresses</h2></div></div>
<?php if($addresses): ?>
<?php foreach($addresses as $a): ?><div class="saved-address"><div><b><?=e($a['address_type']??$a['label'])?></b><?php if($a['is_default']): ?><span class="default-badge">Default</span><?php endif; ?><p><?=e($a['name'])?> · <?=e($a['phone'])?><?=!empty($a['alt_phone'])?' · Alt: '.e($a['alt_phone']):''?><br><?=e($a['apartment_no'])?> <?=e($a['apartment_name'])?>, <?=e($a['area'])?><br><?=e($a['address_line'])?> <?=e($a['landmark']?' · '.$a['landmark']:'')?><br><?=e($a['city'])?>, <?=e($a['state'])?> - <?=e($a['pincode'])?></p></div></div><?php endforeach; ?>
<?php else: ?><p class="hint">No delivery address saved yet. You can add one during checkout.</p><?php endif; ?>
</section>

<section class="panel" id="privacy"><h2>Account Privacy</h2>
<p class="hint">Your account information is used to manage orders, delivery and customer support. You can <b>'Delete'</b> your account.</p>
<button type="button" class="btn btn-danger" id="openDeleteModal">Delete Account</button>
</section>
</main></div></section>

<!-- Delete account modal -->
<div class="modal" id="deleteModal" style="display:none;" aria-modal="true" role="dialog" aria-labelledby="deleteTitle">
  <div class="modal-card">
    <button class="modal-close" id="deleteClose" aria-label="Close">×</button>
    <span class="eyebrow">ACCOUNT DELETION</span>
    <h2 id="deleteTitle">Delete your account</h2>
    <p class="hint">We're sorry to see you go. Please tell us why you're deleting your account. Your past orders and payment records are preserved.</p>
    <form method="post" class="account-form">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="delete_account" value="1">
      <label>Reason for deletion *<textarea name="delete_reason" rows="4" required placeholder="Please share your reason..."></textarea></label>
      <div class="modal-actions">
        <button type="button" class="btn" id="deleteCancel">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm deletion</button>
      </div>
    </form>
  </div>
</div>

<script>
const otp=document.getElementById('sendOtp');otp?.addEventListener('click',async()=>{const phone=document.getElementById('accountPhone').value.trim();const m=document.getElementById('otpMsg');if(phone.length<10){m.textContent='Enter a valid mobile number first.';return;}m.textContent='OTP request prepared. Connect your SMS provider in the OTP API before live use.';});
(function(){
  var modal=document.getElementById('deleteModal');
  function open(){modal.style.display='grid';}
  function close(){modal.style.display='none';}
  document.getElementById('openDeleteModal')?.addEventListener('click',open);
  document.getElementById('deleteClose')?.addEventListener('click',close);
  document.getElementById('deleteCancel')?.addEventListener('click',close);
  modal?.addEventListener('click',function(e){if(e.target===modal)close();});
})();
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
