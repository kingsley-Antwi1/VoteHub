<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isInstAdmin()) redirect(BASE_URL . '/institution/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, i.name AS inst_name, i.status AS inst_status, i.slug FROM institution_admins a JOIN institutions i ON i.id = a.institution_id WHERE a.email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        if ($admin['inst_status'] !== 'active') {
            $error = 'Your institution account is not active. Contact super admin for more info.';
        } elseif (!$admin['status']) {
            $error = 'Your admin account has been deactivated.';
        } else {
            session_regenerate_id(true);
            $_SESSION['inst_admin_id'] = $admin['id'];
            $_SESSION['institution_id'] = $admin['institution_id'];
            $_SESSION['inst_admin_name'] = $admin['full_name'];
            $_SESSION['inst_name'] = $admin['inst_name'];
            $_SESSION['inst_slug'] = $admin['slug'];
            $_SESSION['inst_role'] = $admin['role'];
            logAudit($admin['institution_id'], 'inst_admin', $admin['id'], 'login', 'Institution admin logged in');
            redirect(BASE_URL . '/institution/dashboard.php');
        }
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Institution Login — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body class="login-page">
<div class="login-box">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <h1 style="margin:0">🏛 VoteHub</h1>
    <a href="<?= BASE_URL ?>" class="inst-nav-link" style="padding:4px 12px">Home</a>
  </div>
  <p style="text-align:center;color:#8899bb;font-size:.85rem;margin:4px 0 24px">Institution Administrator Login</p>
  <?php if ($error): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); ?><?php endif; ?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center">Login</button>
    <p style="text-align:center;margin-top:12px;font-size:.8rem"><a href="<?= BASE_URL ?>/auth/inst-forgot-password.php">Forgot Password?</a></p>
    <p style="text-align:center;margin-top:16px;font-size:.8rem;color:#8899bb">
      New institution? <a href="<?= BASE_URL ?>/register.php">Register here</a>
    </p>
  </form>
</div>
</body>
</html>
