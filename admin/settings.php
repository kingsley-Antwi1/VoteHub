<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    verifyCsrf();
    $fields = ['momo_number','momo_name','bank_name','bank_account_name','bank_account_number'];
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt->execute([$key, $val]);
    }
    flash('success', 'Payment details saved');
    redirect(BASE_URL . '/admin/settings.php');
}

$pageTitle = 'Payment Settings';
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>⚙️ Payment Settings</h2>
</div>

<div class="card">
  <div class="card-header">💳 Payment Details for Institutions</div>
  <div class="card-body">
    <p style="color:#6b7280;margin-bottom:20px;font-size:.85rem">These details will be shown to institution admins on their payment page so they know where to send payments.</p>
    <form method="POST">
      <input type="hidden" name="save_settings" value="1">
      <?= csrfField() ?>

      <h3 style="color:#c9a127;font-size:.9rem;margin-bottom:12px">📱 Mobile Money</h3>
      <div class="row" style="gap:12px;margin-bottom:20px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">MoMo Number</label>
          <input type="text" name="momo_number" class="form-control" placeholder="e.g. 0244000000">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Account Name</label>
          <input type="text" name="momo_name" class="form-control" placeholder="e.g. John Doe">
        </div></div>
      </div>

      <h3 style="color:#c9a127;font-size:.9rem;margin-bottom:12px">🏦 Bank Transfer</h3>
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Bank Name</label>
          <input type="text" name="bank_name" class="form-control" placeholder="e.g. GC Bank">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Account Name</label>
          <input type="text" name="bank_account_name" class="form-control" placeholder="e.g. VoteHub Ltd">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Account Number</label>
          <input type="text" name="bank_account_number" class="form-control" placeholder="e.g. 1234567890">
        </div></div>
      </div>

      <button type="submit" class="btn btn-gold" style="margin-top:8px">Save Payment Details</button>
    </form>
    <p style="margin-top:12px;font-size:.78rem;color:#6b7280">Details are hidden after saving for security. Re-enter to update.</p>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
