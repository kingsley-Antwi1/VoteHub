<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

// Handle delete voter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_voter') {
    verifyCsrf();
    $voterId = (int)($_POST['voter_id'] ?? 0);
    $db->prepare("DELETE FROM voters WHERE id = ? AND institution_id = ?")->execute([$voterId, $instId]);
    logAudit($instId, 'inst_admin', currentUserId(), 'delete_voter', "Deleted voter #$voterId");
    flash('success', 'Voter deleted');
    redirect(BASE_URL . '/institution/voters.php');
}

// Handle delete all voters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_all_voters') {
    verifyCsrf();
    $count = $db->prepare("SELECT COUNT(*) FROM voters WHERE institution_id = ?");
    $count->execute([$instId]);
    $total = (int)$count->fetchColumn();
    $db->prepare("DELETE FROM voters WHERE institution_id = ?")->execute([$instId]);
    logAudit($instId, 'inst_admin', currentUserId(), 'delete_all_voters', "Deleted all $total voters");
    flash('success', "All $total voters deleted permanently");
    redirect(BASE_URL . '/institution/voters.php');
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    verifyCsrf();
    $voterId = (int)($_POST['voter_id'] ?? 0);
    $newPass = substr(bin2hex(random_bytes(4)), 0, 8);
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $db->prepare("UPDATE voters SET password = ?, status = 1 WHERE id = ? AND institution_id = ?")
       ->execute([$hash, $voterId, $instId]);
    $voter = $db->prepare("SELECT full_name FROM voters WHERE id = ?");
    $voter->execute([$voterId]);
    $vName = $voter->fetchColumn();
    logAudit($instId, 'inst_admin', currentUserId(), 'reset_password', "Reset password for voter #$voterId");
    flash('success', "Password reset for $vName. New password: $newPass");
    redirect(BASE_URL . '/institution/voters.php');
}

// CSV template download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=voter_template.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Full Name', 'Email', 'Phone', 'Level']);
    fputcsv($out, ['SHS001', 'Akosua Manu', 'akosua.manu@student.com', '0244000001', 'Form 3']);
    fputcsv($out, ['SHS002', 'Kwame Asante', 'kwame.asante@student.com', '0244000002', 'Form 3']);
    fputcsv($out, ['SHS003', 'Abena Owusu', 'abena.owusu@student.com', '0244000003', 'Form 3']);
    fputcsv($out, ['SHS004', 'Yaw Mensah', 'yaw.mensah@student.com', '0244000004', 'Form 3']);
    fputcsv($out, ['SHS005', 'Esi Nyarko', 'esi.nyarko@student.com', '0244000005', 'Form 3']);
    fputcsv($out, ['SHS006', 'Kofi Adjei', 'kofi.adjei@student.com', '0244000006', 'Form 3']);
    fputcsv($out, ['SHS007', 'Mawusi Dogbe', 'mawusi.dogbe@student.com', '0244000007', 'Form 3']);
    fputcsv($out, ['SHS008', 'Nana Yaa Boadu', 'nana.boadu@student.com', '0244000008', 'Form 2']);
    fputcsv($out, ['SHS009', 'Kwabena Osei', 'kwabena.osei@student.com', '0244000009', 'Form 2']);
    fputcsv($out, ['SHS010', 'Adwoa Sarpong', 'adwoa.sarpong@student.com', '0244000010', 'Form 2']);
    fputcsv($out, ['SHS011', 'Kojo Amankwah', 'kojo.amankwah@student.com', '0244000011', 'Form 2']);
    fputcsv($out, ['SHS012', 'Akua Boatemaa', 'akua.boatemaa@student.com', '0244000012', 'Form 2']);
    fclose($out);
    exit;
}

