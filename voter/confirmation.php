<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireVoter();
$db = getDB();
$voterId = (int)($_SESSION['voter_id'] ?? 0);
$instId = currentInstitutionId();

$voter = $db->prepare("SELECT * FROM voters WHERE id = ?");
$voter->execute([$voterId]);
$v = $voter->fetch();

$pageTitle = 'Vote Submitted';
include __DIR__ . '/../includes/voter-header.php';
?>
<div class="card"><div class="card-body" style="text-align:center;padding:60px">
  <div style="font-size:4rem;margin-bottom:16px">✅</div>
  <h3>Your Vote Has Been Cast!</h3>
  <p style="color:#8899bb;margin-bottom:8px">Thank you, <strong><?= e($v['full_name'] ?? '') ?></strong>.</p>
  <p style="color:#8899bb;margin-bottom:24px">Your vote has been recorded securely. You cannot vote again.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/school/<?= e($_SESSION['inst_slug'] ?? '') ?>" class="btn btn-ghost">Back to Portal</a>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-gold">Logout</a>
  </div>
</div></div>
<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
