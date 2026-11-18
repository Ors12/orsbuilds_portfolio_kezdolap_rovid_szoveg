<?php
declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_name('orsbuilds_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['admin_last_activity']) && $now - (int)$_SESSION['admin_last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['admin_last_activity'] = $now;
}

function isAdminAuthenticated(): bool
{
    startAdminSession();
    $passwordHash = getenv('ORSBUILDS_ADMIN_PASSWORD_HASH') ?: '';
    return $passwordHash !== ''
        && ($_SESSION['is_admin'] ?? false) === true
        && isset($_SESSION['admin_hash_fingerprint'])
        && is_string($_SESSION['admin_hash_fingerprint'])
        && hash_equals($_SESSION['admin_hash_fingerprint'], hash('sha256', $passwordHash));
}

function adminCsrfToken(): string
{
    startAdminSession();
    if (!isset($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function validateAdminCsrfToken(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['admin_csrf'])
        && is_string($_SESSION['admin_csrf'])
        && hash_equals($_SESSION['admin_csrf'], $token);
}
