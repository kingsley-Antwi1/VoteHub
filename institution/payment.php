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

// Get current active subscription
$sub = $db->prepare("SELECT s.*, p.name AS plan_name, p.price FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1");
$sub->execute([$instId]);
$sub = $sub->fetch();

// Get available plans
$plans = $db->query("SELECT * FROM subscription_plans ORDER BY price")->fetchAll();

// Get payment history
$payments = $db->prepare("SELECT * FROM payments WHERE institution_id = ? ORDER BY created_at DESC");
$payments->execute([$instId]);
$payments = $payments->fetchAll();

// Handle retry — pre-fill form with old payment details
$retryPlan = 0;
$retryMethod = '';
$retryRef = '';
if (isset($_GET['retry'])) {
    $rid = (int)$_GET['retry'];
    $old = $db->prepare("SELECT * FROM payments WHERE id = ? AND institution_id = ?");
    $old->execute([$rid, $instId]);
    $old = $old->fetch();
    if ($old && $old['status'] === 'declined') {
        $retryPlan = (int)$old['subscription_id'];
        $retryMethod = $old['payment_method'];
        $retryRef = $old['reference'];
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    verifyCsrf();
    $planId = (int)($_POST['plan_id'] ?? 0);
    $method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['reference'] ?? '');

    if ($planId && $method && $reference) {
        $amount = (float)$db->prepare("SELECT price FROM subscription_plans WHERE id = ?")->execute([$planId]);
        // Re-read properly
        $priceStmt = $db->prepare("SELECT price FROM subscription_plans WHERE id = ?");
        $priceStmt->execute([$planId]);
        $amount = (float)$priceStmt->fetchColumn();
        $db->prepare("INSERT INTO payments (institution_id, subscription_id, amount, payment_method, reference, status) VALUES (?,?,?,?,?,'pending')")
           ->execute([$instId, $planId, $amount, $method, $reference]);
        logAudit($instId, 'inst_admin', currentUserId(), 'submit_payment', "Submitted payment of GH₵$amount for plan #$planId");
        flash('success', 'Payment details submitted. Awaiting admin approval.');
        redirect(BASE_URL . '/institution/payment.php');
    } else {
        $error = 'All payment fields are required';
    }
}

$pageTitle = 'Payments';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>
<?php if (!empty($error)): ?><div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>

<div class="page-header">
  <h2>💳 Subscription & Payments</h2>
</div>

<!-- Current Plan -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">📋 Current Subscription</div>
  <div class="card-body">
    <?php if ($sub): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;font-size:.85rem">
        <div><span style="color:#8899bb">Plan</span><br><strong style="color:#c9a127"><?= e($sub['plan_name']) ?></strong></div>
        <div><span style="color:#8899bb">Amount</span><br><strong>₵<?= number_format($sub['price'], 2) ?></strong></div>
        <div><span style="color:#8899bb">Status</span><br><?= statusBadge($sub['status']) ?></div>
        <div><span style="color:#8899bb">Start Date</span><br><?= date('d M Y', strtotime($sub['start_date'])) ?></div>
        <div><span style="color:#8899bb">Expiry</span><br><?= date('d M Y', strtotime($sub['end_date'])) ?></div>
        <div><span style="color:#8899bb">Days Left</span><br><strong><?= max(0, round((strtotime($sub['end_date']) - time()) / 86400) + 1) ?> days</strong></div>
      </div>
    <?php else: ?>
      <p style="color:#8899bb;text-align:center;padding:20px">No active subscription. Select a plan below to get started.</p>
    <?php endif; ?>
  </div>
</div>

<?php
$paySettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$hasMomo = !empty($paySettings['momo_number'] ?? '');
$hasBank = !empty($paySettings['bank_name'] ?? '') && !empty($paySettings['bank_account_number'] ?? '');
?>

