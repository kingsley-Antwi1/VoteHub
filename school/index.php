<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Parse slug from URL: /school/{slug}
$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('HTTP/1.0 404 Not Found');
    die('Institution not found');
}

$db = getDB();
$db->prepare("UPDATE elections SET status = 'closed' WHERE status = 'active' AND end_date <= NOW()")->execute();
$stmt = $db->prepare("SELECT * FROM institutions WHERE slug = ? AND status = 'active' LIMIT 1");
$stmt->execute([$slug]);
$inst = $stmt->fetch();

if (!$inst) {
    header('HTTP/1.0 404 Not Found');
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Not Found — VoteHub</title><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css"></head>
    <body class="login-page"><div class="login-box" style="text-align:center">
      <div style="font-size:4rem;margin-bottom:16px">🚫</div>
      <h3>Institution Not Available</h3>
      <p style="color:#8899bb;margin-top:8px">This institution account is not active. Contact super admin for more info.</p>
      <a href="<?= BASE_URL ?>" class="btn btn-ghost" style="margin-top:16px">Back to VoteHub</a>
    </div></body></html>
    <?php
    exit;
}

// Store institution context in session
$_SESSION['portal_institution_id'] = $inst['id'];
$_SESSION['portal_institution_name'] = $inst['name'];
$_SESSION['portal_institution_slug'] = $inst['slug'];

// Get all elections
$allElections = $db->prepare("SELECT * FROM elections WHERE institution_id = ? ORDER BY created_at DESC");
$allElections->execute([$inst['id']]);
$allElections = $allElections->fetchAll();

// Compute effective status for each
$now = date('Y-m-d H:i:s');
$hasActive = false;
$hasSuspended = false;
$hasDeactivated = false;
$hasClosed = false;
$hasCancelled = false;
$hasFuture = false;
$suspendedTitle = '';
$deactivatedTitle = '';
$closedTitle = '';
$closedTitle = '';
$cancelledTitle = '';
$futureStart = '';
$futureEnd = '';
$futureTitle = '';

foreach ($allElections as $el) {
    $isActive = $el['status'] === 'active' && $now >= $el['start_date'] && $now <= $el['end_date'];
    $isFuture = $now < $el['start_date'];
    if ($isActive) $hasActive = true;
    if ($el['status'] === 'suspended') { $hasSuspended = true; $suspendedTitle = $el['title']; }
    if ($el['status'] === 'deactivated') { $hasDeactivated = true; $deactivatedTitle = $el['title']; }
    if ($el['status'] === 'closed') { $hasClosed = true; $closedTitle = $el['title']; }
    if ($el['status'] === 'cancelled') { $hasCancelled = true; $cancelledTitle = $el['title']; }
    if ($isFuture && !$hasFuture) { $hasFuture = true; $futureTitle = $el['title']; $futureStart = $el['start_date']; $futureEnd = $el['end_date']; }
}

$elections = array_filter($allElections, function($el) {
    return $el['status'] !== 'cancelled' && $el['status'] !== 'deactivated';
});

$primaryColor = $inst['primary_color'] ?? '#1a1a2e';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($inst['name']) ?> — VoteHub</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4"><style>
  .inst-brand { --brand: <?= e($primaryColor) ?>; }
</style>
</head>
<body class="inst-brand">
<div class="inst-header" style="border-bottom-color:<?= e($primaryColor) ?>33">
  <?php if ($inst['logo']): ?>
    <img src="<?= BASE_URL ?>/assets/uploads/institutions/<?= e($inst['logo']) ?>" class="inst-logo">
  <?php endif; ?>
  <div>
    <h1><?= e($inst['name']) ?></h1>
    <span style="font-size:.8rem;color:#8899bb"><?= e($inst['location'] ?: 'VoteHub Portal') ?></span>
  </div>
  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
    <a href="<?= BASE_URL ?>" class="inst-nav-link">🏛 VoteHub</a>
    <?php if (isVoter() && currentInstitutionId() === $inst['id']): ?>
      <a href="<?= BASE_URL ?>/voter/ballot.php" class="inst-nav-link">🗳 Vote Now</a>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="inst-nav-link">Logout</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/auth/voter-login.php?school=<?= e($slug) ?>" class="inst-nav-link">🔑 Voter Login</a>
      <a href="<?= BASE_URL ?>/auth/voter-register.php?school=<?= e($slug) ?>" class="inst-nav-link">📝 Register</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/auth/inst-login.php" class="inst-nav-link">Admin</a>
  </div>
