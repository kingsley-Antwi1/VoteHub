<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isSuperAdmin()) redirect(BASE_URL . '/admin/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM super_admin WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['super_admin_id'] = $admin['id'];
        $_SESSION['super_admin_name'] = $admin['username'];
        logAudit(null, 'super_admin', $admin['id'], 'login', 'Super Admin logged in');
        redirect(BASE_URL . '/admin/dashboard.php');
    }
    $error = 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Super Admin Login — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body class="login-page">
<div class="login-box">
  <h1>🔐 VoteHub</h1>
  <div style="display:flex;align-items:center;justify-content:space-between">
    <p>Super Administrator Login</p>
    <a href="<?= BASE_URL ?>" class="inst-nav-link" style="padding:4px 12px">Back</a>
  </div>
  <?php if ($error): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center">Login</button>
    <p style="text-align:center;margin-top:12px;font-size:.8rem"><a href="<?= BASE_URL ?>/auth/admin-forgot-password.php">Forgot Password?</a></p>
  </form>
</div>
</body>
</html>
