<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Dashboard') ?> — VoteHub Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="logo">
      <h1>🏛 VoteHub</h1>
      <span>Super Admin Panel</span>
    </div>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
      📊 <span class="nav-label">Dashboard</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/institutions.php" class="nav-item <?= $currentPage === 'institutions.php' || $currentPage === 'institution-view.php' || $currentPage === 'institution-add.php' ? 'active' : '' ?>">
      🏫 <span class="nav-label">Institutions</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/plans.php" class="nav-item <?= $currentPage === 'plans.php' ? 'active' : '' ?>">
      💎 <span class="nav-label">Subscription Plans</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/payments.php" class="nav-item <?= $currentPage === 'payments.php' ? 'active' : '' ?>">
      💳 <span class="nav-label">Payments</span>
      <?php $pendingCount = countPendingPayments(); if ($pendingCount > 0): ?><span class="badge badge-warning" style="margin-left:auto;font-size:.65rem"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/logs.php" class="nav-item <?= $currentPage === 'logs.php' ? 'active' : '' ?>">
      🕵️ <span class="nav-label">Audit Logs</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
      ⚙️ <span class="nav-label">Payment Settings</span>
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item" style="margin-top:auto;color:#ef4444" data-confirm="Are you sure you want to logout?">
      🚪 <span class="nav-label">Logout</span>
    </a>
  </aside>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="main-content">
    <div class="topbar">
      <button class="hamburger" onclick="toggleSidebar()">☰</button>
      <h2><?= e($pageTitle ?? 'Dashboard') ?></h2>
      <div class="topbar-actions">
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;color:#fff;background:#111;padding:4px 14px;border-radius:20px;border:1px solid rgba(201,161,39,.35)"><?= e($_SESSION['super_admin_name'] ?? 'Admin') ?></span>
      </div>
    </div>
    <?php
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
