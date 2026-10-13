<?php
require_once __DIR__.'/../config/db.php'; $pdo=db();
if(!is_logged_in()){ redirect('login.php?return=account/refer.php'); }
// If referral is turned off in admin, this page isn't available.
if(!setting_bool($pdo,'referral_enabled')){ flash('error','Refer & Earn is not available right now.'); redirect('account/'); }

$uid=user_id();
$u=$pdo->prepare('SELECT * FROM users WHERE id=?'); $u->execute([$uid]); $u=$u->fetch();

// Ensure the user has a referral code.
$code = trim((string)($u['referral_code'] ?? ''));
if($code===''){
    $code='AG'.strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare('UPDATE users SET referral_code=? WHERE id=?')->execute([$code,$uid]);
}
$refPts=(int)setting($pdo,'referral_points','10');
$referLink = url('register.php?ref='.rawurlencode($code));
$walletBalance=(float)($u['wallet_balance'] ?? 0);

$title='Refer & Earn | '.APP_NAME; include __DIR__.'/../includes/header.php';
?>
<section class="container page account-page">
<div class="account-shell">
<aside class="account-sidebar panel">
  <div class="account-user"><div class="avatar"><?=e(strtoupper(substr(($u['first_name']??$u['name']??'U'),0,1)))?></div><div><b><?=e(trim(($u['first_name']??'').' '.($u['last_name']??''))?:$u['name'])?></b><small><?=e($u['email'])?></small></div></div>
  <a class="wallet-link" href="<?=url('account/wallet.php')?>">💰 My Wallet<span class="wallet-badge">₹<?=number_format($walletBalance,0)?></span></a>
  <a href="<?=url('account/')?>">👤 My Profile</a>
  <a href="<?=url('account/orders.php')?>">📦 My Orders</a>
  <a href="<?=url('account/#addresses')?>">📍 My Addresses</a>
  <a class="active refer-link" href="<?=url('account/refer.php')?>">🎁 Refer &amp; Earn<small>Earn ₹<?=number_format($refPts)?> per referral on their first order</small></a>
  <a href="<?=url('account/#privacy')?>">🔒 Account Privacy</a>
  <a href="<?=url('logout.php')?>">↪ Logout</a>
</aside>
<main class="account-main">
<section class="panel">
  <div class="panel-head"><div><span class="eyebrow">REFER &amp; EARN</span><h2>Invite friends, earn rewards</h2></div></div>
  <div class="refer-hero">
    <div class="refer-hero-icon">🎁</div>
    <div><small>YOU EARN</small><strong>₹<?=number_format($refPts)?></strong><span>per friend, on their first order</span></div>
  </div>
  <p class="hint">Share your referral code with friends. When a new customer signs up using your code and completes their first order, you earn <b>₹<?=number_format($refPts)?></b> in your wallet.</p>

  <h3>Your referral code</h3>
  <div class="refer-code-row">
    <input id="refCode" readonly value="<?=e($code)?>">
    <button type="button" class="btn btn-primary" id="copyCode">Copy code</button>
  </div>

  <h3>Your referral link</h3>
  <div class="refer-code-row">
    <input id="refLink" readonly value="<?=e($referLink)?>">
    <button type="button" class="btn" id="copyLink">Copy link</button>
  </div>

  <div class="refer-share">
    <a class="btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?=rawurlencode('Shop fresh with Aapki Grocery! Use my code '.$code.' and sign up here: '.$referLink)?>">Share on WhatsApp</a>
  </div>
</section>
</main></div></section>
<script>
function agCopy(id,btn){var el=document.getElementById(id);el.select();try{document.execCommand('copy');}catch(e){}navigator.clipboard&&navigator.clipboard.writeText(el.value);var t=btn.textContent;btn.textContent='Copied ✓';setTimeout(function(){btn.textContent=t;},1500);}
document.getElementById('copyCode').addEventListener('click',function(){agCopy('refCode',this);});
document.getElementById('copyLink').addEventListener('click',function(){agCopy('refLink',this);});
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
