<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

$inst = $db->prepare("SELECT * FROM institutions WHERE id = ?");
$inst->execute([$instId]);
$inst = $inst->fetch();

// Plan info
$sub = $db->prepare("SELECT s.*, p.name AS plan_name, p.price FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1");
$sub->execute([$instId]);
$sub = $sub->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $about = trim($_POST['about'] ?? '');
    $primaryColor = trim($_POST['primary_color'] ?? '#1a1a2e');

    $logo = $inst['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $err = validateUpload($_FILES['logo']);
        if ($err) { flash('error', $err); redirect(BASE_URL . '/institution/profile.php'); }
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) { flash('error', 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp, svg'); redirect(BASE_URL . '/institution/profile.php'); }
        $logo = uniqid('logo_') . '.' . $ext;
        $targetDir = UPLOAD_PATH . '/institutions/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        move_uploaded_file($_FILES['logo']['tmp_name'], $targetDir . $logo);
    }

    $db->prepare("UPDATE institutions SET name=?, location=?, contact_email=?, contact_phone=?, about=?, logo=?, primary_color=? WHERE id=?")
       ->execute([$name, $location, $email, $phone, $about, $logo, $primaryColor, $instId]);
    $_SESSION['inst_name'] = $name;
    flash('success', 'Settings updated');
    redirect(BASE_URL . '/institution/profile.php');
}

$pageTitle = 'Settings';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>

<?php if ($sub): ?>
<div class="card" style="margin-bottom:20px;border-color:rgba(201,161,39,.2)">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:.85rem;padding:12px 20px">
    <div>💎 <strong style="color:#c9a127"><?= e($sub['plan_name']) ?></strong> · ₵<?= number_format($sub['price'], 2) ?> · Expires <?= date('d M Y', strtotime($sub['end_date'])) ?> · <span style="color:#8899bb"><?= max(0, (strtotime($sub['end_date']) - time()) / 86400) + 1 ?> days left</span></div>
    <a href="<?= BASE_URL ?>/institution/payment.php" class="btn btn-ghost btn-sm">💳 Renew</a>
  </div>
</div>
<?php endif; ?>

<div class="page-header">
  <h2>⚙️ Institution Settings</h2>
</div>

<div class="card">
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Institution Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($inst['name']) ?>" required>
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" value="<?= e($inst['location'] ?? '') ?>">
        </div></div>
      </div>
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($inst['contact_email'] ?? '') ?>">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($inst['contact_phone'] ?? '') ?>">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Brand Color</label>
          <input type="color" name="primary_color" class="form-control" value="<?= e($inst['primary_color'] ?? '#1a1a2e') ?>" style="padding:4px;height:42px">
        </div></div>
      </div>
      <div class="form-group">
        <label class="form-label">About</label>
        <textarea name="about" class="form-control" rows="3"><?= e($inst['about'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Logo</label>
        <?php if ($inst['logo']): ?>
          <div style="margin-bottom:8px"><img src="<?= BASE_URL ?>/assets/uploads/institutions/<?= e($inst['logo']) ?>" style="height:60px;border-radius:8px"></div>
        <?php endif; ?>
        <input type="file" name="logo" accept="image/*" class="form-control">
      </div>
      <div style="margin-top:12px;padding:12px;background:rgba(255,255,255,.03);border-radius:8px;font-size:.82rem;color:#8899bb">
        <strong>Portal URL:</strong> <a href="<?= BASE_URL ?>/school/<?= e($inst['slug']) ?>" target="_blank" style="color:#000"><?= BASE_URL ?>/school/<?= e($inst['slug']) ?></a>
      </div>
      <button type="submit" class="btn btn-gold" style="margin-top:16px">Save Settings</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
