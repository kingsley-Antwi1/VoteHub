<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$inst = $db->prepare("SELECT * FROM institutions WHERE id = ?");
$inst->execute([$id]);
$inst = $inst->fetch();
if (!$inst) { flash('error', 'Institution not found'); redirect(BASE_URL . '/admin/institutions.php'); }

// Handle status change inline
$action = $_POST['action'] ?? '';
if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (in_array($action, ['approve','suspend','activate','deactivate'])) {
        $status = match($action) { 'approve'=>'active', 'suspend'=>'suspended', 'activate'=>'active', 'deactivate'=>'deactivated' };
        $db->prepare("UPDATE institutions SET status = ? WHERE id = ?")->execute([$status, $id]);
        logAudit(null, 'super_admin', currentUserId(), "institution_{$action}", "Institution #$id {$action}d");
        flash('success', "Institution {$action}d successfully");
        redirect(BASE_URL . "/admin/institution-view.php?id=$id");
    }
    if ($action === 'delete') {
        $name = $inst['name'];
        $db->prepare("DELETE FROM institutions WHERE id = ?")->execute([$id]);
        logAudit(null, 'super_admin', currentUserId(), 'delete_institution', "Deleted institution #$id ($name)");
        flash('success', "Institution <strong>$name</strong> deleted permanently.");
        redirect(BASE_URL . '/admin/institutions.php');
    }
}

$stats = [];
$voterStmt = $db->prepare("SELECT COUNT(*) FROM voters WHERE institution_id = ?"); $voterStmt->execute([$id]); $stats['voters'] = (int)$voterStmt->fetchColumn();
$elecStmt = $db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ?"); $elecStmt->execute([$id]); $stats['elections'] = (int)$elecStmt->fetchColumn();
$activeStmt = $db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ? AND status = 'active'"); $activeStmt->execute([$id]); $stats['active_elections'] = (int)$activeStmt->fetchColumn();
$votesStmt = $db->prepare("SELECT COUNT(*) FROM votes v JOIN elections e ON e.id = v.election_id WHERE e.institution_id = ?"); $votesStmt->execute([$id]); $stats['votes'] = (int)$votesStmt->fetchColumn();

$subInfo = $db->prepare("SELECT s.*, p.name AS plan_name, p.price FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? ORDER BY s.id DESC LIMIT 1");
$subInfo->execute([$id]);
$subInfo = $subInfo->fetch();

$admins = $db->prepare("SELECT * FROM institution_admins WHERE institution_id = ?");
$admins->execute([$id]);
$admins = $admins->fetchAll();

$payments = $db->prepare("SELECT * FROM payments WHERE institution_id = ? ORDER BY created_at DESC LIMIT 10");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$electionList = $db->prepare("SELECT * FROM elections WHERE institution_id = ? ORDER BY created_at DESC");
$electionList->execute([$id]);
$electionList = $electionList->fetchAll();

$pageTitle = $inst['name'];
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>🏫 <?= e($inst['name']) ?></h2>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if ($inst['status'] === 'pending'): ?>
      <form method="POST" style="display:inline" data-confirm="Approve <?= e($inst['name']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="approve"><button type="submit" class="btn btn-success btn-sm">✅ Approve</button></form>
    <?php elseif ($inst['status'] === 'active'): ?>
      <form method="POST" style="display:inline" data-confirm="Suspend <?= e($inst['name']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="suspend"><button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none">⛔ Suspend</button></form>
    <?php elseif ($inst['status'] === 'suspended'): ?>
      <form method="POST" style="display:inline" data-confirm="Reactivate <?= e($inst['name']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="activate"><button type="submit" class="btn btn-success btn-sm">🔄 Reactivate</button></form>
    <?php elseif ($inst['status'] === 'deactivated'): ?>
      <form method="POST" style="display:inline" data-confirm="Reactivate <?= e($inst['name']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="activate"><button type="submit" class="btn btn-success btn-sm">🔄 Reactivate</button></form>
    <?php endif; ?>
    <?php if ($inst['status'] !== 'deactivated'): ?>
      <form method="POST" style="display:inline" data-confirm="Deactivate <?= e($inst['name']) ?>?">
        <?= csrfField() ?><input type="hidden" name="action" value="deactivate">
        <button type="submit" class="btn btn-sm" style="background:#6b7280;color:#fff;border:none">🚫 Deactivate</button>
      </form>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/school/<?= e($inst['slug']) ?>"  class="btn btn-ghost btn-sm">🌐 View Portal</a>
    <form method="POST" style="display:inline" data-confirm="PERMANENTLY DELETE <?= e($inst['name']) ?>? All data will be lost.">
      <?= csrfField() ?><input type="hidden" name="action" value="delete">
      <button type="submit" class="btn btn-sm" style="background:#7f1d1d;color:#fff;border:none">🗑 Delete</button>
    </form>
  </div>
