<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

$electionId = (int)($_GET['election_id'] ?? 0);
$elections = $db->prepare("SELECT * FROM elections WHERE institution_id = ? ORDER BY created_at DESC");
$elections->execute([$instId]);

$election = null;
if ($electionId) {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND institution_id = ?");
    $stmt->execute([$electionId, $instId]);
    $election = $stmt->fetch();
}

$pageTitle = 'Results';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>📈 Election Results</h2>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1">
        <label class="form-label">Select Election</label>
        <select name="election_id" class="form-control" onchange="this.form.submit()">
          <option value="">— Choose —</option>
          <?php foreach ($elections as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $electionId === $e['id'] ? 'selected' : '' ?>>
              <?= e($e['title']) ?> (<?= $e['status'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($election): 
  $positions = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
  $positions->execute([$election['id']]);
  $totalVotes = (function() use ($db, $election) { $s = $db->prepare("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = ?"); $s->execute([$election['id']]); return (int)$s->fetchColumn(); })();
  $totalVoters = (function() use ($db, $instId) { $s = $db->prepare("SELECT COUNT(*) FROM voters WHERE institution_id = ? AND status = 1"); $s->execute([$instId]); return (int)$s->fetchColumn(); })();
  $turnout = $totalVoters > 0 ? round(($totalVotes / $totalVoters) * 100, 1) : 0;
?>
<div class="row" style="margin-bottom:20px">
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVotes ?></div><div class="stat-label">Votes Cast</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVoters ?></div><div class="stat-label">Registered Voters</div></div></div>
  <div class="col"><div class="stat-card"><div class="stat-num"><?= $turnout ?>%</div><div class="stat-label">Turnout</div></div></div>
</div>

<?php $rank = 0; while ($pos = $positions->fetch()):
  $results = $db->prepare("
    SELECT c.id, c.full_name, c.photo, c.manifesto, COUNT(v.id) AS vote_count
    FROM candidates c
    LEFT JOIN votes v ON v.candidate_id = c.id AND v.position_id = c.position_id AND v.election_id = ?
    WHERE c.position_id = ?
    GROUP BY c.id
    ORDER BY vote_count DESC
  ");
  $results->execute([$election['id'], $pos['id']]);
  $results = $results->fetchAll();
  $maxVotes = !empty($results) ? max(array_column($results, 'vote_count')) : 0;
  $posTotalVotes = !empty($results) ? array_sum(array_column($results, 'vote_count')) : 0;
?>
<div class="card">
  <div class="card-header">
    <?= e($pos['title']) ?>
    <?php if ($election['status'] !== 'active'): ?>
      <span style="float:right;font-size:.78rem;color:#22c55e">✓ Results Final</span>
    <?php else: ?>
      <span style="float:right;font-size:.78rem;color:#f59e0b">⚡ Voting in Progress</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th style="width:40px">#</th><th>Candidate</th><th>Votes</th><th>%</th><th>Bar</th></tr></thead>
      <tbody>
        <?php $rank = 1; foreach ($results as $r):
          $pct = $posTotalVotes > 0 ? round(($r['vote_count'] / $posTotalVotes) * 100, 1) : 0;
          $barWidth = $maxVotes > 0 ? round(($r['vote_count'] / $maxVotes) * 100) : 0;
          $isWinner = $r['vote_count'] > 0 && $r['vote_count'] === $maxVotes;
          $rankLabel = $rank === 1 ? '1st' : ($rank === 2 ? '2nd' : ($rank === 3 ? '3rd' : $rank . 'th'));
        ?>
        <tr <?= $isWinner ? 'style="background:rgba(26,86,219,.06)"' : '' ?>>
          <td style="text-align:center;font-weight:700;color:<?= $isWinner ? '#1a56db' : '#6b7280' ?>"><?= $rankLabel ?></td>
          <td style="display:flex;align-items:center;gap:10px;padding:8px 12px">
            <?php if ($r['photo']): ?>
              <img src="<?= BASE_URL ?>/assets/uploads/candidates/<?= e($r['photo']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
            <?php else: ?>
              <span style="width:36px;height:36px;border-radius:50%;background:#e5e7eb;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;color:#8899bb">👤</span>
            <?php endif; ?>
            <div>
              <strong><?= $isWinner ? '🏆 ' : '' ?><?= e($r['full_name']) ?></strong><?= $isWinner ? ' <span style="color:#059669;font-size:.75rem">WINNER</span>' : '' ?>
              <?php if ($r['manifesto'] ?? ''): ?>
                <div style="font-size:.72rem;color:#6b7280;margin-top:2px"><?= e(mb_substr($r['manifesto'], 0, 100)) ?><?= mb_strlen($r['manifesto'] ?? '') > 100 ? '...' : '' ?></div>
              <?php endif; ?>
            </div>
          </td>
          <td><strong style="color:#1a56db"><?= $r['vote_count'] ?></strong></td>
          <td><?= $pct ?>%</td>
          <td style="width:200px">
            <div style="background:#e5e7eb;border-radius:6px;overflow:hidden;height:12px">
              <div style="background:<?= $isWinner ? 'linear-gradient(90deg,#059669,#047857)' : 'linear-gradient(90deg,#1a56db,#1648c0)' ?>;height:100%;border-radius:6px;width:<?= $barWidth ?>%;transition:width 1s"></div>
            </div>
          </td>
        </tr>
        <?php $rank++; endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endwhile; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
