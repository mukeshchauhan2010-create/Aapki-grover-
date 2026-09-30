<?php
require_once __DIR__.'/config/db.php';
$pdo=db();

// Capture a safe, local, relative return path so we can send the user back.
$return = trim($_GET['return'] ?? '');
if($return!=='' && (preg_match('~^(https?:)?//~i',$return) || str_starts_with($return,'/') || str_contains($return,'..'))) $return='';
$returnQS = $return!=='' ? ('?return='.rawurlencode($return)) : '';

if(is_logged_in()) redirect($return!==''?$return:'account/');

$title='Login | '.APP_NAME;
$error='';

if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf($_POST['csrf']??null)){
    $email=trim($_POST['email']??'');
    $pass=$_POST['password']??'';
    $s=$pdo->prepare('SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1');
    $s->execute([$email]);
    $u=$s->fetch();
    if($u && $u['password_hash'] && password_verify($pass,$u['password_hash'])){
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$u['id'];
        $_SESSION['role']=$u['role_slug'];
        if($u['role_slug']!=='customer') redirect('admin/');
        redirect($return!==''?$return:'account/');
    }
    $error='Email or password is incorrect.';
}

$googleEnabled = GOOGLE_CLIENT_ID && GOOGLE_CLIENT_SECRET;
include __DIR__.'/includes/header.php';
?>
<section class="container auth">
  <div class="auth-card">
    <span class="eyebrow">WELCOME</span>
    <h1>Continue to Aapki Grocery</h1>
    <p class="auth-sub">Sign in or create your account in seconds — fastest with Google.</p>

    <!-- ── Primary: social login ── -->
    <a class="social-btn google-btn" href="<?=url('auth/google.php'.$returnQS)?>">
      <span class="social-ico" aria-hidden="true">G</span>
      <span>Continue with Google</span>
    </a>
    <?php if(!$googleEnabled): ?>
      <p class="auth-hint">⚠️ Google login isn't configured yet. Add your Google Client ID &amp; Secret in <code>config/config.local.php</code>, or use the registration form below.</p>
    <?php endif; ?>

    <?php if($error): ?><div class="alert"><?=e($error)?></div><?php endif; ?>

    <div class="divider">new here?</div>
    <a class="btn full" href="<?=url('register.php'.$returnQS)?>">Create an account (email &amp; password)</a>

    <!-- ── Secondary: existing email / admin login ── -->
    <details class="auth-more">
      <summary>Login with email &amp; password</summary>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <label>Email<input type="email" name="email" required></label>
        <label>Password<input type="password" name="password" required></label>
        <button class="btn btn-primary full">Login</button>
      </form>
    </details>
  </div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