</div>

<div class="row" style="margin-bottom:20px">
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $stats['voters'] ?></div><div class="stat-label">Voters</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $stats['elections'] ?></div><div class="stat-label">Elections</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $stats['active_elections'] ?></div><div class="stat-label">Active</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $stats['votes'] ?></div><div class="stat-label">Votes Cast</div></div></div>
</div>

<div class="row">
  <div class="col">
    <div class="card">
      <div class="card-header">📋 Details</div>
      <div class="card-body" style="font-size:.85rem">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="color:#8899bb">Type</div><div><?= e($inst['type']) ?></div>
          <div style="color:#8899bb">Slug</div><div><code>/school/<?= e($inst['slug']) ?></code></div>
          <div style="color:#8899bb">Status</div><div><?= statusBadge($inst['status']) ?></div>
          <div style="color:#8899bb">Email</div><div><?= e($inst['contact_email'] ?: '—') ?></div>
          <div style="color:#8899bb">Phone</div><div><?= e($inst['contact_phone'] ?: '—') ?></div>
          <div style="color:#8899bb">Location</div><div><?= e($inst['location'] ?: '—') ?></div>
          <div style="color:#8899bb">Joined</div><div><?= date('d M Y', strtotime($inst['created_at'])) ?></div>
          <div style="color:#8899bb">Subscription</div><div><?php if ($subInfo): ?><?= e($subInfo['plan_name']) ?> (₵<?= number_format($subInfo['price'], 2) ?>) · <?= statusBadge($subInfo['status']) ?> · Expires <?= date('d M Y', strtotime($subInfo['end_date'])) ?><?php else: ?><span class="badge badge-secondary">None</span><?php endif; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card">
      <div class="card-header">👥 Administrators</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
              <td><?= e($a['full_name']) ?></td>
              <td><?= e($a['email']) ?></td>
              <td><?= e($a['role']) ?></td>
              <td><?= $a['status'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    💳 Payment History
    <?php if (count($payments) > 3): ?>
    <span style="font-size:.75rem;color:#6b7280;float:right"><?= count($payments) ?> total</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <?php $paySettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR); ?>
      <thead><tr><th>Amount</th><th>Method</th><th>Account</th><th>Reference</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($payments, 0, 3) as $p): ?>
        <tr>
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
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if (count($payments) > 3): ?>
      <tbody id="morePayments" style="display:none">
        <?php foreach (array_slice($payments, 3) as $p): ?>
        <tr>
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
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="6" style="text-align:center;padding:8px"><button class="btn btn-ghost btn-sm" onclick="togglePayments()" id="togglePaymentsBtn">+ <?= count($payments) - 3 ?> more payments</button></td></tr>
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
  else { more.style.display = 'none'; btn.textContent = '+ <?= count($payments) - 3 ?> more payments'; }
}
</script>

