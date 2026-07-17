<?php
// Institution status & subscription check
if (isset($_SESSION['institution_id'])) {
    $db = getDB();

    // Check institution status
    $check = $db->prepare("SELECT status FROM institutions WHERE id = ?");
    $check->execute([$_SESSION['institution_id']]);
    $instStatus = $check->fetchColumn();
    if ($instStatus && $instStatus !== 'active') {
        $_SESSION['flash_error'] = 'Your institution account has been ' . $instStatus . '. Contact super admin for more info.';
        unset($_SESSION['inst_admin_id'], $_SESSION['institution_id'], $_SESSION['inst_admin_name'], $_SESSION['inst_name'], $_SESSION['inst_slug'], $_SESSION['inst_role']);
        redirect(BASE_URL . '/auth/inst-login.php');
    }

    // Subscription expiry check
    $instSubStmt = $db->prepare("SELECT s.*, p.name AS plan_name FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1");
    $instSubStmt->execute([$_SESSION['institution_id']]);
    $subData = $instSubStmt->fetch();
    if ($subData && strtotime($subData['end_date']) < strtotime(date('Y-m-d'))) {
        $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE id = ?")->execute([$subData['id']]);
        $_SESSION['sub_expired'] = true;
        $_SESSION['sub_plan'] = $subData['plan_name'];
        $_SESSION['sub_end'] = $subData['end_date'];
    } elseif ($subData) {
        $_SESSION['sub_expired'] = false;
    }
    // Block key pages when expired
    if (!empty($_SESSION['sub_expired'])) {
        $allowedPages = ['profile.php', 'dashboard.php', 'payment.php'];
        $currentPage = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentPage, $allowedPages)) {
            $_SESSION['flash_error'] = 'Your subscription has expired. Please renew to access this feature.';
            redirect(BASE_URL . '/institution/profile.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($_SESSION['inst_name'] ?? '') ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
<script>const BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="logo">
      <h1>🏛 <?= e($_SESSION['inst_name'] ?? 'Portal') ?></h1>
      <span><?= e(ucfirst($_SESSION['inst_role'] ?? 'admin')) ?></span>
    </div>
    <div style="padding:8px 16px;position:relative">
      <input type="text" id="pageSearch" class="form-control" placeholder="🔍 Search pages & content..." autocomplete="off" oninput="globalSearch(this.value)" style="font-size:.78rem;padding:6px 10px">
      <div id="searchResults" style="display:none;position:absolute;top:100%;left:16px;right:16px;background:#1a1a2e;border:1px solid rgba(255,255,255,.1);border-radius:8px;z-index:999;max-height:300px;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.5)"></div>
    </div>
    <div id="navList">
    <?php $currentInstPage = basename($_SERVER['PHP_SELF']); ?>
    <a href="<?= BASE_URL ?>/institution/dashboard.php" class="nav-item <?= $currentInstPage === 'dashboard.php' ? 'active' : '' ?>" data-nav="dashboard">📊 <span class="nav-label">Dashboard</span></a>
    <a href="<?= BASE_URL ?>/institution/voters.php" class="nav-item <?= $currentInstPage === 'voters.php' ? 'active' : '' ?>" data-nav="voters">👤 <span class="nav-label">Voters</span></a>
    <a href="<?= BASE_URL ?>/institution/elections.php" class="nav-item <?= $currentInstPage === 'elections.php' ? 'active' : '' ?>" data-nav="elections">🗳 <span class="nav-label">Elections</span></a>
    <a href="<?= BASE_URL ?>/institution/candidates.php" class="nav-item <?= $currentInstPage === 'candidates.php' ? 'active' : '' ?>" data-nav="candidates">🏆 <span class="nav-label">Candidates</span></a>
    <a href="<?= BASE_URL ?>/institution/results.php" class="nav-item <?= $currentInstPage === 'results.php' ? 'active' : '' ?>" data-nav="results">📈 <span class="nav-label">Results</span></a>
    <a href="<?= BASE_URL ?>/institution/payment.php" class="nav-item <?= $currentInstPage === 'payment.php' ? 'active' : '' ?>" data-nav="payment">💳 <span class="nav-label">Payments</span></a>
    <a href="<?= BASE_URL ?>/institution/profile.php" class="nav-item <?= $currentInstPage === 'profile.php' ? 'active' : '' ?>" data-nav="profile">⚙️ <span class="nav-label">Settings</span></a>
    </div>
    <div style="margin-top:auto;padding:12px 16px;border-top:1px solid rgba(255,255,255,.06);font-size:.75rem">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;position:relative" title="Contact super admin for more info">
        <a href="tel:0245599539" style="color:#e0e0e0;text-decoration:none;display:flex;align-items:center;gap:6px;transition:color .2s" onmouseover="this.style.color='#c9a127'" onmouseout="this.style.color='#e0e0e0'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span>Call Super Admin</span>
        </a>
      </div>
      <div style="display:flex;align-items:center;gap:8px;position:relative" title="Contact super admin for more info">
        <a href="https://wa.me/233245599539" target="_blank" style="color:#e0e0e0;text-decoration:none;display:flex;align-items:center;gap:6px;transition:color .2s" onmouseover="this.style.color='#22c55e'" onmouseout="this.style.color='#e0e0e0'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="#22c55e"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          <span>WhatsApp Super Admin</span>
        </a>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item" style="color:#ef4444;border-top:1px solid rgba(255,255,255,.06)" data-confirm="Are you sure you want to logout?">🚪 <span class="nav-label">Logout</span></a>
  </aside>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="main-content">
    <div class="topbar">
      <button class="hamburger" onclick="toggleSidebar()">☰</button>
      <h2><?= e($pageTitle ?? 'Dashboard') ?></h2>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>/school/<?= e($_SESSION['inst_slug'] ?? '') ?>" target="_blank" class="btn btn-ghost btn-sm">🌐 View Portal</a>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;color:#fff;background:#111;padding:4px 14px;border-radius:20px;border:1px solid rgba(201,161,39,.35)"><?= e($_SESSION['inst_admin_name'] ?? 'Admin') ?></span>
      </div>
    </div>
    <?php if (!empty($_SESSION['sub_expired'])): ?>
    <div class="sub-expired-banner">
      ⚠️ Your <strong><?= e($_SESSION['sub_plan'] ?? '') ?></strong> subscription expired on <?= date('d M Y', strtotime($_SESSION['sub_end'] ?? '')) ?>.
      <a href="<?= BASE_URL ?>/institution/profile.php" class="btn btn-sm" style="background:#f59e0b;color:#000;border:none;padding:4px 12px">Renew Now</a>
    </div>
    <?php endif; ?>
    <?php
    // Show flash messages from any source
    $flashMsg = '';
    if (!empty($_SESSION['flash_error'])) {
        $flashMsg = $_SESSION['flash_error'];
        $flashType = 'flash-error';
    } elseif (!empty($_SESSION['flash_success'])) {
        $flashMsg = $_SESSION['flash_success'];
        $flashType = 'flash-success';
    } elseif (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $flashMsg = $f['message'];
        $flashType = $f['type'] === 'error' ? 'flash-error' : 'flash-success';
    }
    if ($flashMsg): ?>
    <div class="flash <?= $flashType ?>"><?= e($flashMsg) ?></div>
    <?php unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash']); ?>
    <?php endif; ?>
    <div class="page-content">
