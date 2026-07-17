<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireVoter();
$db = getDB();
$voterId = (int)($_SESSION['voter_id'] ?? 0);
$instId = currentInstitutionId();

// Block voting if institution has no approved subscription
$hasSub = hasActiveSubscription($instId);

// Find active election for this institution
$election = $db->prepare("SELECT * FROM elections WHERE institution_id = ? AND status = 'active' AND NOW() BETWEEN start_date AND end_date LIMIT 1");
$election->execute([$instId]);
$election = $election->fetch();

if (!$election) {
    // Check if there's a future election (pending or active with future start)
    $nextElection = $db->prepare("SELECT * FROM elections WHERE institution_id = ? AND status IN ('pending','active') AND start_date > NOW() ORDER BY start_date ASC LIMIT 1");
    $nextElection->execute([$instId]);
    $nextElection = $nextElection->fetch();
    // Check if election already ended
    $pastElection = $db->prepare("SELECT * FROM elections WHERE institution_id = ? AND end_date < NOW() ORDER BY end_date DESC LIMIT 1");
    $pastElection->execute([$instId]);
    $pastElection = $pastElection->fetch();
    // Check if election is suspended or deactivated
    $pausedElection = $db->prepare("SELECT * FROM elections WHERE institution_id = ? AND status IN ('suspended','deactivated') ORDER BY created_at DESC LIMIT 1");
    $pausedElection->execute([$instId]);
    $pausedElection = $pausedElection->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $election) {
    verifyCsrf();
    $positions = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
    $positions->execute([$election['id']]);
    $votesToInsert = [];
    $errors = [];
    while ($pos = $positions->fetch()) {
        $candidateId = (int)($_POST["pos_{$pos['id']}"] ?? 0);
        if ($candidateId) {
            // Verify candidate belongs to this position
            $check = $db->prepare("SELECT COUNT(*) FROM candidates WHERE id = ? AND position_id = ?");
            $check->execute([$candidateId, $pos['id']]);
            if ((int)$check->fetchColumn() > 0) {
                $votesToInsert[] = [
                    'election_id' => $election['id'],
                    'voter_id' => $voterId,
                    'position_id' => $pos['id'],
                    'candidate_id' => $candidateId,
                ];
            }
        }
    }
    if (empty($votesToInsert)) {
        $error = 'Please select at least one candidate.';
    } else {
        try {
            $db->beginTransaction();
            $insert = $db->prepare("INSERT INTO votes (election_id, voter_id, position_id, candidate_id) VALUES (?,?,?,?)");
            foreach ($votesToInsert as $v) {
                $insert->execute([$v['election_id'], $v['voter_id'], $v['position_id'], $v['candidate_id']]);
            }
            $db->commit();
            logAudit($instId, 'voter', $voterId, 'vote', "Voted in election #{$election['id']}");
            redirect(BASE_URL . '/voter/confirmation.php');
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'An error occurred. You may have already voted.';
        }
    }
}

$pageTitle = 'Vote';
include __DIR__ . '/../includes/voter-header.php';
?>
<?= renderFlash() ?>

<?php if (!$hasSub): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:60px">
    <div style="font-size:4rem;margin-bottom:16px">🔒</div>
    <h3>Voting Unavailable</h3>
    <p style="color:#8899bb">Voting has not been opened yet. The institution's subscription is pending approval.</p>
    <a href="<?= BASE_URL ?>/school/<?= e($_SESSION['inst_slug'] ?? '') ?>" class="btn btn-ghost" style="margin-top:16px">Back to Portal</a>
  </div></div>
<?php elseif (!$election): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:60px">
    <div style="font-size:4rem;margin-bottom:16px">🗳️</div>
    <?php if ($nextElection): ?>
      <h3>Election: <?= e($nextElection['title']) ?></h3>
      <p style="color:#c9a127;font-size:1.1rem;margin:8px 0">
        Starts: <?= date('d M Y, H:i', strtotime($nextElection['start_date'])) ?>
      </p>
      <p style="color:#c9a127;font-size:1.1rem;margin:4px 0">
        Ends: <?= date('d M Y, H:i', strtotime($nextElection['end_date'])) ?>
      </p>
      <p style="color:#8899bb;margin-top:16px">Voting will open automatically at the scheduled start time.</p>
    <?php elseif ($pastElection): ?>
      <h3>Election Ended</h3>
      <p style="color:#8899bb">The last election ended on <?= date('d M Y, H:i', strtotime($pastElection['end_date'])) ?>. Check back for future elections.</p>
    <?php elseif ($pausedElection): ?>
      <h3>⏸️ <?= $pausedElection['status'] === 'suspended' ? 'Voting Suspended' : 'Voting Deactivated' ?></h3>
      <p style="color:#c9a127;font-size:1.1rem;margin:8px 0"><?= e($pausedElection['title']) ?></p>
      <p style="color:#8899bb"><?= $pausedElection['status'] === 'suspended' ? 'Voting has been temporarily suspended by the administrator. It may resume later.' : 'This election has been deactivated by the administrator. Check back for future elections.' ?></p>
    <?php else: ?>
      <h3>No Election Scheduled</h3>
      <p style="color:#8899bb">There is no active election at this time.</p>
    <?php endif; ?>
  </div></div>
<?php elseif (!empty($error)): ?>
  <div class="flash flash-error" style="position:static;margin-bottom:16px"><?= e($error) ?></div>
<?php else:
  $positions = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
  $positions->execute([$election['id']]);
  $positions = $positions->fetchAll();
?>
<div class="page-header">
  <div><h2>🗳 <?= e($election['title']) ?></h2></div>
</div>
<div style="margin-bottom:20px;font-size:.85rem;color:#8899bb">Vote once per position. You cannot change your vote after submission.</div>

<form method="POST" id="ballotForm">
  <?= csrfField() ?>
  <?php foreach ($positions as $pos):
    $candidates = $db->prepare("SELECT * FROM candidates WHERE position_id = ?");
    $candidates->execute([$pos['id']]);
    $candidates = $candidates->fetchAll();
  ?>
  <div class="card">
    <div class="card-header"><?= e($pos['title']) ?> <?= $pos['max_vote'] > 1 ? "(Select up to {$pos['max_vote']})" : '' ?></div>
    <div class="card-body">
      <div class="row" style="gap:16px">
        <?php foreach ($candidates as $c): ?>
        <div class="col" style="min-width:200px;max-width:260px">
          <div class="candidate-card" onclick="selectCandidate(this, 'pos_<?= $pos['id'] ?>', <?= $c['id'] ?>)">
            <?php if ($c['photo']): ?>
              <img src="<?= BASE_URL ?>/assets/uploads/candidates/<?= e($c['photo']) ?>" alt="">
            <?php else: ?>
              <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:2rem">👤</div>
            <?php endif; ?>
            <div class="name"><?= e($c['full_name']) ?></div>
            <?php if ($c['manifesto']): ?>
              <div class="manifesto"><?= nl2br(e($c['manifesto'])) ?></div>
            <?php endif; ?>
            <input type="radio" name="pos_<?= $pos['id'] ?>" value="<?= $c['id'] ?>" style="display:none">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:14px;font-size:1rem" data-confirm="Are you sure you want to vote?">🗳 Submit Vote</button>
</form>
<?php endif; ?>

<script>
function selectCandidate(el, name, value) {
  // Deselect all in same position
  const container = el.closest('.card-body');
  container.querySelectorAll('.candidate-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  const radio = el.querySelector('input[type="radio"]');
  if (radio) radio.checked = true;
}
</script>

<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
