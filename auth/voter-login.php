<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$school = trim($_GET['school'] ?? '');
$error = '';
$step = 'login'; // login | otp

// Find institution by slug
if ($school) {
    $stmt = $db->prepare("SELECT id, name FROM institutions WHERE slug = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$school]);
    $inst = $stmt->fetch();
    if (!$inst) $error = 'Institution not found or not active. Contact super admin.';
} else {
    $error = 'No institution specified';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['student_id'], $_POST['password'])) {
        // Step 1: Verify credentials
        $studentId = trim($_POST['student_id']);
        $password = $_POST['password'];
        $voter = $db->prepare("SELECT * FROM voters WHERE institution_id = ? AND student_id = ? LIMIT 1");
        $voter->execute([$inst['id'], $studentId]);
        $v = $voter->fetch();
        if ($v && password_verify($password, $v['password'])) {
            if (!$v['status']) {
                $error = 'Your voter account has been deactivated.';
            } else {
                // Check voter's voting status (only for the current active election)
                $activeElection = $db->prepare("SELECT id FROM elections WHERE institution_id = ? AND status = 'active' AND NOW() BETWEEN start_date AND end_date LIMIT 1");
                $activeElection->execute([$inst['id']]);
                $activeElection = $activeElection->fetch();

                $hasVotedActive = false;
                if ($activeElection) {
                    $voteCheck = $db->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND election_id = ?");
                    $voteCheck->execute([$v['id'], $activeElection['id']]);
                    $hasVotedActive = (int)$voteCheck->fetchColumn() > 0;
                }

                if ($hasVotedActive) {
                    $error = '🙅 You\'ve already voted in this election. Multiple votes are not allowed. 😄';
                } else {
                    // Determine where to redirect after OTP
                    $redirectAfterOtp = $activeElection
                        ? BASE_URL . '/voter/ballot.php'
                        : BASE_URL . '/school/' . e($school);

                    // Generate and send OTP
                    $otp = generateOTP();
                    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
                    $db->prepare("INSERT INTO otp_codes (voter_id, code, expires_at) VALUES (?,?,?)")->execute([$v['id'], $otp, $expires]);
                    
                    // Store voter session temporarily
                    $_SESSION['otp_voter_id'] = $v['id'];
                    $_SESSION['otp_institution_id'] = $inst['id'];
                    $_SESSION['otp_code'] = $otp;
                    $_SESSION['otp_redirect'] = $redirectAfterOtp;
                    $step = 'otp';
                }
            }
        } else {
            $error = 'Invalid student ID or password';
        }
    } elseif (isset($_POST['otp_code'])) {
        // Step 2: Verify OTP
        $otpCode = trim($_POST['otp_code']);
        $voterId = $_SESSION['otp_voter_id'] ?? 0;
        $instId = $_SESSION['otp_institution_id'] ?? 0;
        if (!$voterId) { $error = 'Session expired. Please login again.'; $step = 'login'; }
        else {
            $stmt = $db->prepare("SELECT * FROM otp_codes WHERE voter_id = ? AND code = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$voterId, $otpCode]);
            $otp = $stmt->fetch();
            if ($otp) {
                $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$otp['id']]);
                session_regenerate_id(true);
                $_SESSION['voter_id'] = $voterId;
                $_SESSION['institution_id'] = $instId;
                // Set session vars for voter header
                $voterInfo = $db->prepare("SELECT full_name FROM voters WHERE id = ?");
                $voterInfo->execute([$voterId]);
                $vname = $voterInfo->fetchColumn();
                $_SESSION['voter_name'] = $vname;
                $_SESSION['inst_name'] = $inst['name'];
                $_SESSION['inst_slug'] = $school;
                $redirectUrl = $_SESSION['otp_redirect'] ?? BASE_URL . '/voter/ballot.php';
                unset($_SESSION['otp_voter_id'], $_SESSION['otp_institution_id'], $_SESSION['otp_code'], $_SESSION['otp_redirect']);
                logAudit($instId, 'voter', $voterId, 'login', 'Voter logged in via OTP');
                redirect($redirectUrl);
            } else {
                $error = 'Invalid or expired OTP code';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Voter Login — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body class="login-page">
<div class="login-box">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <h1 style="margin:0">🗳 VoteHub</h1>
    <a href="<?= BASE_URL ?>/school/<?= e($school) ?>" class="inst-nav-link" style="padding:4px 12px">Back</a>
  </div>
  <p style="text-align:center;color:#8899bb;font-size:.85rem;margin:4px 0 24px"><?= e($inst['name'] ?? 'Voter Login') ?> — <?= $step === 'otp' ? 'OTP Verification' : 'Login' ?></p>
  <?php if ($error): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>

  <?php if ($step === 'otp'): ?>
  <form method="POST">
    <?= csrfField() ?>
    <p style="font-size:.85rem;color:#8899bb;margin-bottom:16px;text-align:center">
      An OTP code has been generated. Enter it below to verify your identity.
    </p>
    <div style="text-align:center;font-size:2rem;letter-spacing:12px;font-weight:700;color:#c9a127;margin-bottom:16px;padding:12px;background:rgba(255,255,255,.04);border-radius:10px">
      <?= e($_SESSION['otp_code'] ?? '------') ?>
    </div>
    <p style="text-align:center;font-size:.75rem;color:#6b7280;margin-bottom:12px">(OTP shown here — no SMS/email gateway configured yet)</p>
    <div class="form-group">
      <label class="form-label">OTP Code</label>
      <input type="text" name="otp_code" class="form-control" required autofocus
             placeholder="Enter 6-digit code" maxlength="6" style="text-align:center;font-size:1.5rem;letter-spacing:8px">
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center">Verify<?= (strpos($_SESSION['otp_redirect'] ?? '', 'ballot') !== false) ? ' &amp; Vote' : '' ?></button>
    <p style="text-align:center;margin-top:12px;font-size:.8rem;color:#8899bb">
      OTP expires in <?= OTP_EXPIRY_MINUTES ?> minutes
    </p>
  </form>
  <?php else: ?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Student ID / Index Number</label>
      <input type="text" name="student_id" class="form-control" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center">Login</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
