<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

// Handle create/edit election
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_election'])) {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $electionDate = $_POST['election_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '08:00';
    $endTime = $_POST['end_time'] ?? '17:00';
    $startDate = $electionDate ? $electionDate . ' ' . $startTime . ':00' : '';
    $endDate = $electionDate ? $electionDate . ' ' . $endTime . ':00' : '';
    $editId = (int)($_POST['election_id'] ?? 0);
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    if ($title && $startDate && $endDate) {
        if ($editId) {
            $db->prepare("UPDATE elections SET title=?, description=?, start_date=?, end_date=?, show_results=? WHERE id=? AND institution_id=?")
               ->execute([$title, $desc, $startDate, $endDate, $showResults, $editId, $instId]);
            flash('success', 'Election updated');
        } else {
            $limitMsg = checkPlanLimit($instId, 'elections');
            if ($limitMsg) { flash('error', $limitMsg); redirect(BASE_URL . '/institution/elections.php'); }
            $db->prepare("INSERT INTO elections (institution_id, title, description, start_date, end_date, show_results) VALUES (?,?,?,?,?,?)")
               ->execute([$instId, $title, $desc, $startDate, $endDate, $showResults]);
            flash('success', 'Election created');
        }
    }
    redirect(BASE_URL . '/institution/elections.php');
}

// Handle add position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_position'])) {
    verifyCsrf();
    $electionId = (int)($_POST['election_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    if ($electionId && $title) {
        $db->prepare("INSERT INTO positions (election_id, title) VALUES (?,?)")->execute([$electionId, $title]);
        flash('success', 'Position added');
    }
    redirect(BASE_URL . '/institution/elections.php');
}

// Handle add candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_candidate'])) {
    verifyCsrf();
    if (!hasActiveSubscription($instId)) {
        flash('error', 'Payment approval required. Your institution needs an approved subscription before registering candidates.');
        redirect(BASE_URL . '/institution/payment.php');
    }
    $positionId = (int)($_POST['position_id'] ?? 0);
    $name = trim($_POST['candidate_name'] ?? '');
    $manifesto = trim($_POST['manifesto'] ?? '');
    if ($positionId && $name) {
        $limitMsg = checkPlanLimit($instId, 'candidates');
        if ($limitMsg) { flash('error', $limitMsg); redirect(BASE_URL . '/institution/elections.php'); }
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $err = validateUpload($_FILES['photo']);
            if ($err) { flash('error', $err); redirect(BASE_URL . '/institution/elections.php'); }
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) { flash('error', 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp'); redirect(BASE_URL . '/institution/elections.php'); }
            $photo = uniqid('cand_') . '.' . $ext;
            $targetDir = UPLOAD_PATH . '/candidates/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $photo);
            $imgInfo = getimagesize($targetDir . $photo);
            if ($imgInfo && ($imgInfo[0] > 300 || $imgInfo[1] > 300)) {
                $maxDim = 300;
                $src = match($ext) { 'jpeg','jpg' => imagecreatefromjpeg($targetDir . $photo), 'png' => imagecreatefrompng($targetDir . $photo), 'gif' => imagecreatefromgif($targetDir . $photo), 'webp' => imagecreatefromwebp($targetDir . $photo), default => null };
                if ($src) {
                    $ratio = min($maxDim / $imgInfo[0], $maxDim / $imgInfo[1]);
                    $newW = (int)round($imgInfo[0] * $ratio);
                    $newH = (int)round($imgInfo[1] * $ratio);
                    $dst = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $imgInfo[0], $imgInfo[1]);
                    imagejpeg($dst, $targetDir . $photo, 80);
                    imagedestroy($src); imagedestroy($dst);
                }
            }
        }
        $db->prepare("INSERT INTO candidates (position_id, full_name, photo, manifesto) VALUES (?,?,?,?)")->execute([$positionId, $name, $photo ?: null, $manifesto]);
        flash('success', 'Candidate added');
    }
    redirect(BASE_URL . '/institution/elections.php');
}