<!-- Submit Payment -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">💰 Submit Payment</div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="submit_payment" value="1">
      <?= csrfField() ?>
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Select Plan *</label>
            <select name="plan_id" class="form-control" required onchange="updateAmount(this)">
              <option value="">— Choose —</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" <?= $retryPlan === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> — ₵<?= number_format($p['price'], 2) ?> / <?= $p['duration_days'] ?> days</option>
              <?php endforeach; ?>
            </select>
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Amount (GH₵) *</label>
          <input type="number" name="amount" id="amount" class="form-control" step="0.01" required readonly>
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Payment Method *</label>
          <select name="payment_method" id="payment_method" class="form-control" required onchange="showPaymentDetails(this.value)">
            <option value="">— Select —</option>
            <option value="mobile_money"<?= $retryMethod === 'mobile_money' ? ' selected' : '' ?><?php if (!$hasMomo): ?> disabled style="color:#999"<?php endif; ?>>Mobile Money</option>
            <option value="bank_transfer"<?= $retryMethod === 'bank_transfer' ? ' selected' : '' ?><?php if (!$hasBank): ?> disabled style="color:#999"<?php endif; ?>>Bank Transfer</option>

          </select>
        </div></div>
      </div>

      <?php if ($hasMomo): ?>
      <div id="momo_details" class="card" style="margin-bottom:16px;border-color:rgba(201,161,39,.3);display:none">
        <div class="card-body" style="padding:16px">
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#c9a127;margin-bottom:8px">📱 Send to Mobile Money</div>
          <div style="font-size:1.1rem;font-weight:700"><?= e($paySettings['momo_number']) ?></div>
          <div style="font-size:.85rem;color:#6b7280"><?= e($paySettings['momo_name']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($hasBank): ?>
      <div id="bank_details" class="card" style="margin-bottom:16px;border-color:rgba(201,161,39,.3);display:none">
        <div class="card-body" style="padding:16px">
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#c9a127;margin-bottom:8px">🏦 Send to Bank Account</div>
          <div style="font-size:.95rem;font-weight:700"><?= e($paySettings['bank_name']) ?></div>
          <div style="font-size:.85rem;color:#6b7280"><?= e($paySettings['bank_account_name']) ?></div>
          <div style="font-size:1.1rem;font-weight:700;margin-top:4px"><?= e($paySettings['bank_account_number']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0;flex:2"><div class="form-group">
          <label class="form-label">Transaction Reference / Receipt No. *</label>
          <input type="text" name="reference" class="form-control" required placeholder="e.g. Momo ref or bank slip number" value="<?= e($retryRef) ?>">
        </div></div>
      </div>
      <button type="submit" class="btn btn-gold">Submit Payment</button>
    </form>
  </div>
</div>

<!-- Payment History -->
<div class="card">
  <div class="card-header">📜 Payment History</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="7" style="text-align:center;color:#8899bb;padding:24px">No payments yet</td></tr>
        <?php else: $payCount = count($payments); $shown = array_slice($payments, 0, 3); $hidden = array_slice($payments, 3); ?>
        <?php foreach ($shown as $p):
          $pname = $db->prepare("SELECT name FROM subscription_plans WHERE id = ?");
          $pname->execute([$p['subscription_id']]);
          $pname = $pname->fetchColumn() ?: '—';
        ?>
        <tr>
          <td style="font-size:.78rem"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td><?= e($pname) ?></td>
          <td><strong style="color:#c9a127">₵<?= number_format($p['amount'], 2) ?></strong></td>
          <td><?= str_replace('_', ' ', e($p['payment_method'])) ?></td>
          <td style="font-size:.8rem"><?= e($p['reference']) ?></td>
          <td><?= statusBadge($p['status']) ?>
            <?php if ($p['status'] === 'declined' && !empty($p['notes'])): ?>
              <br><span style="font-size:.7rem;color:#991b1b"><?= e($p['notes']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($p['status'] === 'declined'): ?>
              <a href="<?= BASE_URL ?>/institution/payment.php?retry=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Retry</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($payCount > 3): ?>
      <tbody id="morePayments" style="display:none">
        <?php foreach ($hidden as $p):
          $pname = $db->prepare("SELECT name FROM subscription_plans WHERE id = ?");
          $pname->execute([$p['subscription_id']]);
          $pname = $pname->fetchColumn() ?: '—';
        ?>
        <tr>
          <td style="font-size:.78rem"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td><?= e($pname) ?></td>
          <td><strong style="color:#c9a127">₵<?= number_format($p['amount'], 2) ?></strong></td>
          <td><?= str_replace('_', ' ', e($p['payment_method'])) ?></td>
          <td style="font-size:.8rem"><?= e($p['reference']) ?></td>
          <td><?= statusBadge($p['status']) ?>
            <?php if ($p['status'] === 'declined' && !empty($p['notes'])): ?>
              <br><span style="font-size:.7rem;color:#991b1b"><?= e($p['notes']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($p['status'] === 'declined'): ?>
              <a href="<?= BASE_URL ?>/institution/payment.php?retry=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Retry</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="7" style="text-align:center;padding:8px"><button class="btn btn-ghost btn-sm" onclick="togglePayments()" id="togglePaymentsBtn">+ <?= $payCount - 3 ?> more payments</button></td></tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
function togglePayments() {
  const more = document.getElementById('morePayments');
  const btn = document.getElementById('togglePaymentsBtn');
  if (more.style.display === 'none') { more.style.display = ''; btn.textContent = 'Show less'; }
  else { more.style.display = 'none'; btn.textContent = '+ <?= $payCount - 3 ?> more payments'; }
}
function updateAmount(sel) {
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('amount').value = opt.dataset.price || '';
}
function showPaymentDetails(method) {
  document.getElementById('momo_details') && (document.getElementById('momo_details').style.display = method === 'mobile_money' ? '' : 'none');
  document.getElementById('bank_details') && (document.getElementById('bank_details').style.display = method === 'bank_transfer' ? '' : 'none');
}
<?php if ($retryMethod): ?>showPaymentDetails('<?= $retryMethod ?>');<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
