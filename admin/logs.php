<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = $_GET['per_page'] ?? '10';
$validPerPage = ['5','10','15','20','25','30','35','40','50','all'];
if (!in_array((string)$perPage, $validPerPage, true)) $perPage = '10';

$total = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = $perPage === 'all' ? 1 : max(1, (int)ceil($total / (int)$perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = $perPage === 'all' ? 0 : ((int)$perPage * ($page - 1));
$limit = $perPage === 'all' ? '' : "LIMIT " . (int)$perPage . " OFFSET $offset";

$logs = $db->query("
    SELECT l.*, i.name AS inst_name 
    FROM audit_logs l 
    LEFT JOIN institutions i ON i.id = l.institution_id 
    ORDER BY l.created_at DESC 
    $limit
")->fetchAll();

$pageTitle = 'Audit Logs';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
  <h2>🕵️ Audit Logs</h2>
  <div style="display:flex;align-items:center;gap:8px;font-size:.82rem">
    <span>Show</span>
    <select onchange="location.search='?page=1&per_page='+this.value" style="padding:4px 8px;border:1px solid rgba(201,161,39,.35);border-radius:6px;background:#111;color:#fff;font-size:.82rem">
      <?php foreach ($validPerPage as $v): ?>
        <option value="<?= $v ?>" <?= $perPage === $v ? 'selected' : '' ?>><?= $v === 'all' ? 'All' : $v ?></option>
      <?php endforeach; ?>
    </select>
    <span>of <strong><?= $total ?></strong></span>
  </div>
</div>
<style>
.paginate { display:inline-flex;align-items:center;justify-content:center;min-width:32px;padding:4px 10px;font-size:.8rem;border:1px solid rgba(201,161,39,.35);border-radius:6px;background:#111;color:#fff;text-decoration:none;transition:all .2s }
.paginate:hover { color:#c9a127;border-color:#c9a127 }
.paginate.active { background:#c9a127;color:#111;border-color:#c9a127;font-weight:700 }
.paginate.disabled { opacity:.35;pointer-events:none }
</style>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Time</th><th>Institution</th><th>User Type</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="6" style="text-align:center;color:#8899bb;padding:24px">No logs yet</td></tr>
        <?php else: ?>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-size:.75rem;white-space:nowrap"><?= date('d M H:i', strtotime($l['created_at'])) ?></td>
          <td><?= e($l['inst_name'] ?? '—') ?></td>
          <td><span class="badge badge-secondary"><?= e($l['user_type']) ?></span></td>
          <td><code style="font-size:.78rem"><?= e($l['action']) ?></code></td>
          <td style="font-size:.8rem;color:#8899bb"><?= e($l['description'] ?: '—') ?></td>
          <td style="font-size:.72rem;color:#8899bb"><?= e($l['ip_address'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($totalPages > 1): ?>
      <tfoot>
        <tr><td colspan="6" style="text-align:center;padding:10px">
          <div style="display:flex;align-items:center;justify-content:center;gap:6px">
            <a href="?page=1&per_page=<?= $perPage ?>" class="paginate <?= $page <= 1 ? 'disabled' : '' ?>">««</a>
            <a href="?page=<?= max(1, $page - 1) ?>&per_page=<?= $perPage ?>" class="paginate <?= $page <= 1 ? 'disabled' : '' ?>">«</a>
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): ?>
              <a href="?page=<?= $i ?>&per_page=<?= $perPage ?>" class="paginate <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages, $page + 1) ?>&per_page=<?= $perPage ?>" class="paginate <?= $page >= $totalPages ? 'disabled' : '' ?>">»</a>
            <a href="?page=<?= $totalPages ?>&per_page=<?= $perPage ?>" class="paginate <?= $page >= $totalPages ? 'disabled' : '' ?>">»»</a>
          </div>
        </td></tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
