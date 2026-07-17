<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

$totalInst = (int)$db->query("SELECT COUNT(*) FROM institutions")->fetchColumn();
$activeInst = (int)$db->query("SELECT COUNT(*) FROM institutions WHERE status='active'")->fetchColumn();
$pendingInst = (int)$db->query("SELECT COUNT(*) FROM institutions WHERE status='pending'")->fetchColumn();
$totalVoters = (int)$db->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$totalElections = (int)$db->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$activeElections = (int)$db->query("SELECT COUNT(*) FROM elections WHERE status='active'")->fetchColumn();
$totalVotes = (int)$db->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$revenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='approved'")->fetchColumn();

$recentInsts = $db->query("SELECT id, name, slug, type, status, created_at FROM institutions ORDER BY created_at DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>

<div class="row">
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalInst ?></div><div class="stat-label">Total Institutions</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $activeInst ?></div><div class="stat-label">Active</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $pendingInst ?></div><div class="stat-label">Pending Approval</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVoters ?></div><div class="stat-label">Total Voters</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalElections ?></div><div class="stat-label">Elections</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $activeElections ?></div><div class="stat-label">Active Elections</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVotes ?></div><div class="stat-label">Total Votes Cast</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num">₵<?= number_format($revenue, 2) ?></div><div class="stat-label">Revenue</div></div></div>
</div>

<?php $pendingPays = countPendingPayments(); ?>
<?php if ($pendingPays > 0): ?>
<div class="card" style="margin-bottom:20px;border-color:#f59e0b;background:rgba(245,158,11,.08)">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:12px 20px">
    <div>💳 <strong style="color:#f59e0b"><?= $pendingPays ?> pending payment<?= $pendingPays > 1 ? 's' : '' ?></strong> awaiting your approval</div>
    <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-gold btn-sm">Review Payments</a>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:24px">
  <div class="card-header">🏫 Recent Registrations</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Slug</th><th>Type</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($recentInsts as $i): ?>
        <tr>
          <td><strong><?= e($i['name']) ?></strong></td>
          <td><code>/school/<?= e($i['slug']) ?></code></td>
          <td><?= e($i['type']) ?></td>
          <td><?= statusBadge($i['status']) ?></td>
          <td><?= timeAgo($i['created_at']) ?></td>
          <td><a href="<?= BASE_URL ?>/admin/institution-view.php?id=<?= $i['id'] ?>" class="btn btn-ghost btn-sm">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
