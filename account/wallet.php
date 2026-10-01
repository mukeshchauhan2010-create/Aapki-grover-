<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/wallet.php';
$pdo=db();
if(!is_logged_in()){ redirect('login.php?return=account/wallet.php'); }
$uid=user_id();
$u=$pdo->prepare('SELECT * FROM users WHERE id=?'); $u->execute([$uid]); $u=$u->fetch();

$razorpayLive = setting_bool($pdo,'razorpay_enabled') && RAZORPAY_KEY_ID && RAZORPAY_KEY_SECRET;

$balance = wallet_balance($pdo,$uid);
$txns    = wallet_transactions($pdo,$uid,50);

$title='My Wallet | '.APP_NAME; include __DIR__.'/../includes/header.php';
?>
<section class="container page account-page">
<div class="account-shell">
<aside class="account-sidebar panel">
  <div class="account-user"><div class="avatar"><?=e(strtoupper(substr(($u['first_name']??$u['name']??'U'),0,1)))?></div><div><b><?=e(trim(($u['first_name']??'').' '.($u['last_name']??''))?:$u['name'])?></b><small><?=e($u['email'])?></small></div></div>
  <a class="wallet-link active" href="<?=url('account/wallet.php')?>">💰 My Wallet<span class="wallet-badge">₹<?=number_format($balance,0)?></span></a>
  <a href="<?=url('account/')?>">👤 My Profile</a>
  <a href="<?=url('account/orders.php')?>">📦 My Orders</a>
  <a href="<?=url('account/#addresses')?>">📍 My Addresses</a>
  <a href="<?=url('account/#privacy')?>">🔒 Account Privacy</a>
  <a href="<?=url('logout.php')?>">↪ Logout</a>
</aside>
<main class="account-main">

<section class="panel">
  <div class="panel-head"><div><span class="eyebrow">MY WALLET</span><h2>Wallet balance</h2></div></div>
  <div class="wallet-hero">
    <div class="wallet-hero-icon">💰</div>
    <div><small>AVAILABLE BALANCE</small><strong>₹<?=number_format($balance,2)?></strong></div>
  </div>
  <p class="hint">Use your wallet balance to pay at checkout. Money can be added by top-up or credited by Aapki Grocery (refunds, cashback, promotions).</p>
</section>

<section class="panel" id="topup">
  <div class="panel-head"><div><span class="eyebrow">ADD MONEY</span><h2>Top up your wallet</h2></div></div>
  <?php if($razorpayLive): ?>
    <form method="post" action="<?=url('account/wallet-topup.php')?>" class="account-form wallet-topup-form">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <label>Amount (₹)<input name="amount" type="number" min="10" step="1" placeholder="Enter amount, e.g. 500" required></label>
      <div class="wallet-quick">
        <?php foreach([100,250,500,1000] as $q): ?><button type="button" class="btn wallet-quick-btn" data-amt="<?=$q?>">+₹<?=$q?></button><?php endforeach; ?>
      </div>
      <button class="btn btn-primary">Proceed to pay</button>
    </form>
    <script>
    document.querySelectorAll('.wallet-quick-btn').forEach(function(b){b.addEventListener('click',function(){var i=document.querySelector('.wallet-topup-form [name=amount]');i.value=(parseInt(i.value||'0',10)+parseInt(b.dataset.amt,10));});});
    </script>
  <?php else: ?>
    <div class="wallet-topup-disabled">
      <p>💳 Self top-up will be available once online payments (Razorpay) are enabled by Aapki Grocery.</p>
      <p class="hint">For now, wallet balance can be credited by our team (refunds, cashback and promotions). Please contact support if you need help.</p>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head"><div><span class="eyebrow">HISTORY</span><h2>Transactions</h2></div></div>
  <?php if(!$txns): ?>
    <p class="hint">No wallet transactions yet.</p>
  <?php else: ?>
    <div class="wallet-txns">
      <?php foreach($txns as $t): $credit=$t['type']==='credit'; ?>
        <div class="wallet-txn">
          <div class="wallet-txn-main">
            <b><?=e($t['reason'])?></b>
            <small class="hint"><?=e(date('d M Y, h:i A', strtotime($t['created_at'])))?><?=$t['order_id']?' · Order #'.e((string)$t['order_id']):''?></small>
          </div>
          <div class="wallet-txn-amt <?=$credit?'credit':'debit'?>">
            <?=$credit?'+':'−'?>₹<?=number_format((float)$t['amount'],2)?>
            <small class="hint">Bal ₹<?=number_format((float)$t['balance_after'],2)?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

</main></div></section>
<?php include __DIR__.'/../includes/footer.php'; ?>
