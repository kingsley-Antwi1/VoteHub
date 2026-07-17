<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime'  => SESSION_LIFETIME,
    ]);
}

// Enforce session timeout on idle
$idleMax = SESSION_LIFETIME;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleMax) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

function isSuperAdmin(): bool {
    return isset($_SESSION['super_admin_id']);
}

function requireSuperAdmin(): void {
    if (!isSuperAdmin()) {
        header('Location: ' . BASE_URL . '/auth/admin-login.php');
        exit;
    }
}

function isInstAdmin(): bool {
    return isset($_SESSION['inst_admin_id'], $_SESSION['institution_id']);
}

function requireInstAdmin(): void {
    if (!isInstAdmin()) {
        header('Location: ' . BASE_URL . '/auth/inst-login.php');
        exit;
    }
}

function isVoter(): bool {
    return isset($_SESSION['voter_id'], $_SESSION['institution_id']);
}

function requireVoter(): void {
    if (!isVoter()) {
        header('Location: ' . BASE_URL . '/auth/voter-login.php');
        exit;
    }
}

function currentUserId(): int {
    return (int)($_SESSION['super_admin_id'] ?? $_SESSION['inst_admin_id'] ?? $_SESSION['voter_id'] ?? 0);
}

function currentUserType(): string {
    if (isSuperAdmin()) return 'super_admin';
    if (isInstAdmin()) return 'inst_admin';
    if (isVoter()) return 'voter';
    return 'guest';
}

function currentInstitutionId(): int {
    return (int)($_SESSION['institution_id'] ?? 0);
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}
