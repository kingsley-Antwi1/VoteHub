<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

$totalVoters = (function() use ($db, $instId) { $s = $db->prepare("SELECT COUNT(*) FROM voters WHERE institution_id = ?"); $s->execute([$instId]); return (int)$s->fetchColumn(); })();
$totalElections = (function() use ($db, $instId) { $s = $db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ?"); $s->execute([$instId]); return (int)$s->fetchColumn(); })();
$activeElections = (function() use ($db, $instId) { $s = $db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ? AND status='active'"); $s->execute([$instId]); return (int)$s->fetchColumn(); })();
$totalCandidates = (function() use ($db, $instId) { $s = $db->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON p.id = c.position_id JOIN elections e ON e.id = p.election_id WHERE e.institution_id = ?"); $s->execute([$instId]); return (int)$s->fetchColumn(); })();
$totalVotes = (function() use ($db, $instId) { $s = $db->prepare("SELECT COUNT(*) FROM votes v JOIN elections e ON e.id = v.election_id WHERE e.institution_id = ?"); $s->execute([$instId]); return (int)$s->fetchColumn(); })();

$recentElections = (function() use ($db, $instId) { $s = $db->prepare("SELECT * FROM elections WHERE institution_id = ? ORDER BY created_at DESC LIMIT 5"); $s->execute([$instId]); return $s->fetchAll(); })();

// Plan info
$limits = getPlanLimits($instId);

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>

<div class="row">
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVoters ?><?php if ($limits): ?><span style="font-size:.65rem;color:#8899bb">/<?= $limits['max_voters'] >= 99999 ? '∞' : number_format($limits['max_voters']) ?></span><?php endif; ?></div><div class="stat-label">Registered Voters</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $activeElections ?><?php if ($limits): ?><span style="font-size:.65rem;color:#8899bb">/<?= $limits['max_elections'] >= 999 ? '∞' : $limits['max_elections'] ?></span><?php endif; ?></div><div class="stat-label">Active Elections</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalElections ?></div><div class="stat-label">Total Elections</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalCandidates ?><?php if ($limits): ?><span style="font-size:.65rem;color:#8899bb">/<?= $limits['max_candidates'] >= 999 ? '∞' : $limits['max_candidates'] ?></span><?php endif; ?></div><div class="stat-label">Candidates</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVotes ?></div><div class="stat-label">Votes Cast</div></div></div>
</div>

<?php if ($limits): ?>
<div class="card" style="margin-bottom:20px;border-color:rgba(201,161,39,.2)">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:.85rem;padding:12px 20px">
    <div>💎 <strong style="color:#c9a127"><?= e($limits['plan_name']) ?></strong> · Expires <?= date('d M Y', strtotime($limits['end_date'])) ?>
      · <span style="color:#8899bb">📋 <?= $totalElections ?>/<?= $limits['max_elections'] >= 999 ? '∞' : $limits['max_elections'] ?> elections</span>
      · <span style="color:#8899bb">👤 <?= $totalVoters ?>/<?= $limits['max_voters'] >= 99999 ? '∞' : number_format($limits['max_voters']) ?> voters</span>
      · <span style="color:#8899bb">🏆 <?= $totalCandidates ?>/<?= $limits['max_candidates'] >= 999 ? '∞' : $limits['max_candidates'] ?> candidates</span>
    </div>
    <a href="<?= BASE_URL ?>/institution/payment.php" class="btn btn-ghost btn-sm">Manage</a>
  </div>
</div>
<div class="card" style="margin-bottom:20px;border-color:rgba(201,161,39,.15)">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:.85rem;padding:12px 20px">
    <div>🔗 <strong>Voter Portal Link</strong> <span style="color:#000;font-family:monospace;font-size:.78rem"><?= BASE_URL ?>/school/<?= e($_SESSION['inst_slug'] ?? '') ?></span></div>
    <a href="<?= BASE_URL ?>/school/<?= e($_SESSION['inst_slug'] ?? '') ?>" target="_blank" class="btn btn-gold btn-sm">Open Portal</a>
  </div>
</div>
<?php elseif ($instId): ?>
<div class="card" style="margin-bottom:20px;border-color:rgba(201,161,39,.2);background:rgba(201,161,39,.05)">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:.85rem;padding:12px 20px">
    <div>⚠️ <strong style="color:#f59e0b">No Active Subscription</strong> · Submit payment to activate your plan and unlock features.</div>
    <a href="<?= BASE_URL ?>/institution/payment.php" class="btn btn-gold btn-sm">Submit Payment</a>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:24px">
  <div class="card-header">🗳 Elections</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Period</th><th>Status</th><th>Candidates</th><th>Votes</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($recentElections as $e):
          $candCount = (function() use ($db, $e) { $s = $db->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON p.id = c.position_id WHERE p.election_id = ?"); $s->execute([$e['id']]); return (int)$s->fetchColumn(); })();
          $voteCount = (function() use ($db, $e) { $s = $db->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?"); $s->execute([$e['id']]); return (int)$s->fetchColumn(); })();
        ?>
        <tr>
          <td><strong><?= e($e['title']) ?></strong></td>
          <td style="font-size:.8rem"><?= date('d M', strtotime($e['start_date'])) ?> — <?= date('d M Y', strtotime($e['end_date'])) ?></td>
          <td><?= statusBadge($e['status']) ?></td>
          <td><?= $candCount ?></td>
          <td><?= $voteCount ?></td>
          <td>
            <a href="<?= BASE_URL ?>/institution/elections.php" class="btn btn-ghost btn-sm">👁</a>
            <a href="<?= BASE_URL ?>/institution/results.php?election_id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">📊</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
