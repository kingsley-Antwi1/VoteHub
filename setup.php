<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/includes/functions.php';

if (isSuperAdmin()) redirect(BASE_URL . '/admin/dashboard.php');

$db = getDB();

// Check if super admin exists
$stmt = $db->query("SELECT COUNT(*) FROM super_admin");
if ((int)$stmt->fetchColumn() > 0) {
    redirect(BASE_URL . '/auth/admin-login.php');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $email && $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO super_admin (username, email, password) VALUES (?,?,?)")->execute([$username, $email, $hash]);
        $message = 'Super Admin created successfully. <a href="' . BASE_URL . '/auth/admin-login.php">Login now</a>';
    } else {
        $message = 'All fields are required';
    }
}

// Insert default subscription plans
$plans = [
    ['Basic', 2, 500, 50, 0, 0, 0.00, 365],
    ['Standard', 5, 2000, 100, 1, 0, 299.00, 365],
    ['Premium', 999, 99999, 999, 1, 1, 799.00, 365],
];
foreach ($plans as $p) {
    $db->prepare("INSERT IGNORE INTO subscription_plans (name, max_elections, max_voters, max_candidates, custom_branding, priority_support, price, duration_days) VALUES (?,?,?,?,?,?,?,?)")->execute($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body class="login-page">
<div class="login-box">
  <h1>⚙️ VoteHub Setup</h1>
  <p>Create the Super Administrator account</p>
  <?php if ($message): ?><div class="flash flash-success" style="position:static;margin-bottom:16px"><?= $message ?></div><?php endif; ?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required minlength="6">
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center">Create Admin</button>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash').forEach(f => { setTimeout(() => { f.style.opacity = '0'; setTimeout(() => f.remove(), 500); }, 4000); });
});
</script>
</body>
</html>
