<?php
require_once __DIR__.'/config/db.php';
$pdo=db();

// Safe, local, relative return path.
$return = trim($_GET['return'] ?? '');
if($return!=='' && (preg_match('~^(https?:)?//~i',$return) || str_starts_with($return,'/') || str_contains($return,'..'))) $return='';
$returnQS = $return!=='' ? ('?return='.rawurlencode($return)) : '';

if(is_logged_in()) redirect($return!==''?$return:'account/');

$title='Create account | '.APP_NAME;
$error='';
$old=['first_name'=>'','last_name'=>'','email'=>'','phone'=>''];

if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf($_POST['csrf']??null)){
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/\D+/','',$_POST['phone'] ?? '');
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password_confirm'] ?? '');
    $old=['first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone];

    if($first===''){ $error='Please enter your first name.'; }
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){ $error='Please enter a valid email address.'; }
    elseif(!preg_match('/^[6-9]\d{9}$/',$phone)){ $error='Please enter a valid 10-digit mobile number.'; }
    elseif(strlen($pass)<6){ $error='Password must be at least 6 characters.'; }
    elseif($pass!==$pass2){ $error='Passwords do not match.'; }
    else{
        $chk=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $chk->execute([$email]);
        if($chk->fetch()){
            $error='An account with this email already exists. Please login instead.';
        } else {
            $code='AG'.strtoupper(bin2hex(random_bytes(4)));
            $hash=password_hash($pass,PASSWORD_DEFAULT);
            $name=trim($first.' '.$last);
            $st=$pdo->prepare('INSERT INTO users(name,first_name,last_name,email,phone,password_hash,provider,role_slug,referral_code) VALUES(?,?,?,?,?,?, "local","customer",?)');
            $st->execute([$name,$first,$last?:null,$email,$phone?:null,$hash,$code]);
            $uid=(int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id']=$uid;
            $_SESSION['role']='customer';
            flash('success','Welcome to Aapki Grocery! Your account is ready.');
            redirect($return!==''?$return:'account/');
        }
    }
}

$googleEnabled = GOOGLE_CLIENT_ID && GOOGLE_CLIENT_SECRET;
include __DIR__.'/includes/header.php';
?>
<section class="container auth">
  <div class="auth-card">
    <div class="auth-logo"><img src="<?=url('assets/logo.png')?>" alt="<?=e(APP_NAME)?>"></div>

    <p class="auth-lead">Login/ Sign up with Google.</p>

    <?php if($googleEnabled): ?>
    <a class="social-btn google-btn" href="<?=url('auth/google.php'.$returnQS)?>" data-google-login>
      <img class="social-ico-img" src="<?=url('assets/images/google-g.svg')?>" alt="" aria-hidden="true">
      <span>Sign up with Google</span>
    </a>
    <div class="divider">or with email</div>
    <?php endif; ?>

    <?php if($error): ?><div class="alert"><?=e($error)?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <div class="field-grid">
        <label>First name *<input name="first_name" value="<?=e($old['first_name'])?>" required></label>
        <label>Last name<input name="last_name" value="<?=e($old['last_name'])?>"></label>
      </div>
      <label>Email *<input type="email" name="email" value="<?=e($old['email'])?>" required></label>
      <label>Mobile number *<input name="phone" value="<?=e($old['phone'])?>" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" placeholder="10-digit mobile number" required></label>
      <div class="field-grid">
        <label>Password *<input type="password" name="password" minlength="6" required></label>
        <label>Confirm password *<input type="password" name="password_confirm" minlength="6" required></label>
      </div>
      <button class="btn btn-primary full">Create account</button>
    </form>

    <p class="auth-hint">Already have an account? <a href="<?=url('login.php'.$returnQS)?>">Login</a></p>

    <?php include __DIR__.'/includes/auth-legal.php'; ?>
  </div>
</section>
<?php include __DIR__.'/includes/auth-popup.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
