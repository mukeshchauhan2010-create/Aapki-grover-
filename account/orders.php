<?php
require_once __DIR__.'/../config/db.php'; $pdo=db();
if(!is_logged_in()){ redirect('login.php?return=account/orders.php'); }
$uid=user_id();
$u=$pdo->prepare('SELECT * FROM users WHERE id=?'); $u->execute([$uid]); $u=$u->fetch();

// Orders for this user, newest first
$os=$pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC');
$os->execute([$uid]);
$orders=$os->fetchAll();

// Items grouped per order
$itemsByOrder=[];
if($orders){
    $ids=array_column($orders,'id');
    $in=implode(',',array_fill(0,count($ids),'?'));
    $its=$pdo->prepare('SELECT * FROM order_items WHERE order_id IN('.$in.')');
    $its->execute($ids);
    foreach($its->fetchAll() as $it){ $itemsByOrder[$it['order_id']][]=$it; }
}

$walletBalance=(int)($u['points'] ?? 0);
$title='My Orders | '.APP_NAME; include __DIR__.'/../includes/header.php';

function order_status_class(string $s):string{
    $s=strtolower($s);
    if(in_array($s,['delivered','completed'],true)) return 'ap-badge-green';
    if(in_array($s,['cancelled','failed'],true)) return 'ap-badge-gray';
    return 'ap-badge-blue';
}
?>
<section class="container page account-page">
<div class="account-shell">
<aside class="account-sidebar panel">
  <div class="account-user"><div class="avatar"><?=e(strtoupper(substr(($u['first_name']??$u['name']??'U'),0,1)))?></div><div><b><?=e(trim(($u['first_name']??'').' '.($u['last_name']??''))?:$u['name'])?></b><small><?=e($u['email'])?></small></div></div>
  <a class="wallet-link" href="<?=url('account/#wallet')?>">💰 My Wallet<span class="wallet-badge"><?=number_format($walletBalance)?></span></a>
  <a href="<?=url('account/')?>">👤 My Profile</a>
  <a class="active" href="<?=url('account/orders.php')?>">📦 My Orders</a>
  <a href="<?=url('account/#addresses')?>">📍 My Addresses</a>
  <a href="<?=url('account/#privacy')?>">🔒 Account Privacy</a>
  <a href="<?=url('logout.php')?>">↪ Logout</a>
</aside>
<main class="account-main">
<section class="panel"><div class="panel-head"><div><span class="eyebrow">ORDERS</span><h2>My Orders</h2></div></div>
<?php if(!$orders): ?>
  <p class="hint">You haven't placed any orders yet.</p>
  <a class="btn btn-primary" href="<?=url()?>">Start shopping</a>
<?php else: ?>
  <div class="orders-list">
  <?php foreach($orders as $o): ?>
    <div class="order-card">
      <div class="order-card-head">
        <div>
          <b>#<?=e($o['order_no'])?></b>
          <small class="hint"><?=e(date('d M Y, h:i A', strtotime($o['created_at'])))?></small>
        </div>
        <div class="order-card-status">
          <span class="ap-badge <?=order_status_class($o['status'])?>"><?=e(ucfirst($o['status']))?></span>
          <b>₹<?=number_format((float)$o['total'],2)?></b>
        </div>
      </div>
      <div class="order-card-items">
        <?php foreach(($itemsByOrder[$o['id']] ?? []) as $it): ?>
          <div class="order-item-row">
            <span><?=e($it['product_name'])?><?=$it['variant_label']?' · '.e($it['variant_label']):''?> × <?=e((string)(0+$it['qty']))?></span>
            <span>₹<?=number_format((float)$it['line_total'],2)?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="order-card-foot">
        <span class="hint">Payment: <?=e(strtoupper($o['payment_method']))?> · <?=e(ucfirst($o['payment_status']))?></span>
        <span class="hint">Slot: <?=e($o['delivery_slot']?:'Any available slot')?></span>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>
</main></div></section>
<?php include __DIR__.'/../includes/footer.php'; ?>
