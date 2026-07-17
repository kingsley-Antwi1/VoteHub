<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

// Handle status change
$action = $_POST['action'] ?? '';
$actionId = (int)($_POST['id'] ?? 0);
if ($action && $actionId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (in_array($action, ['approve','suspend','activate','deactivate'])) {
        $status = match($action) { 'approve'=>'active', 'suspend'=>'suspended', 'activate'=>'active', 'deactivate'=>'deactivated' };
        $db->prepare("UPDATE institutions SET status = ? WHERE id = ?")->execute([$status, $actionId]);
        logAudit(null, 'super_admin', currentUserId(), "institution_{$action}", "Institution #$actionId {$action}d");
        flash('success', "Institution {$action}d successfully");
        redirect(BASE_URL . '/admin/institutions.php');
    }
    if ($action === 'delete') {
        $name = $db->prepare("SELECT name FROM institutions WHERE id = ?");
        $name->execute([$actionId]);
        $name = $name->fetchColumn();
        $db->prepare("DELETE FROM institutions WHERE id = ?")->execute([$actionId]);
        logAudit(null, 'super_admin', currentUserId(), 'delete_institution', "Deleted institution #$actionId ($name)");
        flash('success', "Institution <strong>$name</strong> deleted permanently.");
        redirect(BASE_URL . '/admin/institutions.php');
    }
}

$filter = $_GET['status'] ?? '';
$insts = $filter
    ? $db->prepare("SELECT * FROM institutions WHERE status = ? ORDER BY created_at DESC")
    : $db->query("SELECT * FROM institutions ORDER BY created_at DESC");
if ($filter) { $insts->execute([$filter]); $insts = $insts->fetchAll(); }
else { $insts = $insts->fetchAll(); }

$pageTitle = 'Institutions';
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>🏫 Institutions</h2>
  <a href="<?= BASE_URL ?>/admin/institution-add.php" class="btn btn-gold">➕ Add Institution</a>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
  <a href="?" class="btn btn-ghost btn-sm <?= !$filter?'btn-gold':'' ?>">All</a>
  <a href="?status=active" class="btn btn-ghost btn-sm <?= $filter==='active'?'btn-gold':'' ?>">Active</a>
  <a href="?status=pending" class="btn btn-ghost btn-sm <?= $filter==='pending'?'btn-gold':'' ?>">Pending</a>
  <a href="?status=suspended" class="btn btn-ghost btn-sm <?= $filter==='suspended'?'btn-gold':'' ?>">Suspended</a>
  <a href="?status=deactivated" class="btn btn-ghost btn-sm <?= $filter==='deactivated'?'btn-gold':'' ?>">Deactivated</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Slug</th><th>Type</th><th>Email</th><th>Plan</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($insts as $i):
          $plan = $db->prepare("SELECT name FROM subscription_plans WHERE id = ?");
          $plan->execute([$i['subscription_id']]);
          $pname = $plan->fetchColumn() ?: '—';
        ?>
        <tr>
          <td><strong><?= e($i['name']) ?></strong></td>
          <td><code>/school/<?= e($i['slug']) ?></code></td>
          <td><?= e($i['type']) ?></td>
          <td><?= e($i['contact_email']) ?></td>
          <td><?= e($pname) ?></td>
          <td><?= statusBadge($i['status']) ?></td>
          <td><?= date('d M Y', strtotime($i['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:nowrap">
            <a href="<?= BASE_URL ?>/admin/institution-view.php?id=<?= $i['id'] ?>" class="btn btn-ghost btn-sm" title="View">👁</a>
            <?php if ($i['status'] === 'pending'): ?>
              <form method="POST" style="display:inline" data-confirm="Approve <?= e($i['name']) ?>?"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $i['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-success btn-sm">Approve</button></form>
            <?php endif; ?>
            <?php if ($i['status'] === 'active'): ?>
              <form method="POST" style="display:inline" data-confirm="Suspend <?= e($i['name']) ?>?"><input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="<?= $i['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm">Suspend</button></form>
            <?php endif; ?>
            <?php if ($i['status'] === 'suspended' || $i['status'] === 'deactivated'): ?>
              <form method="POST" style="display:inline" data-confirm="Activate <?= e($i['name']) ?>?"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $i['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-success btn-sm">Activate</button></form>
            <?php endif; ?>
            <?php if ($i['status'] !== 'deactivated' && $i['status'] !== 'suspended'): ?>
              <form method="POST" style="display:inline" data-confirm="Deactivate <?= e($i['name']) ?>?"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $i['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm">Deactivate</button></form>
            <?php endif; ?>
            <form method="POST" style="display:inline" data-confirm="Permanently delete <?= e($i['name']) ?>?"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $i['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-sm" style="background:#7f1d1d;border-color:#7f1d1d;color:#fff" title="Delete">🗑</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
