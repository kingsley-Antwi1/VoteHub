<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

$elections = $db->prepare("SELECT * FROM elections WHERE institution_id = ? ORDER BY created_at DESC");
$elections->execute([$instId]);
$elections = $elections->fetchAll();

$electionData = [];
foreach ($elections as $e) {
    $positions = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
    $positions->execute([$e['id']]);
    $positions = $positions->fetchAll();
    $posData = [];
    foreach ($positions as $p) {
        $candidates = $db->prepare("SELECT * FROM candidates WHERE position_id = ?");
        $candidates->execute([$p['id']]);
        $posData[] = ['position' => $p, 'candidates' => $candidates->fetchAll()];
    }
    $electionData[] = ['election' => $e, 'positions' => $posData];
}

$pageTitle = 'Candidates';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>🏆 Candidates by Election</h2>
</div>

<?php foreach ($electionData as $ed):
  $el = $ed['election'];
?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span><strong><?= e($el['title']) ?></strong></span>
    <span><?= statusBadge($el['status']) ?> <span style="font-size:.78rem;color:#8899bb;margin-left:8px"><?= date('d M Y', strtotime($el['start_date'])) ?> — <?= date('d M Y', strtotime($el['end_date'])) ?></span></span>
  </div>
  <div class="card-body">
    <?php if (empty($ed['positions'])): ?>
      <p style="color:#8899bb;font-size:.85rem;text-align:center;padding:12px">No positions added yet.</p>
    <?php else: ?>
      <?php foreach ($ed['positions'] as $pd):
        $pos = $pd['position'];
        $cands = $pd['candidates'];
      ?>
      <div style="margin-bottom:16px;padding:12px;background:rgba(255,255,255,.03);border-radius:8px">
        <h4 style="color:#c9a127;font-size:.85rem;margin-bottom:8px"><?= e($pos['title']) ?> <span style="font-weight:400;color:#8899bb;font-size:.75rem">(<?= count($cands) ?> candidate<?= count($cands) !== 1 ? 's' : '' ?>)</span></h4>
        <?php if (empty($cands)): ?>
          <p style="color:#8899bb;font-size:.8rem;margin:4px 0">No candidates for this position.</p>
        <?php else: ?>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <?php foreach ($cands as $c): ?>
            <div style="flex:1;min-width:200px;max-width:280px;padding:12px;background:rgba(255,255,255,.06);border-radius:8px;display:flex;align-items:center;gap:12px">
              <?php if ($c['photo']): ?>
                <img src="<?= BASE_URL ?>/assets/uploads/candidates/<?= e($c['photo']) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover">
              <?php else: ?>
                <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:1.2rem">👤</div>
              <?php endif; ?>
              <div style="flex:1;min-width:0">
                <strong style="font-size:.85rem"><?= e($c['full_name']) ?></strong>
                <?php if ($c['manifesto']): ?>
                  <div style="font-size:.72rem;color:#8899bb;margin-top:2px;word-wrap:break-word"><?= e(mb_substr($c['manifesto'], 0, 120)) ?><?= mb_strlen($c['manifesto']) > 120 ? '...' : '' ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($electionData)): ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:40px">
    <div style="font-size:3rem;margin-bottom:12px">🏆</div>
    <h3>No Candidates Yet</h3>
    <p style="color:#8899bb;font-size:.85rem">Create an election and add candidates to see them here.</p>
    <a href="<?= BASE_URL ?>/institution/elections.php" class="btn btn-gold" style="margin-top:12px">Go to Elections</a>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
