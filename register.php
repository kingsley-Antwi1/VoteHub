<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$success = '';
$error = '';
$plans = $db->query("SELECT id, name, price, duration_days, max_elections, max_voters FROM subscription_plans ORDER BY price")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    if ($type === 'other') $type = trim($_POST['custom_type'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $planId = (int)($_POST['plan_id'] ?? 0);

    if (!$name || !$type || !$email || !$adminName || !$adminEmail || !$password) {
        $error = 'All fields marked * are required';
    } else {
        $slug = slugify($name);
        $existing = $db->prepare("SELECT COUNT(*) FROM institutions WHERE slug = ?");
        $existing->execute([$slug]);
        if ((int)$existing->fetchColumn() > 0) {
            $slug .= '-' . random_int(100, 999);
        }

        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO institutions (name, slug, type, location, contact_email, contact_phone, subscription_id, status) VALUES (?,?,?,?,?,?,?,'pending')")
               ->execute([$name, $slug, $type, $location, $email, $phone, $planId ?: null]);
            $instId = $db->lastInsertId();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO institution_admins (institution_id, full_name, email, phone, password, role) VALUES (?,?,?,?,?,'admin')")
               ->execute([$instId, $adminName, $adminEmail, $phone, $hash]);

            $db->commit();
            $success = "Institution registered successfully! Your portal: <a href='" . BASE_URL . "/school/$slug'>" . BASE_URL . "/school/$slug</a>. Awaiting admin approval.";
            if ($planId) {
                $pname = $db->prepare("SELECT name FROM subscription_plans WHERE id = ?");
                $pname->execute([$planId]);
                $success .= " Once approved, login and submit payment for <strong>" . e($pname->fetchColumn()) . "</strong> plan.";
            }
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body class="login-page" style="align-items:flex-start;padding:40px 20px">
<div class="login-box" style="max-width:600px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
    <div><h1 style="margin:0">🏛 VoteHub</h1><p style="margin:4px 0 0">Register your institution</p></div>
    <a href="<?= BASE_URL ?>" class="inst-nav-link" style="padding:4px 12px">Back to VoteHub</a>
  </div>
  <?php if ($success): ?>
  <div class="flash flash-success" style="position:static;margin-bottom:16px"><?= $success ?></div>
  <div style="text-align:center"><a href="<?= BASE_URL ?>" class="btn btn-gold">Back to Home</a></div>
  <?php else: ?>
  <?php if ($error): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off" onsubmit="document.getElementById('regBtn').disabled=true;document.getElementById('regBtn').innerHTML='⏳ Registering...'">
    <?= csrfField() ?>
    <h3 style="color:#c9a127;font-size:.9rem;margin-bottom:12px">Institution Details</h3>
    <div class="row" style="gap:12px">
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Institution Name *</label>
        <input type="text" name="name" class="form-control" required>
      </div></div>
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Type *</label>
        <select name="type" id="instType" class="form-control" required>
          <option value="">Select School type</option>
          <option value="university">University</option>
          <option value="shs">Senior High School</option>
          <option value="other">Other</option>
        </select>
        <input type="text" name="custom_type" id="customType" class="form-control" style="display:none;margin-top:6px" placeholder="Enter institution type">
      </div></div>
    </div>
    <div class="row" style="gap:12px">
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" required>
      </div></div>
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control">
      </div></div>
    </div>
    <div class="form-group">
      <label class="form-label">Location</label>
      <input type="text" name="location" class="form-control" placeholder="City, Region">
    </div>

    <h3 style="color:#c9a127;font-size:.9rem;margin:16px 0 12px">Administrator Account</h3>
    <div class="row" style="gap:12px">
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="admin_name" class="form-control" required>
      </div></div>
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="admin_email" class="form-control" required>
      </div></div>
    </div>
    <div class="form-group">
      <label class="form-label">Password *</label>
      <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
    </div>

    <h3 style="color:#c9a127;font-size:.9rem;margin:16px 0 12px">💎 Choose a Plan</h3>
    <div class="row" style="gap:12px;margin-bottom:16px">
      <?php foreach ($plans as $p): ?>
      <label class="col plan-card" style="min-width:180px;cursor:pointer" data-plan="<?= $p['id'] ?>">
        <div class="card" style="text-align:center;padding:16px;border:2px solid transparent;transition:all .2s">
          <input type="radio" name="plan_id" value="<?= $p['id'] ?>" style="display:none" <?= $p['price'] == 0 ? 'checked' : '' ?>>
          <div style="font-weight:700;color:#c9a127;font-size:.85rem"><?= e($p['name']) ?></div>
          <div style="font-size:1.3rem;font-weight:800;margin:4px 0">
            ₵<?= number_format($p['price'], 2) ?>
            <span style="font-size:.7rem;font-weight:400;color:#8899bb">/<?= $p['duration_days'] ?>d</span>
          </div>
          <div style="font-size:.72rem;color:#8899bb"><?= $p['max_elections'] == 999 ? 'Unlimited' : $p['max_elections'] ?> elections · <?= $p['max_voters'] >= 99999 ? 'Unlimited' : number_format($p['max_voters']) ?> voters</div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <button type="submit" id="regBtn" class="btn btn-gold" style="width:100%;justify-content:center;margin-top:8px">Register Institution</button>
    <p style="text-align:center;margin-top:16px;font-size:.8rem;color:#8899bb">
      Already registered? <a href="<?= BASE_URL ?>/auth/inst-login.php">Login here</a>
    </p>
  </form>
  <?php endif; ?>
</div>
<script>
document.getElementById('instType').addEventListener('change', function() {
  var ct = document.getElementById('customType');
  ct.style.display = this.value === 'other' ? 'block' : 'none';
  if (this.value === 'other') { ct.required = true; }
  else { ct.required = false; ct.value = ''; }
});
document.querySelectorAll('.plan-card').forEach(card => {
  const radio = card.querySelector('input[type="radio"]');
  const inner = card.querySelector('.card');
  if (radio.checked) inner.style.borderColor = '#c9a127';
  card.addEventListener('click', () => {
    document.querySelectorAll('.plan-card .card').forEach(c => c.style.borderColor = 'transparent');
    inner.style.borderColor = '#c9a127';
    radio.checked = true;
  });
  card.addEventListener('mouseenter', () => { if (!radio.checked) inner.style.borderColor = '#c9a12766'; });
  card.addEventListener('mouseleave', () => { if (!radio.checked) inner.style.borderColor = 'transparent'; });
});
</script>
</body>
</html>
