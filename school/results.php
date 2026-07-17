<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$electionId = (int)($_GET['election_id'] ?? 0);
if (!$slug || !$electionId) { header('HTTP/1.0 404 Not Found'); die('Invalid request.'); }

$db = getDB();
$db->prepare("UPDATE elections SET status = 'closed' WHERE status = 'active' AND end_date <= NOW()")->execute();

$inst = $db->prepare("SELECT * FROM institutions WHERE slug = ? AND status = 'active' LIMIT 1");
$inst->execute([$slug]);
$inst = $inst->fetch();
if (!$inst) { header('HTTP/1.0 404 Not Found'); die('Institution not found.'); }

$election = $db->prepare("SELECT * FROM elections WHERE id = ? AND institution_id = ? AND status != 'active'");
$election->execute([$electionId, $inst['id']]);
$election = $election->fetch();
if (!$election) { header('HTTP/1.0 404 Not Found'); die('Results not available.'); }

$primaryColor = $inst['primary_color'] ?? '#1a1a2e';

$positions = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
$positions->execute([$election['id']]);

$totalVotes = (function() use ($db, $election) { $s = $db->prepare("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = ?"); $s->execute([$election['id']]); return (int)$s->fetchColumn(); })();
$totalVoters = (function() use ($db, $inst) { $s = $db->prepare("SELECT COUNT(*) FROM voters WHERE institution_id = ? AND status = 1"); $s->execute([$inst['id']]); return (int)$s->fetchColumn(); })();
$turnout = $totalVoters > 0 ? round(($totalVotes / $totalVoters) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($election['title']) ?> — Results — <?= e($inst['name']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>.inst-brand { --brand: <?= e($primaryColor) ?>; }</style>
</head>
<body class="inst-brand">
<div class="inst-header" style="border-bottom-color:<?= e($primaryColor) ?>33">
  <div><h1><?= e($inst['name']) ?></h1></div>
  <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
    <a href="<?= isSuperAdmin() ? BASE_URL . '/admin/dashboard.php' : (isInstAdmin() ? BASE_URL . '/institution/dashboard.php' : BASE_URL) ?>" class="btn btn-ghost btn-sm">Dashboard</a>
    <?php if (isSuperAdmin()): ?>
      <a href="<?= BASE_URL ?>/admin/institution-view.php?id=<?= $inst['id'] ?>" class="btn btn-ghost btn-sm">🏛 Institution</a>
    <?php elseif (isInstAdmin() && currentInstitutionId() === $inst['id']): ?>
      <a href="<?= BASE_URL ?>/institution/results.php" class="btn btn-ghost btn-sm">🏛 Institution</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/school/<?= e($slug) ?>" class="btn btn-ghost btn-sm">Voting Portal</a>
  </div>
</div>
<div class="page-content" style="max-width:800px;margin:0 auto">
  <div class="page-header"><h2>📊 <?= e($election['title']) ?> — Results</h2></div>

  <div class="row" style="margin-bottom:20px">
    <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVotes ?></div><div class="stat-label">Votes Cast</div></div></div>
    <div class="col"><div class="stat-card"><div class="stat-num"><?= $totalVoters ?></div><div class="stat-label">Registered Voters</div></div></div>
    <div class="col"><div class="stat-card"><div class="stat-num"><?= $turnout ?>%</div><div class="stat-label">Turnout</div></div></div>
  </div>

  <?php while ($pos = $positions->fetch()):
    $results = (function() use ($db, $election, $pos) { $s = $db->prepare("SELECT c.id, c.full_name, c.photo, c.manifesto, COUNT(v.id) AS vote_count FROM candidates c LEFT JOIN votes v ON v.candidate_id = c.id AND v.position_id = c.position_id AND v.election_id = ? WHERE c.position_id = ? GROUP BY c.id ORDER BY vote_count DESC"); $s->execute([$election['id'], $pos['id']]); return $s->fetchAll(); })();
    $maxVotes = !empty($results) ? max(array_column($results, 'vote_count')) : 0;
    $posTotal = !empty($results) ? array_sum(array_column($results, 'vote_count')) : 0;
  ?>
  <div class="card">
    <div class="card-header"><?= e($pos['title']); if ($election['status'] !== 'active') { echo ' <span style="float:right;font-size:.78rem;color:#22c55e">✓ Final</span>'; } else { echo ' <span style="float:right;font-size:.78rem;color:#f59e0b">⚡ In Progress</span>'; } ?></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th style="width:40px">#</th><th>Candidate</th><th>Votes</th><th>%</th><th>Bar</th></tr></thead>
        <tbody>
          <?php $rank = 1; foreach ($results as $r):
            $pct = $posTotal > 0 ? round(($r['vote_count'] / $posTotal) * 100, 1) : 0;
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
                <div style="background:<?= $isWinner ? 'linear-gradient(90deg,#059669,#047857)' : 'linear-gradient(90deg,#1a56db,#1648c0)' ?>;height:100%;border-radius:6px;width:<?= $barWidth ?>%"></div>
              </div>
            </td>
          </tr>
          <?php $rank++; endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endwhile; ?>
</div>
</body>
</html>