</div>

<div class="page-content" style="max-width:800px;margin:0 auto">
  <?php if ($inst['about']): ?>
    <div class="card">
      <div class="card-body"><?= nl2br(e($inst['about'])) ?></div>
    </div>
  <?php endif; ?>

  <?php if (empty($allElections)): ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:40px">
      <div style="font-size:3rem;margin-bottom:12px">🗳️</div>
      <h3>No Elections Yet</h3>
      <p style="color:#8899bb;font-size:.85rem">No elections have been created for this institution yet.</p>
    </div></div>
  <?php elseif (!$hasActive && !$hasFuture): ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:40px">
      <div style="font-size:3rem;margin-bottom:12px">🗳️</div>
      <?php if ($hasSuspended): ?>
        <h3>⏸️ Voting Suspended</h3>
        <p style="color:#c9a127;font-size:1rem;margin:4px 0"><?= e($suspendedTitle) ?></p>
        <p style="color:#8899bb;font-size:.85rem">Voting has been temporarily suspended by the administrator. It may resume later.</p>
      <?php elseif ($hasDeactivated): ?>
        <h3>⏸️ Voting Deactivated</h3>
        <p style="color:#c9a127;font-size:1rem;margin:4px 0"><?= e($deactivatedTitle) ?></p>
        <p style="color:#8899bb;font-size:.85rem">This election has been deactivated by the administrator. Check back for future elections.</p>
      <?php elseif ($hasClosed): ?>
        <h3>📊 Election Closed</h3>
        <p style="color:#c9a127;font-size:1rem;margin:4px 0"><?= e($closedTitle) ?></p>
        <p style="color:#8899bb;font-size:.85rem">This election has ended. Results may be available.</p>
      <?php elseif ($hasCancelled): ?>
        <h3>🚫 Election Cancelled</h3>
        <p style="color:#c9a127;font-size:1rem;margin:4px 0"><?= e($cancelledTitle) ?></p>
        <p style="color:#8899bb;font-size:.85rem">This election was cancelled. Check back for future elections.</p>
      <?php else: ?>
        <h3>No Active Elections</h3>
        <p style="color:#8899bb;font-size:.85rem">There are no elections currently. Check back later.</p>
      <?php endif; ?>
    </div></div>
  <?php elseif (!empty($elections)): ?>
    <h3 style="margin-bottom:16px">📋 Elections</h3>
    <div class="row">
<?php foreach ($elections as $e):
  $now = date('Y-m-d H:i:s');
  $isActive = $e['status'] === 'active' && $now >= $e['start_date'] && $now <= $e['end_date'];
  $isUpcoming = $now < $e['start_date'];
  $isEnded = $now > $e['end_date'] || $e['status'] === 'closed';
  $displayStatus = $isEnded ? 'ended' : ($isActive ? 'active' : ($isUpcoming ? 'upcoming' : $e['status']));
?>
      <div class="col" style="min-width:280px">
        <div class="card">
          <div class="card-body">
            <h4 style="color:#1a56db;margin-bottom:8px"><?= e($e['title']) ?></h4>
            <div style="font-size:.8rem;color:#8899bb;margin-bottom:12px">
              <?= date('d M Y, H:i', strtotime($e['start_date'])) ?> — <?= date('d M Y, H:i', strtotime($e['end_date'])) ?>
            </div>
            <div style="margin-bottom:8px"><span class="badge badge-<?= $isEnded ? 'secondary' : ($isActive ? 'success' : 'warning') ?>"><?= $displayStatus ?></span></div>
            <?php if ($e['description']): ?>
              <p style="font-size:.82rem;color:#8899bb"><?= e($e['description']) ?></p>
            <?php endif; ?>
            <?php if ($isEnded && $e['show_results']): ?>
              <a href="<?= BASE_URL ?>/school/results.php?slug=<?= e($slug) ?>&election_id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm" style="margin-top:8px">📈 View Results</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