// Handle status toggle & delete
$action = $_POST['action'] ?? '';
$actionId = (int)($_POST['id'] ?? 0);
if ($action && $actionId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($action === 'delete_candidate') {
        $db->prepare("DELETE FROM candidates WHERE id = ? AND position_id IN (SELECT id FROM positions WHERE election_id IN (SELECT id FROM elections WHERE institution_id = ?))")
           ->execute([$actionId, $instId]);
        logAudit($instId, 'inst_admin', currentUserId(), 'delete_candidate', "Deleted candidate #$actionId");
        flash('success', 'Candidate deleted');
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM elections WHERE id = ? AND institution_id = ?")->execute([$actionId, $instId]);
        logAudit($instId, 'inst_admin', currentUserId(), 'delete_election', "Deleted election #$actionId");
        flash('success', 'Election deleted permanently');
    } elseif ($action === 'extend') {
        $newEnd = $_POST['new_end_date'] ?? '';
        $newTime = $_POST['new_end_time'] ?? '23:59';
        if ($newEnd) {
            $extended = $newEnd . ' ' . $newTime . ':00';
            $db->prepare("UPDATE elections SET end_date = ? WHERE id = ? AND institution_id = ?")->execute([$extended, $actionId, $instId]);
            logAudit($instId, 'inst_admin', currentUserId(), 'extend_election', "Election #$actionId extended to $extended");
            flash('success', 'Election time extended successfully');
        }
    } elseif (in_array($action, ['activate','close','cancel','suspend','deactivate'])) {
        if ($action === 'activate' && !hasActiveSubscription($instId)) {
            flash('error', 'Payment approval required. Your institution needs an approved subscription before activating elections.');
            redirect(BASE_URL . '/institution/payment.php');
        }
        if ($action === 'activate') {
            // Check max_elections plan limit
            $planCheck = $db->prepare("SELECT s.*, p.max_elections FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1");
            $planCheck->execute([$instId]);
            $planData = $planCheck->fetch();
            if ($planData && $planData['max_elections'] < 999) {
                $activeCount = (int)$db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ? AND status = 'active'")->execute([$instId]) ? 0 : 0;
                $activeStmt = $db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ? AND status = 'active'");
                $activeStmt->execute([$instId]);
                $activeCount = (int)$activeStmt->fetchColumn();
                if ($activeCount >= (int)$planData['max_elections']) {
                    flash('error', 'Maximum active elections limit (' . $planData['max_elections'] . ') reached. Close or cancel an election first.');
                    redirect(BASE_URL . '/institution/elections.php');
                }
            }
        }
        $status = match($action) { 'activate'=>'active', 'close'=>'closed', 'cancel'=>'cancelled', 'suspend'=>'suspended', 'deactivate'=>'deactivated' };
        $db->prepare("UPDATE elections SET status = ? WHERE id = ? AND institution_id = ?")->execute([$status, $actionId, $instId]);
        logAudit($instId, 'inst_admin', currentUserId(), "election_{$action}", "Election #$actionId {$action}d");
        flash('success', "Election {$action}d");
    }
    redirect(BASE_URL . '/institution/elections.php');
}

$elections = $db->prepare("SELECT * FROM elections WHERE institution_id = ? ORDER BY created_at DESC");
$elections->execute([$instId]);
$elections = $elections->fetchAll();

// Handle edit pre-fill
$editElection = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $db->prepare("SELECT id, title, description, start_date, end_date, show_results FROM elections WHERE id = ? AND institution_id = ?");
    $stmt->execute([$editId, $instId]);
    $editElection = $stmt->fetch();
}

$pageTitle = 'Elections';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>🗳 Elections</h2>
</div>

