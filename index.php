<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$activeInsts = $db->query("SELECT COUNT(*) FROM institutions WHERE status='active'")->fetchColumn();
$totalElections = $db->query("SELECT COUNT(*) FROM elections WHERE status='active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VoteHub — Multi-Institution Voting Platform</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
  .hero {
    text-align:center;padding:80px 24px 60px;
    background:linear-gradient(135deg,#0f0f1a 0%,#1a1a2e 50%,#16213e 100%);
  }
  .hero h1 { font-size:2.5rem;color:#c9a127;margin-bottom:12px; }
  .hero p { color:#8899bb;font-size:1rem;max-width:600px;margin:0 auto 32px; }
  .features { display:flex;gap:24px;justify-content:center;flex-wrap:wrap;max-width:1000px;margin:0 auto;padding:40px 24px; }
  .feature-card { flex:1;min-width:200px;max-width:300px;text-align:center;padding:24px; }
  .feature-card .icon { font-size:2.5rem;margin-bottom:12px; }
  .feature-card h3 { color:#c9a127;margin-bottom:8px;font-size:1rem; }
  .feature-card p { color:#8899bb;font-size:.82rem; }
</style>
</head>
<body>
<div class="hero">
  <h1>🏛 VoteHub</h1>
  <p>A secure multi-institution online voting platform for Senior High Schools and Universities</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-ghost">Register Your Institution</a>
    <a href="<?= BASE_URL ?>/auth/inst-login.php" class="btn btn-ghost">Institution Login</a>
    <a href="<?= BASE_URL ?>/auth/admin-login.php" class="btn btn-ghost">Admin Login</a>
  </div>
  <div style="margin-top:24px;font-size:.85rem;color:#8899bb">
    <?= $activeInsts ?> active institutions · <?= $totalElections ?> live elections
  </div>
</div>

<div class="features">
  <div class="feature-card card"><div class="icon">🏫</div><h3>Multi-Institution</h3><p>Each school gets its own private portal. Data is completely separated.</p></div>
  <div class="feature-card card"><div class="icon">🔐</div><h3>Secure Authentication</h3><p>OTP verification ensures only eligible voters can cast their vote.</p></div>
  <div class="feature-card card"><div class="icon">📊</div><h3>Real-Time Results</h3><p>Automatic vote counting with instant results and visual reports.</p></div>
  <div class="feature-card card"><div class="icon">📱</div><h3>Works on Any Device</h3><p>Responsive design works on smartphones, tablets, and computers.</p></div>
</div>

<div style="text-align:center;padding:40px;color:#8899bb;font-size:.8rem">
  &copy; <?= date('Y') ?> VoteHub. All rights reserved.
  <br><br>
  <a href="https://wa.me/233245599539" target="_blank" style="color:#25D366;text-decoration:none;font-size:.9rem">
    <svg style="width:18px;height:18px;vertical-align:middle;margin-right:4px" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    WhatsApp: 0245599539
  </a>
  &nbsp;·&nbsp;
  <span style="font-size:.9rem">
    <svg style="width:18px;height:18px;vertical-align:middle;margin-right:4px" viewBox="0 0 24 24" fill="#8899bb"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
    Contact: 0245599539
  </span>
</div>
</body>
</html>