// Handle add voter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_voter'])) {
    verifyCsrf();
    $studentId = trim($_POST['student_id'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $autoGen = false;
    if (!$password) { $password = strtolower(explode(' ', $fullName)[0]); $autoGen = true; }
    if ($studentId && $fullName) {
        $limitMsg = checkPlanLimit($instId, 'voters');
        if ($limitMsg) { flash('error', $limitMsg); redirect(BASE_URL . '/institution/voters.php'); }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $db->prepare("INSERT INTO voters (institution_id, student_id, full_name, email, phone, level, password) VALUES (?,?,?,?,?,?,?)")
               ->execute([$instId, $studentId, $fullName, $email, $phone, $level, $hash]);
            logAudit($instId, 'inst_admin', currentUserId(), 'add_voter', "Added voter $studentId");
            $msg = "Voter $fullName added successfully";
            if ($autoGen) $msg .= ". Password: $password";
            flash('success', $msg);
        } catch (Throwable $e) {
            flash('error', 'Duplicate student ID or error adding voter');
        }
    }
    redirect(BASE_URL . '/institution/voters.php');
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['csv_file']['tmp_name'];
    if (($handle = fopen($tmp, 'r')) !== false) {
        $header = fgetcsv($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $sid = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            if ($sid && $name) $rows[] = $row;
        }
        fclose($handle);
        $limitMsg = checkPlanLimit($instId, 'voters', count($rows));
        if ($limitMsg) { flash('error', $limitMsg); redirect(BASE_URL . '/institution/voters.php'); }
        $insert = $db->prepare("INSERT IGNORE INTO voters (institution_id, student_id, full_name, email, phone, level, password) VALUES (?,?,?,?,?,?,?)");
        $count = 0;
        foreach ($rows as $row) {
            $sid = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $phone = trim($row[3] ?? '');
            $level = trim($row[4] ?? '');
            $firstName = strtolower(explode(' ', $name)[0]);
            $insert->execute([$instId, $sid, $name, $email, $phone, $level, password_hash($firstName, PASSWORD_BCRYPT)]);
            $count++;
        }
        logAudit($instId, 'inst_admin', currentUserId(), 'import_voters', "Imported $count voters via CSV");
        flash('success', "$count voters imported successfully. Each voter's password is their first name (lowercase).");
    } else {
        flash('error', 'Failed to read CSV file');
    }
    redirect(BASE_URL . '/institution/voters.php');
}

$search = trim($_GET['search'] ?? '');
$perPage = (int)($_GET['per_page'] ?? 20);
$allowedPerPage = [20, 60, 100, 120, 150, 200, 999999];
if (!in_array($perPage, $allowedPerPage)) $perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$whereClause = "v.institution_id = ?";
$countParams = [$instId];
$queryParams = [$instId, $instId];
if ($search) {
    $s = "%$search%";
    $whereClause .= " AND (v.full_name LIKE ? OR v.student_id LIKE ?)";
    $countParams[] = $s; $countParams[] = $s;
    $queryParams[] = $s; $queryParams[] = $s;
}

$totalStmt = $db->prepare("SELECT COUNT(*) FROM voters v WHERE $whereClause");
$totalStmt->execute($countParams);
$totalVoters = (int)$totalStmt->fetchColumn();
$totalPages = $perPage === 999999 ? 1 : max(1, (int)ceil($totalVoters / $perPage));
$limitClause = $perPage === 999999 ? "" : " LIMIT ? OFFSET ?";
$voters = $db->prepare("SELECT v.*, (SELECT COUNT(*) FROM votes vv JOIN elections e ON e.id = vv.election_id WHERE vv.voter_id = v.id AND e.institution_id = ?) AS voted_count FROM voters v WHERE $whereClause ORDER BY v.created_at DESC$limitClause");
if ($perPage === 999999) {
    $voters->execute($queryParams);
} else {
    $queryParams[] = $perPage; $queryParams[] = $offset;
    $voters->execute($queryParams);
}

$pageTitle = 'Voters';
include __DIR__ . '/../includes/inst-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>👤 Voters</h2>
  <form method="POST" style="display:inline" data-confirm="⚠️ PERMANENTLY delete ALL voters for this institution? This cannot be undone!">
    <input type="hidden" name="action" value="delete_all_voters">
    <?= csrfField() ?>
    <button type="submit" class="btn btn-sm" style="background:#7f1d1d;border-color:#7f1d1d;color:#fff">🗑 Delete All Voters</button>
  </form>
</div>

