<?php
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    // Legacy keys for headers that check them directly
    if ($type === 'success') {
        $_SESSION['flash_success'] = $message;
    } else {
        $_SESSION['flash_error'] = $message;
    }
}

function renderFlash(): string {
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash'], $_SESSION['flash_error'], $_SESSION['flash_success']);
    $cssClass = $f['type'] === 'error' ? 'flash-error' : 'flash-success';
    return "<div class=\"flash {$cssClass}\">{$f['message']}</div>";
}

function generateOTP(): string {
    return str_pad((string)random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9-]/', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}

function timeAgo(string $datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return date('d M Y', $ts);
}

function statusBadge(string $status): string {
    $map = [
        'pending'     => 'badge-warning',
        'active'      => 'badge-success',
        'suspended'   => 'badge-danger',
        'deactivated' => 'badge-secondary',
        'approved'    => 'badge-success',
        'declined'    => 'badge-danger',
        'expired'     => 'badge-secondary',
        'cancelled'   => 'badge-danger',
        'closed'      => 'badge-secondary',
    ];
    $class = $map[$status] ?? 'badge-secondary';
    return "<span class=\"badge $class\">$status</span>";
}

function logAudit(?int $instId, string $userType, int $userId, string $action, string $desc = ''): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO audit_logs (institution_id, user_type, user_id, action, description, ip_address) VALUES (?,?,?,?,?,?)")
           ->execute([$instId, $userType, $userId, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) {}
}

function hasActiveSubscription(int $instId): bool {
    try {
        $db = getDB();
        $sub = $db->prepare("SELECT id FROM subscriptions WHERE institution_id = ? AND status = 'active' AND end_date >= CURDATE() ORDER BY id DESC LIMIT 1");
        $sub->execute([$instId]);
        return (bool)$sub->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function countPendingPayments(): int {
    try {
        $db = getDB();
        return (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function validateUpload(array $file, array $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'], int $maxSize = MAX_FILE_SIZE): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return $file['error'] === UPLOAD_ERR_NO_FILE ? null : 'Upload error';
    if ($file['size'] > $maxSize) return 'File too large (max 2MB)';
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedTypes)) return 'Invalid file type. Allowed: JPG, PNG, GIF, WebP';
    return null;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            flash('error', 'Invalid request. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
        }
    }
}

function getPlanLimits(int $instId): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT s.end_date, p.max_elections, p.max_voters, p.max_candidates, p.name AS plan_name, p.price FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.institution_id = ? AND s.status = 'active' AND s.end_date >= CURDATE() ORDER BY s.id DESC LIMIT 1");
        $stmt->execute([$instId]);
        $data = $stmt->fetch();
        if (!$data) return null;
        $data['max_elections'] = (int)$data['max_elections'];
        $data['max_voters'] = (int)$data['max_voters'];
        $data['max_candidates'] = (int)$data['max_candidates'];
        return $data;
    } catch (Throwable $e) {
        return null;
    }
}

function upgradePrompt(string $feature, int $current, int $limit): string {
    $next = '';
    if ($limit >= 999) return '';
    $pct = round(($current / $limit) * 100);
    return '<div style="margin-top:8px;padding:10px 14px;background:rgba(201,161,39,.08);border:1px solid rgba(201,161,39,.25);border-radius:8px;font-size:.8rem">'
        . '<strong style="color:#c9a127">📈 Plan limit reached</strong><br>'
        . "Your current plan allows <strong>$limit $feature</strong> (you have <strong>$current</strong>). "
        . '<a href="' . BASE_URL . '/institution/profile.php" style="color:#c9a127;font-weight:600">Upgrade your plan</a> '
        . 'to add more.'
        . '</div>';
}

function checkPlanLimit(int $instId, string $type, int $count = 1): ?string {
    $limits = getPlanLimits($instId);
    if (!$limits) return 'No active subscription. Please renew your plan.';

    $current = match($type) {
        'elections' => (function() use ($instId) { $db = getDB(); $s = $db->prepare("SELECT COUNT(*) FROM elections WHERE institution_id = ? AND status != 'cancelled'"); $s->execute([$instId]); return (int)$s->fetchColumn(); })(),
        'voters' => (function() use ($instId) { $db = getDB(); $s = $db->prepare("SELECT COUNT(*) FROM voters WHERE institution_id = ?"); $s->execute([$instId]); return (int)$s->fetchColumn(); })(),
        'candidates' => (function() use ($instId) { $db = getDB(); $s = $db->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON p.id = c.position_id JOIN elections e ON e.id = p.election_id WHERE e.institution_id = ?"); $s->execute([$instId]); return (int)$s->fetchColumn(); })(),
    };

    $limit = match($type) {
        'elections' => $limits['max_elections'],
        'voters' => $limits['max_voters'],
        'candidates' => $limits['max_candidates'],
    };

    if ($limit >= 999) return null; // unlimited

    if (($current + $count) > $limit) {
        $feature = match($type) {
            'elections' => 'elections',
            'voters' => 'voters',
            'candidates' => 'candidates',
        };
        return upgradePrompt($feature, $current, $limit);
    }
    return null;
}

function checkRateLimit(string $key, int $maxAttempts = 5, int $windowMinutes = 15): bool {
    $sessionKey = "rate_limit_{$key}";
    $now = time();
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 1, 'first' => $now];
        return true;
    }
    $data = $_SESSION[$sessionKey];
    if ($now - $data['first'] > $windowMinutes * 60) {
        $_SESSION[$sessionKey] = ['count' => 1, 'first' => $now];
        return true;
    }
    $data['count']++;
    $_SESSION[$sessionKey] = $data;
    return $data['count'] <= $maxAttempts;
}
