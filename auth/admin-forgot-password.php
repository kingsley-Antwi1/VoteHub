<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isSuperAdmin()) redirect(BASE_URL . '/admin/dashboard.php');

$error = '';
$success = '';
$step = isset($_SESSION['admin_reset_step']) ? $_SESSION['admin_reset_step'] : 'request';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['request_reset'])) {
        $username = trim($_POST['username'] ?? '');
        if ($username) {
            $stmt = $db->prepare("SELECT * FROM super_admin WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();

            if ($admin) {
                $otp = generateOTP();
                $_SESSION['admin_reset_otp'] = $otp;
                $_SESSION['admin_reset_admin_id'] = $admin['id'];
                $_SESSION['admin_reset_expires'] = time() + OTP_EXPIRY_MINUTES * 60;
                $_SESSION['admin_reset_step'] = 'otp';
                $step = 'otp';
            } else {
                // Don't reveal whether username exists — show same message
                $error = 'If that account exists, an OTP has been generated.';
                // Actually show OTP for demo purposes
                $otp = generateOTP();
                $_SESSION['admin_reset_otp'] = $otp;
                $_SESSION['admin_reset_admin_id'] = 0;
                $_SESSION['admin_reset_expires'] = time() + OTP_EXPIRY_MINUTES * 60;
                $_SESSION['admin_reset_step'] = 'otp';
                $step = 'otp';
            }
        } else {
            $error = 'Please enter your username or email';
        }
    } elseif (isset($_POST['verify_otp'])) {
        $otpInput = trim($_POST['otp_code'] ?? '');
        $storedOtp = $_SESSION['admin_reset_otp'] ?? '';
        $expires = $_SESSION['admin_reset_expires'] ?? 0;

        if (time() > $expires) {
            $error = 'OTP has expired. Please request a new one.';
            $_SESSION['admin_reset_step'] = 'request';
            $step = 'request';
            unset($_SESSION['admin_reset_otp'], $_SESSION['admin_reset_admin_id'], $_SESSION['admin_reset_expires']);
        } elseif ($otpInput === $storedOtp) {
            $_SESSION['admin_reset_step'] = 'reset';
            $step = 'reset';
        } else {
            $error = 'Invalid OTP code';
        }
    } elseif (isset($_POST['reset_password'])) {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $adminId = $_SESSION['admin_reset_admin_id'] ?? 0;

        if (!$adminId) {
            $error = 'Session expired. Please start over.';
            $_SESSION['admin_reset_step'] = 'request';
            $step = 'request';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE super_admin SET password = ? WHERE id = ?")->execute([$hash, $adminId]);
            logAudit(null, 'super_admin', $adminId, 'password_reset', 'Super Admin password reset via OTP');
            $success = 'Password reset successfully. Redirecting to login...';
            unset($_SESSION['admin_reset_otp'], $_SESSION['admin_reset_admin_id'], $_SESSION['admin_reset_expires'], $_SESSION['admin_reset_step']);
            // Will redirect after short delay
        }
    }
}

if ($success && !str_contains($success, 'Redirect')) {
    // Already handled above
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body class="login-page">
<div class="login-box">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <h1 style="margin:0">🔐 VoteHub</h1>
    <a href="<?= BASE_URL ?>/auth/admin-login.php" class="inst-nav-link" style="padding:4px 12px">Back</a>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="flash flash-success" style="position:static;margin-bottom:16px"><?= $success ?></div>
    <script>setTimeout(function(){ window.location.href = '<?= BASE_URL ?>/auth/admin-login.php'; }, 2000);</script>
  <?php elseif ($step === 'request'): ?>
    <p style="text-align:center;color:#8899bb;font-size:.85rem;margin:4px 0 24px">Enter your username or email to reset your password</p>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Username or Email</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <button type="submit" name="request_reset" class="btn btn-gold" style="width:100%;justify-content:center">Reset Password</button>
    </form>

  <?php elseif ($step === 'otp'): ?>
    <p style="text-align:center;color:#8899bb;font-size:.85rem;margin:4px 0 16px">An OTP has been generated. Enter it below.</p>
    <div style="text-align:center;font-size:2rem;letter-spacing:12px;font-weight:700;color:#c9a127;margin-bottom:16px;padding:12px;background:rgba(255,255,255,.04);border-radius:10px">
      <?= e($_SESSION['admin_reset_otp'] ?? '------') ?>
    </div>
    <p style="text-align:center;font-size:.75rem;color:#6b7280;margin-bottom:12px">(OTP shown here — no SMS/email gateway configured yet)</p>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">OTP Code</label>
        <input type="text" name="otp_code" class="form-control" required autofocus
               placeholder="Enter 6-digit code" maxlength="6" style="text-align:center;font-size:1.5rem;letter-spacing:8px">
      </div>
      <button type="submit" name="verify_otp" class="btn btn-gold" style="width:100%;justify-content:center">Verify OTP</button>
    </form>

  <?php elseif ($step === 'reset'): ?>
    <p style="text-align:center;color:#8899bb;font-size:.85rem;margin:4px 0 24px">Choose a new password</p>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">New Password * (min 6 chars)</label>
        <input type="password" name="password" class="form-control" required minlength="6">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password *</label>
        <input type="password" name="confirm_password" class="form-control" required minlength="6">
      </div>
      <button type="submit" name="reset_password" class="btn btn-gold" style="width:100%;justify-content:center">Update Password</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