<div class="row" style="margin-bottom:20px">
  <div class="col">
    <div class="card">
      <div class="card-header">➕ Add Voter Manually</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="add_voter" value="1">
          <?= csrfField() ?>
          <div class="row" style="gap:12px">
            <div class="col" style="min-width:0"><div class="form-group">
              <label class="form-label">Student ID *</label>
              <input type="text" name="student_id" class="form-control" required>
            </div></div>
            <div class="col" style="min-width:0"><div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-control" required>
            </div></div>
          </div>
          <div class="row" style="gap:12px">
            <div class="col" style="min-width:0"><div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control">
            </div></div>
            <div class="col" style="min-width:0"><div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control">
            </div></div>
            <div class="col" style="min-width:0"><div class="form-group">
              <label class="form-label">Level/Year</label>
              <input type="text" name="level" class="form-control">
            </div></div>
          </div>
          <div class="form-group">
            <label class="form-label">Password <span style="font-size:.75rem;color:#8899bb">(leave blank to auto-generate)</span></label>
            <input type="text" name="password" class="form-control" placeholder="Auto-generates as first name lowercase">
          </div>
          <button type="submit" class="btn btn-gold">Add Voter</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card">
      <div class="card-header">📁 Import CSV</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <div class="form-group">
            <label class="form-label">CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            <p style="font-size:.75rem;color:#8899bb;margin-top:4px">Columns: Student ID, Full Name, Email, Phone, Level</p>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <button type="submit" class="btn btn-gold">Import</button>
            <a href="?download_template=1" class="btn btn-ghost btn-sm">📄 Download Template</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">🔍 Search</div>
      <div class="card-body">
        <form method="GET" style="display:flex;gap:8px" autocomplete="off">
          <input type="text" name="search" class="form-control" placeholder="Search by name or ID..." value="<?= e($search) ?>" style="flex:1" id="liveSearch" oninput="liveFilter(this.value)">
          <button type="submit" class="btn btn-ghost btn-sm">🔍 Search</button>
          <?php if ($search): ?><a href="?" class="btn btn-ghost btn-sm">✕ Clear</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">📋 Voter List</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Level</th><th>Status</th><th>Voted</th><th>Actions</th><th>Added</th></tr></thead>
      <tbody id="voterTableBody">
        <?php foreach ($voters as $v): ?>
        <tr data-search="<?= e(strtolower($v['student_id'] . ' ' . $v['full_name'])) ?>">
          <td><code><?= e($v['student_id']) ?></code></td>
          <td><strong><?= e($v['full_name']) ?></strong></td>
          <td><?= e($v['email'] ?: '—') ?></td>
          <td><?= e($v['level'] ?: '—') ?></td>
          <td><?= $v['status'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
          <td><?= $v['voted_count'] > 0 ? "✅ Yes ({$v['voted_count']})" : '—' ?></td>
          <td>
            <form method="POST" style="display:inline" data-confirm="Reset password for <?= e($v['full_name']) ?>?">
              <input type="hidden" name="voter_id" value="<?= $v['id'] ?>">
              <input type="hidden" name="action" value="reset_password">
              <?= csrfField() ?>
              <button type="submit" class="btn btn-ghost btn-sm" style="color:#f59e0b">🔑 Reset</button>
            </form>
            <form method="POST" style="display:inline" data-confirm="Delete <?= e($v['full_name']) ?> and all their votes?">
              <input type="hidden" name="voter_id" value="<?= $v['id'] ?>">
              <input type="hidden" name="action" value="delete_voter">
              <?= csrfField() ?>
              <button type="submit" class="btn btn-ghost btn-sm" style="color:#ef4444">🗑</button>
            </form>
          </td>
          <td style="font-size:.78rem"><?= date('d M Y', strtotime($v['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;padding:12px 16px;background:#1a1a2e;border-radius:8px">
  <div style="font-size:.82rem;color:#8899bb"><?= $totalVoters ?> voter<?= $totalVoters !== 1 ? 's' : '' ?> found</div>
  <div style="display:flex;align-items:center;gap:8px;font-size:.82rem">
    <span style="color:#8899bb">Show:</span>
    <select onchange="location.href='?search=<?= e($search) ?>&per_page='+this.value" style="background:#1a1a2e;border:1px solid #333;color:#e0e0e0;padding:4px 8px;border-radius:6px;font-size:.78rem">
      <?php foreach ([20,60,100,120,150,200,999999] as $opt):
        $label = $opt === 999999 ? 'All' : $opt; ?>
        <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;gap:4px;flex-wrap:wrap">
    <?php if ($page > 1): ?>
      <a href="?search=<?= e($search) ?>&per_page=<?= $perPage ?>&page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?search=<?= e($search) ?>&per_page=<?= $perPage ?>&page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-gold' : 'btn-ghost' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?search=<?= e($search) ?>&per_page=<?= $perPage ?>&page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function liveFilter(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#voterTableBody tr').forEach(row => {
    row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
  });
}
</script>
<?php include __DIR__ . '/../includes/inst-footer.php'; ?>
