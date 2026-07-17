<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

// Approve / decline payment
$actionId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action && $actionId && in_array($action, ['approve','decline'])) {
    $status = $action === 'approve' ? 'approved' : 'declined';
    $notes = $action === 'decline' ? trim($_POST['reason'] ?? '') : '';
    $db->prepare("UPDATE payments SET status = ?, notes = ? WHERE id = ?")->execute([$status, $notes ?: null, $actionId]);
    if ($action === 'approve') {
        $pay = $db->prepare("SELECT p.institution_id, p.subscription_id, p.amount FROM payments p WHERE p.id = ?");
        $pay->execute([$actionId]);
        $pay = $pay->fetch();
        if ($pay && $pay['subscription_id']) {
            $plan = $db->prepare("SELECT duration_days FROM subscription_plans WHERE id = ?");
            $plan->execute([$pay['subscription_id']]);
            $days = (int)$plan->fetchColumn();
            if ($days > 0) {
                $existingSub = $db->prepare("SELECT id, end_date FROM subscriptions WHERE institution_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                $existingSub->execute([$pay['institution_id']]);
                $existing = $existingSub->fetch();
                if ($existing) {
                    $newEnd = date('Y-m-d', max(strtotime($existing['end_date']), time()) + $days * 86400);
                    // Only upgrade plan if the new plan is better (higher price)
                    $curPlanPrice = $db->prepare("SELECT p.price FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.id = ?");
                    $curPlanPrice->execute([$existing['id']]);
                    $curPrice = (float)$curPlanPrice->fetchColumn();
                    $newPlanPrice = $db->prepare("SELECT price FROM subscription_plans WHERE id = ?");
                    $newPlanPrice->execute([$pay['subscription_id']]);
                    $newPrice = (float)$newPlanPrice->fetchColumn();
                    if ($newPrice > $curPrice) {
                        $db->prepare("UPDATE subscriptions SET end_date = ?, plan_id = ? WHERE id = ?")->execute([$newEnd, $pay['subscription_id'], $existing['id']]);
                    } else {
                        $db->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?")->execute([$newEnd, $existing['id']]);
                    }
                } else {
                    $db->prepare("INSERT INTO subscriptions (institution_id, plan_id, start_date, end_date, status) VALUES (?,?,CURDATE(),DATE_ADD(CURDATE(), INTERVAL ? DAY),'active')")
                       ->execute([$pay['institution_id'], $pay['subscription_id'], $days]);
                }
            }
        }
    }
    logAudit(null, 'super_admin', currentUserId(), "payment_{$action}", "Payment #$actionId {$action}d");
    flash('success', "Payment {$action}d");
    redirect(BASE_URL . '/admin/payments.php');
}

$payments = $db->query("
    SELECT p.*, i.name AS inst_name, i.slug 
    FROM payments p 
    JOIN institutions i ON i.id = p.institution_id 
    ORDER BY p.created_at DESC
")->fetchAll();

$paySettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Payments';
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>💳 Payments</h2>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Institution</th><th>Amount</th><th>Method</th><th>Account</th><th>Reference</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="8" style="text-align:center;color:#8899bb;padding:24px">No payments yet</td></tr>
        <?php else: $payCount = count($payments); $shown = array_slice($payments, 0, 3); $hidden = array_slice($payments, 3); ?>
        <?php foreach ($shown as $p): ?>
        <tr>
          <td><strong><?= e($p['inst_name']) ?></strong></td>
          <td><strong style="color:#c9a127">₵<?= number_format($p['amount'], 2) ?></strong></td>
          <td><?= str_replace('_', ' ', e($p['payment_method'])) ?></td>
          <td style="font-size:.78rem">
            <?php if ($p['payment_method'] === 'mobile_money' && !empty($paySettings['momo_number'])): ?>
              <span style="color:#6b7280">MoMo:</span> <?= e($paySettings['momo_number']) ?><br><span style="color:#6b7280;font-size:.72rem"><?= e($paySettings['momo_name']) ?></span>
            <?php elseif ($p['payment_method'] === 'bank_transfer' && !empty($paySettings['bank_name'])): ?>
              <span style="color:#6b7280"><?= e($paySettings['bank_name']) ?>:</span> <?= e($paySettings['bank_account_number']) ?><br><span style="color:#6b7280;font-size:.72rem"><?= e($paySettings['bank_account_name']) ?></span>
            <?php else: ?>
              <span style="color:#9ca3af">—</span>
            <?php endif; ?>
          </td>
          <td><?= e($p['reference'] ?: '—') ?></td>
          <td><?= statusBadge($p['status']) ?></td>
          <td style="font-size:.78rem"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td>
            <?php if ($p['status'] === 'pending'): ?>
              <form method="POST" style="display:inline" data-confirm="Approve this payment of ₵<?= number_format($p['amount'], 2) ?> from <?= e($p['inst_name']) ?>?"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-success btn-sm">Approve</button></form>
              <button class="btn btn-danger btn-sm" onclick="declinePayment(<?= $p['id'] ?>, '<?= e(addslashes($p['inst_name'])) ?>', <?= $p['amount'] ?>)">Decline</button>
            <?php elseif ($p['status'] === 'approved'): ?>
              <span class="badge badge-success">Approved</span>
            <?php elseif ($p['status'] === 'declined'): ?>
              <span class="badge badge-danger">Declined</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($payCount > 3): ?>
      <tbody id="morePayments" style="display:none">
        <?php foreach ($hidden as $p): ?>
        <tr>
          <td><strong><?= e($p['inst_name']) ?></strong></td>
          <td><strong style="color:#c9a127">₵<?= number_format($p['amount'], 2) ?></strong></td>
          <td><?= str_replace('_', ' ', e($p['payment_method'])) ?></td>
          <td style="font-size:.78rem">
            <?php if ($p['payment_method'] === 'mobile_money' && !empty($paySettings['momo_number'])): ?>
              <span style="color:#6b7280">MoMo:</span> <?= e($paySettings['momo_number']) ?><br><span style="color:#6b7280;font-size:.72rem"><?= e($paySettings['momo_name']) ?></span>
            <?php elseif ($p['payment_method'] === 'bank_transfer' && !empty($paySettings['bank_name'])): ?>
              <span style="color:#6b7280"><?= e($paySettings['bank_name']) ?>:</span> <?= e($paySettings['bank_account_number']) ?><br><span style="color:#6b7280;font-size:.72rem"><?= e($paySettings['bank_account_name']) ?></span>
            <?php else: ?>
              <span style="color:#9ca3af">—</span>
            <?php endif; ?>
          </td>
          <td><?= e($p['reference'] ?: '—') ?></td>
          <td><?= statusBadge($p['status']) ?></td>
          <td style="font-size:.78rem"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td>
            <?php if ($p['status'] === 'pending'): ?>
              <form method="POST" style="display:inline" data-confirm="Approve this payment of ₵<?= number_format($p['amount'], 2) ?> from <?= e($p['inst_name']) ?>?"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-success btn-sm">Approve</button></form>
              <button class="btn btn-danger btn-sm" onclick="declinePayment(<?= $p['id'] ?>, '<?= e(addslashes($p['inst_name'])) ?>', <?= $p['amount'] ?>)">Decline</button>
            <?php elseif ($p['status'] === 'approved'): ?>
              <span class="badge badge-success">Approved</span>
            <?php elseif ($p['status'] === 'declined'): ?>
              <span class="badge badge-danger">Declined</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="8" style="text-align:center;padding:8px"><button class="btn btn-ghost btn-sm" onclick="togglePayments()" id="togglePaymentsBtn">+ <?= $payCount - 3 ?> more payments</button></td></tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="modal-overlay" id="declineModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-body">
      <h3 style="color:#dc2626;margin-bottom:8px">⛔ Decline Payment</h3>
      <p style="font-size:.85rem;color:#6b7280;margin-bottom:16px" id="declineInfo"></p>
      <form method="POST" id="declineForm">
        <input type="hidden" name="action" value="decline">
        <input type="hidden" name="id" id="declineId" value="0">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Reason for declining</label>
          <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Incorrect amount, invalid reference..." required></textarea>
        </div>
        <div style="display:flex;gap:10px">
          <button type="button" class="btn btn-ghost" style="flex:1" onclick="closeModal('declineModal')">Cancel</button>
          <button type="submit" class="btn btn-danger" style="flex:1">Decline Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function togglePayments() {
  const more = document.getElementById('morePayments');
  const btn = document.getElementById('togglePaymentsBtn');
  if (more.style.display === 'none') { more.style.display = ''; btn.textContent = 'Show less'; }
  else { more.style.display = 'none'; btn.textContent = '+ <?= $payCount - 3 ?> more payments'; }
}
function declinePayment(id, name, amount) {
  document.getElementById('declineId').value = id;
  document.getElementById('declineInfo').textContent = 'Decline ₵' + amount.toFixed(2) + ' payment from ' + name + '?';
  openModal('declineModal');
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