<!-- Create Election -->
<div class="card">
  <div class="card-header"><?= $editElection ? '✏️ Edit Election' : '➕ New Election' ?></div>
  <div class="card-body">
      <form method="POST">
        <input type="hidden" name="save_election" value="1">
        <?php if ($editElection): ?><input type="hidden" name="election_id" value="<?= $editElection['id'] ?>"><?php endif; ?>
        <?= csrfField() ?>
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0;flex:2"><div class="form-group">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. SRC Elections 2026" value="<?= e($editElection['title'] ?? '') ?>">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Date *</label>
          <input type="date" name="election_date" class="form-control" required value="<?= $editElection ? date('Y-m-d', strtotime($editElection['start_date'])) : '' ?>">
        </div></div>
      </div>
      <div class="row" style="gap:12px">
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">Start Time *</label>
          <input type="time" name="start_time" class="form-control" required value="<?= $editElection ? date('H:i', strtotime($editElection['start_date'])) : '08:00' ?>">
        </div></div>
        <div class="col" style="min-width:0"><div class="form-group">
          <label class="form-label">End Time *</label>
          <input type="time" name="end_time" class="form-control" required value="<?= $editElection ? date('H:i', strtotime($editElection['end_date'])) : '17:00' ?>">
        </div></div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Optional description"><?= e($editElection['description'] ?? '') ?></textarea>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;margin-bottom:12px;cursor:pointer">
        <input type="checkbox" name="show_results" value="1" <?= !$editElection || $editElection['show_results'] ? 'checked' : '' ?>> Show results to voters on portal
      </label>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-gold"><?= $editElection ? 'Update Election' : 'Create Election' ?></button>
        <?php if ($editElection): ?>
          <a href="<?= BASE_URL ?>/institution/elections.php" class="btn btn-ghost">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Election List -->
<?php foreach ($elections as $e):
  $positions = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
  $positions->execute([$e['id']]);
  $positions = $positions->fetchAll();
