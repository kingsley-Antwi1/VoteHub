<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$school = trim($_GET['school'] ?? '');
$error = '';
$success = '';

if ($school) {
    $inst = $db->prepare("SELECT id, name, slug FROM institutions WHERE slug = ? AND status = 'active' LIMIT 1");
    $inst->execute([$school]);
    $inst = $inst->fetch();
    if (!$inst) $error = 'Institution not found or not active.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    verifyCsrf();
    $studentId = trim($_POST['student_id'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$studentId || !$fullName || !$password) {
        $error = 'Student ID, full name, and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $limitMsg = checkPlanLimit($inst['id'], 'voters');
        if ($limitMsg) { $error = $limitMsg; } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $db->prepare("INSERT INTO voters (institution_id, student_id, full_name, email, phone, level, password) VALUES (?,?,?,?,?,?,?)")
               ->execute([$inst['id'], $studentId, $fullName, $email, $phone, $level, $hash]);
            logAudit($inst['id'], 'voter', $db->lastInsertId(), 'register', "Voter self-registered");
            $success = 'Registration successful! <a href="' . BASE_URL . '/auth/voter-login.php?school=' . e($school) . '" style="color:#c9a127">Login now</a>';
        } catch (Throwable $e) {
            $error = 'Student ID already exists or error registering.';
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
<title>Voter Registration — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body class="login-page">
<div class="login-box">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
    <h1 style="margin:0">🗳 Register</h1>
    <a href="<?= BASE_URL ?>/school/<?= e($school) ?>" class="inst-nav-link" style="padding:4px 12px">Back</a>
  </div>
  <p style="text-align:center;color:#8899bb;font-size:.85rem;margin:4px 0 24px"><?= e($inst['name'] ?? '') ?></p>
  <?php if ($error): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="flash flash-success" style="position:static;margin-bottom:16px"><?= $success ?></div><?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="register" value="1">
    <div class="form-group">
      <label class="form-label">Student ID / Index Number *</label>
      <input type="text" name="student_id" class="form-control" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Full Name *</label>
      <input type="text" name="full_name" class="form-control" required>
    </div>
    <div class="row" style="gap:12px">
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control">
      </div></div>
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control">
      </div></div>
      <div class="col" style="min-width:0"><div class="form-group">
        <label class="form-label">Level/Year</label>
        <input type="text" name="level" class="form-control">
      </div></div>
    </div>
    <div class="form-group">
      <label class="form-label">Password * (min 6 chars)</label>
      <input type="password" name="password" class="form-control" required minlength="6">
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center">Register</button>
  </form>
  <p style="text-align:center;margin-top:16px;font-size:.8rem;color:#8899bb">
    Already registered? <a href="<?= BASE_URL ?>/auth/voter-login.php?school=<?= e($school) ?>">Login here</a>
  </p>
  <?php endif; ?>
</div>
</body>
</html>
