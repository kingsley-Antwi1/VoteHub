<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Vote') ?> — <?= e($_SESSION['inst_name'] ?? '') ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=4">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" style="padding-top:20px">
    <div class="logo">
      <h1>🏛 <?= e($_SESSION['inst_name'] ?? 'Portal') ?></h1>
      <span>Voter</span>
    </div>
    <a href="<?= BASE_URL ?>/school/<?= e($_SESSION['inst_slug'] ?? '') ?>" class="nav-item" style="margin-top:20px">🌐 <span class="nav-label">Back to Portal</span></a>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item" style="margin-top:auto;color:#ef4444" data-confirm="Are you sure you want to logout?">🚪 <span class="nav-label">Logout</span></a>
  </aside>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="main-content">
    <div class="topbar">
      <button class="hamburger" onclick="toggleSidebar()">☰</button>
      <h2><?= e($pageTitle ?? 'Vote') ?></h2>
      <div class="topbar-actions">
        <span style="font-size:.8rem;color:#8899bb"><?= e($_SESSION['voter_name'] ?? 'Voter') ?></span>
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
