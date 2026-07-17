<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireInstAdmin();
$db = getDB();
$instId = currentInstitutionId();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['nav' => [], 'results' => []]); exit; }

$s = "%$q%";
$results = [];
$navItems = [];

// Filter nav pages
$pages = [
    ['label' => 'Dashboard', 'url' => '/institution/dashboard.php', 'icon' => '📊', 'keywords' => 'dashboard home overview stats'],
    ['label' => 'Voters', 'url' => '/institution/voters.php', 'icon' => '👤', 'keywords' => 'voters students list add import csv'],
    ['label' => 'Elections', 'url' => '/institution/elections.php', 'icon' => '🗳', 'keywords' => 'elections positions candidates create'],
    ['label' => 'Candidates', 'url' => '/institution/candidates.php', 'icon' => '🏆', 'keywords' => 'candidates nominees contestants'],
    ['label' => 'Results', 'url' => '/institution/results.php', 'icon' => '📈', 'keywords' => 'results winners votes turnout'],
    ['label' => 'Payments', 'url' => '/institution/payment.php', 'icon' => '💳', 'keywords' => 'payments subscription plans billing'],
    ['label' => 'Settings', 'url' => '/institution/profile.php', 'icon' => '⚙️', 'keywords' => 'settings profile logo branding'],
];
$ql = strtolower($q);
foreach ($pages as $p) {
    if (strpos(strtolower($p['label']), $ql) !== false || strpos($p['keywords'], $ql) !== false) {
        $navItems[] = $p;
    }
}

// Search voters
$voters = $db->prepare("SELECT id, student_id, full_name, email, phone, level FROM voters WHERE institution_id = ? AND (full_name LIKE ? OR student_id LIKE ? OR email LIKE ? OR phone LIKE ? OR level LIKE ?) LIMIT 5");
$voters->execute([$instId, $s, $s, $s, $s, $s]);
while ($r = $voters->fetch()) {
    $results[] = [
        'section' => 'Voters',
        'label' => "{$r['full_name']} — {$r['student_id']}" . ($r['level'] ? " ({$r['level']})" : ''),
        'url' => BASE_URL . '/institution/voters.php?search=' . urlencode($r['student_id']),
        'icon' => '👤',
    ];
}

// Search elections
$elecs = $db->prepare("SELECT id, title, status, description FROM elections WHERE institution_id = ? AND (title LIKE ? OR description LIKE ?) LIMIT 5");
$elecs->execute([$instId, $s, $s]);
while ($r = $elecs->fetch()) {
    $results[] = [
        'section' => 'Elections',
        'label' => "{$r['title']} ({$r['status']})",
        'url' => BASE_URL . '/institution/elections.php',
        'icon' => '🗳',
    ];
}

// Search positions
$positions = $db->prepare("SELECT p.title AS pos_title, e.title AS elec_title FROM positions p JOIN elections e ON e.id = p.election_id WHERE e.institution_id = ? AND p.title LIKE ? LIMIT 5");
$positions->execute([$instId, $s]);
while ($r = $positions->fetch()) {
    $results[] = [
        'section' => 'Positions',
        'label' => "{$r['pos_title']} — {$r['elec_title']}",
        'url' => BASE_URL . '/institution/elections.php',
        'icon' => '📋',
    ];
}

// Search candidates
$cands = $db->prepare("SELECT c.id, c.full_name, c.manifesto, p.title AS pos_title, e.title AS elec_title FROM candidates c JOIN positions p ON p.id = c.position_id JOIN elections e ON e.id = p.election_id WHERE e.institution_id = ? AND (c.full_name LIKE ? OR c.manifesto LIKE ?) LIMIT 5");
$cands->execute([$instId, $s, $s]);
while ($r = $cands->fetch()) {
    $results[] = [
        'section' => 'Candidates',
        'label' => "{$r['full_name']} — {$r['pos_title']} ({$r['elec_title']})",
        'url' => BASE_URL . '/institution/candidates.php',
        'icon' => '🏆',
    ];
}

// Search payments
$pays = $db->prepare("SELECT p.id, p.amount, p.reference, p.status, pl.name AS plan_name FROM payments p JOIN subscription_plans pl ON pl.id = p.subscription_id WHERE p.institution_id = ? AND (p.reference LIKE ? OR p.status LIKE ? OR pl.name LIKE ?) LIMIT 5");
$pays->execute([$instId, $s, $s, $s]);
while ($r = $pays->fetch()) {
    $results[] = [
        'section' => 'Payments',
        'label' => "₵{$r['amount']} — {$r['plan_name']} ({$r['status']})" . ($r['reference'] ? " Ref: {$r['reference']}" : ''),
        'url' => BASE_URL . '/institution/payment.php',
        'icon' => '💳',
    ];
}

// Search subscription plans info
$plans = $db->prepare("SELECT p.name, p.price FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? AND (p.name LIKE ?) LIMIT 3");
$plans->execute([$instId, $s]);
while ($r = $plans->fetch()) {
    $results[] = [
        'section' => 'Subscription',
        'label' => "{$r['name']} Plan (₵{$r['price']})",
        'url' => BASE_URL . '/institution/payment.php',
        'icon' => '💎',
    ];
}

echo json_encode(['nav' => $navItems, 'results' => $results]);