?>
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span><?= e($e['title']) ?></span>
    <div style="display:flex;gap:8px;align-items:center">
      <?= statusBadge($e['status']) ?>
      <a href="?edit=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
      <?php if ($e['status'] === 'pending' || empty($e['status'])): ?>
        <form method="POST" style="display:inline" data-confirm="Activate <?= e($e['title']) ?>? Voting will begin."><?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-success btn-sm">Activate</button></form>
        <form method="POST" style="display:inline" data-confirm="Cancel <?= e($e['title']) ?>? This cannot be undone."><?= csrfField() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Cancel</button></form>
      <?php endif; ?>
      <?php if ($e['status'] === 'active'): ?>
        <button class="btn btn-sm" style="background:#d97706;border:none;color:#fff" onclick="openExtend(<?= $e['id'] ?>, '<?= date('Y-m-d', strtotime($e['end_date'])) ?>', '<?= date('H:i', strtotime($e['end_date'])) ?>')">⏱ Extend</button>
        <form method="POST" style="display:inline" data-confirm="Close voting? Votes will still be counted."><?= csrfField() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-warning btn-sm">Close</button></form>
        <form method="POST" style="display:inline" data-confirm="Suspend <?= e($e['title']) ?>? Voting paused, can resume later."><?= csrfField() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Suspend</button></form>
      <?php endif; ?>
      <?php if ($e['status'] === 'deactivated' || $e['status'] === 'suspended'): ?>
        <form method="POST" style="display:inline" data-confirm="Resume <?= e($e['title']) ?>? Voting will continue."><?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-success btn-sm">Resume</button></form>
        <form method="POST" style="display:inline" data-confirm="Close <?= e($e['title']) ?>? Votes will still be counted."><?= csrfField() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-warning btn-sm">Close</button></form>
      <?php endif; ?>
      <?php if ($e['status'] !== 'cancelled' && $e['status'] !== 'closed'): ?>
        <form method="POST" style="display:inline" data-confirm="Deactivate <?= e($e['title']) ?>? Hidden from voters."><?= csrfField() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Deactivate</button></form>
      <?php endif; ?>
      <form method="POST" style="display:inline" data-confirm="Permanently delete <?= e($e['title']) ?>? All positions, candidates, and votes will be lost."><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-sm" style="background:#7f1d1d;border-color:#7f1d1d;color:#fff">🗑</button></form>
    </div>
  </div>
  <div class="card-body">
    <div style="font-size:.82rem;color:#8899bb;margin-bottom:16px">
      <?= date('d M Y H:i', strtotime($e['start_date'])) ?> — <?= date('d M Y H:i', strtotime($e['end_date'])) ?>
      <?php if ($e['description']): ?> · <?= e($e['description']) ?><?php endif; ?>
    </div>

    <!-- Positions -->
    <h4 style="font-size:.85rem;color:#c9a127;margin-bottom:8px">Positions & Candidates</h4>
    <?php foreach ($positions as $p):
      $candidates = $db->prepare("SELECT * FROM candidates WHERE position_id = ?");
      $candidates->execute([$p['id']]);
      $candidates = $candidates->fetchAll();
    ?>
    <div style="margin-bottom:12px;padding:12px;background:rgba(255,255,255,.02);border-radius:8px">
      <strong style="font-size:.85rem"><?= e($p['title']) ?></strong>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
        <?php foreach ($candidates as $c): ?>
          <span class="badge badge-success"><?= e($c['full_name']) ?> <form method="POST" style="display:inline" data-confirm="Remove <?= e($c['full_name']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="delete_candidate"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" style="background:none;border:none;color:#f87171;cursor:pointer;padding:0;margin-left:4px;font-size:1rem">&times;</button></form></span>
        <?php endforeach; ?>
        <span style="cursor:pointer;color:#c9a127;font-size:.78rem" onclick="document.getElementById('candForm_<?= $p['id'] ?>').style.display='block'">+ Add</span>
      </div>

      <!-- Add candidate form -->
      <form method="POST" enctype="multipart/form-data" id="candForm_<?= $p['id'] ?>" style="display:none;margin-top:8px">
        <input type="hidden" name="save_candidate" value="1">
        <input type="hidden" name="position_id" value="<?= $p['id'] ?>">
        <?= csrfField() ?>
        <div class="row" style="gap:8px;align-items:flex-end">
          <div style="flex:1"><input type="text" name="candidate_name" class="form-control" placeholder="Candidate name" required style="padding:6px 10px;font-size:.8rem"></div>
          <div><input type="file" name="photo" accept="image/*" style="font-size:.75rem"></div>
          <div><button type="submit" class="btn btn-gold btn-sm">Save</button></div>
        </div>
        <textarea name="manifesto" class="form-control" rows="2" placeholder="Manifesto (optional)" style="margin-top:4px;font-size:.8rem;padding:6px 10px"></textarea>
      </form>
    </div>
    <?php endforeach; ?>

    <!-- Add position form -->
    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.06)">
      <input type="hidden" name="save_position" value="1">
      <input type="hidden" name="election_id" value="<?= $e['id'] ?>">
      <?= csrfField() ?>
      <div style="flex:1"><input type="text" name="title" class="form-control" placeholder="New position (e.g. Secretary)" required style="padding:6px 10px;font-size:.8rem"></div>
      <button type="submit" class="btn btn-ghost btn-sm">➕ Add Position</button>
    </form>
  </div>
</div>
<?php endforeach; ?>

<div class="modal-overlay" id="extendModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-body">
      <h3 style="color:#d97706;margin-bottom:8px">⏱ Extend Election</h3>
      <p style="font-size:.85rem;color:#6b7280;margin-bottom:16px">Set a new end date/time for this election.</p>
      <form method="POST" id="extendForm">
        <input type="hidden" name="action" value="extend">
        <input type="hidden" name="id" id="extendId" value="0">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">New End Date</label>
          <input type="date" name="new_end_date" id="extendDate" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">New End Time</label>
          <input type="time" name="new_end_time" id="extendTime" class="form-control" value="23:59" required>
        </div>
        <div style="display:flex;gap:10px">
          <button type="button" class="btn btn-ghost" style="flex:1" onclick="closeModal('extendModal')">Cancel</button>
          <button type="submit" class="btn" style="flex:1;background:#d97706;color:#fff;border:none">Extend Time</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openExtend(id, date, time) {
  document.getElementById('extendId').value = id;
  document.getElementById('extendDate').value = date;
  document.getElementById('extendTime').value = time;
  openModal('extendModal');
}
</script>
<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
