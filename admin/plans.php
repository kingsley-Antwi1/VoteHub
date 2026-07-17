<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    verifyCsrf();
    $id = (int)($_POST['plan_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $maxElections = (int)($_POST['max_elections'] ?? 1);
    $maxVoters = (int)($_POST['max_voters'] ?? 100);
    $maxCandidates = (int)($_POST['max_candidates'] ?? 50);
    $customBranding = isset($_POST['custom_branding']) ? 1 : 0;
    $prioritySupport = isset($_POST['priority_support']) ? 1 : 0;
    $price = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_days'] ?? 365);

    if ($id) {
        $db->prepare("UPDATE subscription_plans SET name=?, max_elections=?, max_voters=?, max_candidates=?, custom_branding=?, priority_support=?, price=?, duration_days=? WHERE id=?")
           ->execute([$name, $maxElections, $maxVoters, $maxCandidates, $customBranding, $prioritySupport, $price, $duration, $id]);
        flash('success', 'Plan updated');
    } else {
        $db->prepare("INSERT INTO subscription_plans (name, max_elections, max_voters, max_candidates, custom_branding, priority_support, price, duration_days) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$name, $maxElections, $maxVoters, $maxCandidates, $customBranding, $prioritySupport, $price, $duration]);
        flash('success', 'Plan created');
    }
    redirect(BASE_URL . '/admin/plans.php');
}

$plans = $db->query("SELECT * FROM subscription_plans ORDER BY price ASC")->fetchAll();

$pageTitle = 'Subscription Plans';
include __DIR__ . '/../includes/admin-header.php';
?>
<?= renderFlash() ?>

<div class="page-header">
  <h2>💎 Subscription Plans</h2>
</div>

<div class="row">
  <?php foreach ($plans as $p): ?>
  <div class="col" style="min-width:280px;max-width:350px">
    <div class="card" style="text-align:center;padding:24px;position:relative;<?= $p['price'] == 0 ? 'border-color:rgba(201,161,39,.3)' : '' ?>">
      <?php if ($p['price'] == 0): ?>
        <div style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#c9a127;color:#1a1a2e;padding:2px 16px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase">Free</div>
      <?php endif; ?>
      <h3 style="color:#c9a127;margin:12px 0 4px;font-size:1.1rem"><?= e($p['name']) ?></h3>
      <div style="font-size:2rem;font-weight:800;color:#e0e0e0;margin:8px 0">
        ₵<?= number_format($p['price'], 2) ?>
        <span style="font-size:.8rem;font-weight:400;color:#8899bb">/ <?= $p['duration_days'] ?> days</span>
      </div>
      <div style="font-size:.82rem;color:#8899bb;margin:12px 0;line-height:2">
        <div>📋 Up to <?= $p['max_elections'] == 999 ? 'unlimited' : $p['max_elections'] ?> elections</div>
        <div>👤 Up to <?= $p['max_voters'] == 99999 ? 'unlimited' : number_format($p['max_voters']) ?> voters</div>
        <div>🏆 Up to <?= $p['max_candidates'] == 999 ? 'unlimited' : $p['max_candidates'] ?> candidates</div>
        <div><?= $p['custom_branding'] ? '✅ Custom Branding' : '❌ Custom Branding' ?></div>
        <div><?= $p['priority_support'] ? '✅ Priority Support' : '❌ Priority Support' ?></div>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="editPlan(<?= $p['id'] ?>, '<?= e($p['name']) ?>', <?= $p['max_elections'] ?>, <?= $p['max_voters'] ?>, <?= $p['max_candidates'] ?>, <?= $p['custom_branding'] ?>, <?= $p['priority_support'] ?>, <?= $p['price'] ?>, <?= $p['duration_days'] ?>)">✏️ Edit</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Edit/Create Plan Modal -->
<div class="modal-overlay" id="planModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-body">
      <h3 id="planModalTitle" style="color:#c9a127;margin-bottom:16px">Edit Plan</h3>
      <form method="POST">
        <input type="hidden" name="save_plan" value="1">
        <?= csrfField() ?>
        <input type="hidden" name="plan_id" id="plan_id" value="0">
        <div class="row" style="gap:12px">
          <div class="col" style="min-width:0"><div class="form-group">
            <label class="form-label">Plan Name</label>
            <input type="text" name="name" id="plan_name" class="form-control" required>
          </div></div>
          <div class="col" style="min-width:0"><div class="form-group">
            <label class="form-label">Price (₵)</label>
            <input type="number" name="price" id="plan_price" class="form-control" step="0.01" required>
          </div></div>
        </div>
        <div class="row" style="gap:12px">
          <div class="col" style="min-width:0"><div class="form-group">
            <label class="form-label">Max Elections</label>
            <input type="number" name="max_elections" id="plan_max_elec" class="form-control">
          </div></div>
          <div class="col" style="min-width:0"><div class="form-group">
            <label class="form-label">Max Voters</label>
            <input type="number" name="max_voters" id="plan_max_voters" class="form-control">
          </div></div>
          <div class="col" style="min-width:0"><div class="form-group">
            <label class="form-label">Max Candidates</label>
            <input type="number" name="max_candidates" id="plan_max_cands" class="form-control">
          </div></div>
        </div>
        <div class="row" style="gap:12px">
          <div class="col" style="min-width:0"><div class="form-group">
            <label class="form-label">Duration (days)</label>
            <input type="number" name="duration_days" id="plan_duration" class="form-control" value="365">
          </div></div>
          <div class="col" style="min-width:0;display:flex;gap:16px;padding-top:24px">
            <label><input type="checkbox" name="custom_branding" id="plan_branding" value="1"> Custom Branding</label>
            <label><input type="checkbox" name="priority_support" id="plan_support" value="1"> Priority Support</label>
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button type="button" class="btn btn-ghost" style="flex:1" onclick="closeModal('planModal')">Cancel</button>
          <button type="submit" class="btn btn-gold" style="flex:1">Save Plan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editPlan(id, name, maxE, maxV, maxC, branding, support, price, duration) {
  document.getElementById('plan_id').value = id;
  document.getElementById('plan_name').value = name;
  document.getElementById('plan_price').value = price;
  document.getElementById('plan_max_elec').value = maxE;
  document.getElementById('plan_max_voters').value = maxV;
  document.getElementById('plan_max_cands').value = maxC;
  document.getElementById('plan_duration').value = duration;
  document.getElementById('plan_branding').checked = !!branding;
  document.getElementById('plan_support').checked = !!support;
  document.getElementById('planModalTitle').textContent = id ? 'Edit Plan' : 'New Plan';
  openModal('planModal');
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
