<?php
/**
 * Admin — create a new customer (for phone / WhatsApp orders).
 *
 * Staff takes the customer's details over the phone/WhatsApp and creates the
 * user here (optionally with a first delivery address). After saving they can
 * jump straight to "Create Order" for this customer.
 */
require_once __DIR__.'/../config/db.php';
$pdo = db();
if (!user_can($pdo, 'users')) redirect('admin/');

$err = '';
$old = ['first_name'=>'','last_name'=>'','phone'=>'','email'=>'','role_slug'=>'customer',
        'apartment_no'=>'','apartment_name'=>'','area'=>'','landmark'=>'','address_line'=>'',
        'city'=>'Delhi','state'=>'Delhi','pincode'=>'','address_type'=>'Home'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    foreach ($old as $k => $_) { $old[$k] = trim($_POST[$k] ?? $old[$k]); }

    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = (string)($_POST['password'] ?? '');
    $role  = trim($_POST['role_slug'] ?? 'customer') ?: 'customer';
    $addAddr = !empty($_POST['add_address']);

    // ── Validation ──
    if ($first === '') {
        $err = 'First name is required.';
    } elseif ($phone !== '' && !preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        $err = 'Enter a valid 10-digit mobile number.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Enter a valid email address (or leave it blank).';
    }

    // Phone/email uniqueness (so staff don't create duplicates).
    if (!$err && $phone !== '') {
        $c = $pdo->prepare('SELECT id FROM users WHERE phone=? LIMIT 1'); $c->execute([$phone]);
        if ($c->fetchColumn()) $err = 'A user with this mobile number already exists.';
    }
    if (!$err && $email !== '') {
        $c = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1'); $c->execute([$email]);
        if ($c->fetchColumn()) $err = 'A user with this email already exists.';
    }

    // Address fields are required only when "also add address" is ticked.
    if (!$err && $addAddr) {
        $apt=trim($_POST['apartment_no']??''); $area=trim($_POST['area']??'');
        $street=trim($_POST['address_line']??''); $city=trim($_POST['city']??'');
        $state=trim($_POST['state']??''); $pin=preg_replace('/\D+/','',$_POST['pincode']??'');
        if ($apt==='' || $area==='' || $street==='' || $city==='' || $state==='') {
            $err = 'Please fill all required address fields (or untick "add address").';
        } elseif (!preg_match('/^[1-9][0-9]{5}$/', $pin)) {
            $err = 'Enter a valid 6-digit pincode for the address.';
        } elseif ($phone === '') {
            $err = 'A mobile number is required to save a delivery address.';
        }
    }

    if (!$err) {
        try {
            $pdo->beginTransaction();
            $name = trim($first.' '.$last);
            $hash = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null;
            $code = 'AG'.strtoupper(bin2hex(random_bytes(4)));
            $st = $pdo->prepare(
                'INSERT INTO users(name,first_name,last_name,email,phone,password_hash,provider,role_slug,referral_code,is_active,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,\'local\',?,?,1,NOW(),NOW())'
            );
            $st->execute([$name,$first,$last?:null,$email?:null,$phone?:null,$hash,$role,$code]);
            $uid = (int)$pdo->lastInsertId();

            if ($addAddr) {
                $type = in_array($_POST['address_type']??'Home',['Home','Office','Other'],true) ? $_POST['address_type'] : 'Home';
                $as = $pdo->prepare(
                    'INSERT INTO addresses(user_id,label,name,phone,alt_phone,apartment_no,apartment_name,area,landmark,address_line,city,state,pincode,address_type,is_default)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
                );
                $as->execute([
                    $uid, $type, $name, $phone,
                    preg_replace('/\D+/','',$_POST['alt_phone']??'')?:null,
                    trim($_POST['apartment_no']??''), trim($_POST['apartment_name']??''),
                    trim($_POST['area']??''), trim($_POST['landmark']??''),
                    trim($_POST['address_line']??''), trim($_POST['city']??''),
                    trim($_POST['state']??''), preg_replace('/\D+/','',$_POST['pincode']??''), $type,
                ]);
            }
            $pdo->commit();
            flash('success', 'Customer "'.$name.'" created.');
            // Offer a direct jump to create an order for this new customer.
            redirect('admin/order-add.php?user_id='.$uid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'Could not create user: '.$e->getMessage();
        }
    }
}

$roles = $pdo->query('SELECT slug, name FROM roles ORDER BY id')->fetchAll();

$adminTitle   = 'Add New User';
$adminSection = 'users';
$adminCrumbs  = [['label'=>'Users & Roles','url'=>url('admin/users.php')], ['label'=>'Add New User']];
include __DIR__.'/../includes/admin-header.php';
?>

<?php if ($err): ?><div class="ap-alert"><?= e($err) ?></div><?php endif; ?>

<div class="ap-card">
  <div class="ap-card-header">
    <h2 class="ap-card-title">Add new customer</h2>
    <div style="display:flex;gap:8px;">
      <a class="ap-btn ap-btn-sm" href="<?= url('admin/users.php') ?>">👥 User List</a>
    </div>
  </div>
  <div class="ap-card-body">
    <form class="ap-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="ap-section-title">Customer details</div>
      <div class="ap-form-grid-3">
        <div class="ap-field">
          <label class="ap-label">First name *</label>
          <input class="ap-input" name="first_name" value="<?= e($old['first_name']) ?>" required>
        </div>
        <div class="ap-field">
          <label class="ap-label">Last name</label>
          <input class="ap-input" name="last_name" value="<?= e($old['last_name']) ?>">
        </div>
        <div class="ap-field">
          <label class="ap-label">Mobile number</label>
          <input class="ap-input" name="phone" inputmode="numeric" maxlength="10" value="<?= e($old['phone']) ?>" placeholder="10-digit mobile">
          <span class="ap-hint">Recommended — used for the order & delivery.</span>
        </div>
        <div class="ap-field">
          <label class="ap-label">Email (optional)</label>
          <input class="ap-input" type="email" name="email" value="<?= e($old['email']) ?>" placeholder="name@example.com">
        </div>
        <div class="ap-field">
          <label class="ap-label">Password (optional)</label>
          <input class="ap-input" type="text" name="password" placeholder="Leave blank for phone-order customers">
          <span class="ap-hint">Set only if the customer wants to log in later.</span>
        </div>
        <div class="ap-field">
          <label class="ap-label">Role</label>
          <select class="ap-select" name="role_slug">
            <?php foreach ($roles as $r): ?>
              <option value="<?= e($r['slug']) ?>" <?= ($old['role_slug']===$r['slug'] || ($old['role_slug']===''&&$r['slug']==='customer')) ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="ap-section-title">Delivery address (optional)</div>
      <label class="ap-check-row" style="margin-bottom:10px;">
        <input type="checkbox" name="add_address" value="1" id="addAddrChk" <?= !empty($_POST['add_address'])?'checked':'' ?>>
        Also add a delivery address for this customer now
      </label>
      <div id="addrFields">
        <div class="ap-form-grid-3">
          <div class="ap-field"><label class="ap-label">Flat / House no. *</label><input class="ap-input" name="apartment_no" value="<?= e($old['apartment_no']) ?>"></div>
          <div class="ap-field"><label class="ap-label">Building / Apartment</label><input class="ap-input" name="apartment_name" value="<?= e($old['apartment_name']) ?>"></div>
          <div class="ap-field"><label class="ap-label">Area / Locality *</label><input class="ap-input" name="area" value="<?= e($old['area']) ?>"></div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-field"><label class="ap-label">Street / Address line *</label><input class="ap-input" name="address_line" value="<?= e($old['address_line']) ?>"></div>
          <div class="ap-field"><label class="ap-label">Landmark</label><input class="ap-input" name="landmark" value="<?= e($old['landmark']) ?>"></div>
        </div>
        <div class="ap-form-grid-3">
          <div class="ap-field"><label class="ap-label">City *</label><input class="ap-input" name="city" value="<?= e($old['city']) ?>"></div>
          <div class="ap-field"><label class="ap-label">State *</label><input class="ap-input" name="state" value="<?= e($old['state']) ?>"></div>
          <div class="ap-field"><label class="ap-label">Pincode *</label><input class="ap-input" name="pincode" inputmode="numeric" maxlength="6" value="<?= e($old['pincode']) ?>"></div>
        </div>
        <div class="ap-form-grid-3">
          <div class="ap-field"><label class="ap-label">Alternate mobile</label><input class="ap-input" name="alt_phone" inputmode="numeric" maxlength="10"></div>
          <div class="ap-field"><label class="ap-label">Address type</label>
            <select class="ap-select" name="address_type">
              <option>Home</option><option>Office</option><option>Other</option>
            </select>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px;">
        <button class="ap-btn ap-btn-primary" style="min-width:200px;">💾 Create customer</button>
        <a class="ap-btn" href="<?= url('admin/users.php') ?>">Cancel</a>
      </div>
      <span class="ap-hint" style="display:block;margin-top:8px;">After saving you'll be taken to "Create Order" for this customer.</span>
    </form>
  </div>
</div>

<script>
(function(){
  var chk=document.getElementById('addAddrChk'), box=document.getElementById('addrFields');
  function sync(){ box.style.display = chk.checked ? '' : 'none'; }
  chk.addEventListener('change', sync); sync();
})();
</script>

<?php include __DIR__.'/../includes/admin-footer.php'; ?>
