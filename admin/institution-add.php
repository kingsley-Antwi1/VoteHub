<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'university';
    $location = trim($_POST['location'] ?? '');
    $email = trim($_POST['contact_email'] ?? '');
    $phone = trim($_POST['contact_phone'] ?? '');
    $planId = (int)($_POST['subscription_id'] ?? 0);
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if ($name && $adminEmail && $adminPassword) {
        $slug = slugify($name);
        $origSlug = $slug;
        $i = 1;
        $check = $db->prepare("SELECT COUNT(*) FROM institutions WHERE slug = ?");
        $check->execute([$slug]);
        while ($check->fetchColumn()) {
            $slug = $origSlug . '-' . $i++;
            $check->execute([$slug]);
        }

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO institutions (name, slug, type, location, contact_email, contact_phone, subscription_id, status) VALUES (?,?,?,?,?,?,?,'active')")
               ->execute([$name, $slug, $type, $location, $email, $phone, $planId ?: null]);
            $instId = $db->lastInsertId();

            $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO institution_admins (institution_id, full_name, email, password) VALUES (?,?,?,?)")
               ->execute([$instId, $adminName, $adminEmail, $hash]);

            if ($planId) {
                $plan = $db->prepare("SELECT duration_days FROM subscription_plans WHERE id = ?");
                $plan->execute([$planId]);
                $days = (int)$plan->fetchColumn();
                $db->prepare("INSERT INTO subscriptions (institution_id, plan_id, start_date, end_date, status) VALUES (?,?,CURDATE(),DATE_ADD(CURDATE(), INTERVAL ? DAY),'active')")
                   ->execute([$instId, $planId, $days]);
            }

            $db->commit();
            logAudit(null, 'super_admin', currentUserId(), 'add_institution', "Created institution $name with admin $adminEmail");
            flash('success', "Institution <strong>$name</strong> created. Admin login: $adminEmail");
            redirect(BASE_URL . '/admin/institutions.php');
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Failed to create institution: ' . $e->getMessage();
        }
    } else {
        $error = 'Name, admin email, and admin password are required';
    }
}

$plans = $db->query("SELECT id, name, price, duration_days FROM subscription_plans ORDER BY price");

$pageTitle = 'Add Institution';
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="page-header">
  <h2>➕ Add Institution</h2>
</div>

<form method="POST">
  <div class="card">
    <div class="card-header">🏫 Institution Details</div>
    <div class="card-body">
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0;flex:2"><div class="form-group">
          <label class="form-label">Institution Name *</label>
          <input type="text" name="name" class="form-control" required>
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" class="form-control">
            <option value="shs">Senior High School</option>
            <option value="university" selected>University</option>
            <option value="other">Other</option>
          </select>
        </div></div>
      </div>
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Contact Email</label>
          <input type="email" name="contact_email" class="form-control">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Contact Phone</label>
          <input type="text" name="contact_phone" class="form-control">
        </div></div>
      </div>
      <div class="form-group">
        <label class="form-label">Subscription Plan</label>
        <select name="subscription_id" class="form-control">
          <option value="">— No Plan (assign later) —</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> — GH₵<?= $p['price'] ?> / <?= $p['duration_days'] ?> days</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">👤 Admin Account</div>
    <div class="card-body">
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Admin Full Name *</label>
          <input type="text" name="admin_name" class="form-control">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Admin Email *</label>
          <input type="email" name="admin_email" class="form-control" required>
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Admin Password *</label>
          <input type="text" name="admin_password" class="form-control" required value="<?= substr(bin2hex(random_bytes(4)), 0, 10) ?>">
        </div></div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-gold" style="margin-top:8px">Create Institution</button>
  <a href="<?= BASE_URL ?>/admin/institutions.php" class="btn btn-ghost" style="margin-top:8px">Cancel</a>
</form>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