<div class="card" style="margin-top:20px">
  <div class="card-header">🗳 Elections Overview</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Period</th><th>Status</th><th>Positions</th><th>Candidates</th><th>Votes Cast</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($electionList as $el):
          $posCount = (function() use ($db, $el) { $s = $db->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ?"); $s->execute([$el['id']]); return (int)$s->fetchColumn(); })();
          $candCount = (function() use ($db, $el) { $s = $db->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON p.id = c.position_id WHERE p.election_id = ?"); $s->execute([$el['id']]); return (int)$s->fetchColumn(); })();
          $voteCount = (function() use ($db, $el) { $s = $db->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?"); $s->execute([$el['id']]); return (int)$s->fetchColumn(); })();
          $elPositions = (function() use ($db, $el) { $s = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order"); $s->execute([$el['id']]); return $s->fetchAll(); })();
        ?>
        <tr>
          <td><strong><?= e($el['title']) ?></strong></td>
          <td style="font-size:.78rem"><?= date('d M Y', strtotime($el['start_date'])) ?> — <?= date('d M Y', strtotime($el['end_date'])) ?></td>
          <td><?= statusBadge($el['status']) ?></td>
          <td><?= $posCount ?></td>
          <td><?= $candCount ?></td>
          <td><?= $voteCount ?></td>
          <td><a href="<?= BASE_URL ?>/school/results.php?slug=<?= e($inst['slug']) ?>&election_id=<?= $el['id'] ?>"  class="btn btn-ghost btn-sm">📊 Results</a></td>
        </tr>
        <?php if ($elPositions): ?>
        <tr style="background:rgba(0,0,0,.02)">
          <td colspan="7" style="padding:4px 16px 12px">
            <details style="font-size:.78rem">
              <summary style="color:#c9a127;cursor:pointer;font-weight:600;padding:4px 0">📊 View Results & Rankings <?= $el['status'] === 'closed' ? '<span style="color:#059669;font-size:.7rem;font-weight:400">✓ Final</span>' : ($el['status'] === 'active' ? '<span style="color:#f59e0b;font-size:.7rem;font-weight:400">⚡ Live</span>' : '') ?></summary>
              <?php foreach ($elPositions as $ep):
                $elCands = (function() use ($db, $ep) { $s = $db->prepare("SELECT c.*, COUNT(v.id) AS vcount FROM candidates c LEFT JOIN votes v ON v.candidate_id = c.id WHERE c.position_id = ? GROUP BY c.id ORDER BY vcount DESC"); $s->execute([$ep['id']]); return $s->fetchAll(); })();
                $maxVotes = $elCands ? max(array_column($elCands, 'vcount')) : 1;
                $posTotal = $elCands ? array_sum(array_column($elCands, 'vcount')) : 0;
              ?>
              <div style="margin:8px 0;padding:8px;background:rgba(255,255,255,.6);border-radius:6px;border:1px solid #e5e7eb">
                <strong style="color:#1f2937"><?= e($ep['title']) ?></strong>
                <?php $r = 1; foreach ($elCands as $ec):
                  $isWinner = $ec['vcount'] > 0 && $ec['vcount'] === $maxVotes;
                  $pct = $posTotal > 0 ? round(($ec['vcount'] / $posTotal) * 100, 1) : 0;
                  $rankLabel = $r === 1 ? '1st' : ($r === 2 ? '2nd' : ($r === 3 ? '3rd' : $r . 'th'));
                ?>
                <div style="display:flex;align-items:center;gap:6px;margin:4px 0;font-size:.75rem">
                  <?php if ($ec['photo']): ?>
                    <img src="<?= BASE_URL ?>/assets/uploads/candidates/<?= e($ec['photo']) ?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover">
                  <?php else: ?>
                    <span style="width:24px;height:24px;border-radius:50%;background:#e5e7eb;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem">👤</span>
                  <?php endif; ?>
                  <span style="flex:1;color:<?= $isWinner ? '#c9a127' : '#6b7280' ?>;font-weight:<?= $isWinner ? '700' : '400' ?>"><?= $rankLabel ?>. <?= $isWinner ? '🏆 ' : '' ?><?= e($ec['full_name']) ?>
                    <?php if ($ec['manifesto'] ?? ''): ?>
                      <span style="display:block;font-size:.65rem;color:#8899bb;font-weight:400"><?= e(mb_substr($ec['manifesto'], 0, 80)) ?><?= mb_strlen($ec['manifesto'] ?? '') > 80 ? '...' : '' ?></span>
                    <?php endif; ?>
                  </span>
                  <span style="color:#6b7280"><?= $ec['vcount'] ?> vote<?= $ec['vcount'] != 1 ? 's' : '' ?> (<?= $pct ?>%)</span>
                </div>
                <?php $r++; endforeach; ?>
              </div>
              <?php endforeach; ?>
            </details>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($electionList)): ?>
        <tr><td colspan="6" style="text-align:center;color:#8899bb">No elections yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
