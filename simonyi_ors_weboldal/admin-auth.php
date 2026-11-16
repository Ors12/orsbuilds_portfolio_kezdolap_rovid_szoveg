<?php
declare(strict_types=1);
require __DIR__ . '/api/security.php';
require __DIR__ . '/api/admin-security.php';
setSecurityHeaders();
header('Content-Type: application/json; charset=UTF-8');

function respondAdminAuth(array $response, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = require __DIR__ . '/api/config.php';
$passwordHash = getenv('ORSBUILDS_ADMIN_PASSWORD_HASH') ?: '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    startAdminSession();
    respondAdminAuth([
        'authenticated' => isAdminAuthenticated(),
        'configured' => $passwordHash !== '',
        'csrfToken' => adminCsrfToken(),
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: GET, POST');
    respondAdminAuth(['error' => 'Nem támogatott kérés.'], 405);
}
if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/x-www-form-urlencoded'
    || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    respondAdminAuth(['error' => 'Nem támogatott vagy túl nagy kérés.'], 415);
}
if (!isSameOriginSubmission()) {
    respondAdminAuth(['error' => 'Az adminisztráció csak erről a weboldalról érhető el.'], 403);
}

startAdminSession();
if (!validateAdminCsrfToken($_POST['csrf_token'] ?? null)) {
    respondAdminAuth(['error' => 'A munkamenet lejárt. Frissítsd az oldalt, és próbáld újra.'], 403);
}

$action = $_POST['action'] ?? '';
if (!is_string($action)) {
    respondAdminAuth(['error' => 'Érvénytelen kérés.'], 400);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    respondAdminAuth([
        'authenticated' => false,
        'configured' => $passwordHash !== '',
        'csrfToken' => adminCsrfToken(),
        'message' => 'Sikeresen kijelentkeztél.',
    ]);
}

if ($action !== 'login') {
    respondAdminAuth(['error' => 'Érvénytelen kérés.'], 400);
}
if (!allowRateLimitedSubmission('portfolio-admin-login', 5)) {
    $status = http_response_code() === 429 ? 429 : 503;
    respondAdminAuth([
        'error' => $status === 429
            ? 'Túl sok belépési kísérlet. Várj néhány másodpercet, majd próbáld újra.'
            : 'A belépés jelenleg nem érhető el. Próbáld újra később.',
    ], $status);
}
if ($passwordHash === '') {
    error_log('orsbuilds admin login attempted without ORSBUILDS_ADMIN_PASSWORD_HASH configured');
    respondAdminAuth(['error' => 'Az adminbelépés még nincs beállítva a tárhelyen.'], 503);
}

$password = $_POST['password'] ?? '';
if (!is_string($password) || strlen($password) > 1024 || !password_verify($password, $passwordHash)) {
    respondAdminAuth(['error' => 'Hibás jelszó.'], 401);
}

session_regenerate_id(true);
$_SESSION['is_admin'] = true;
$_SESSION['admin_hash_fingerprint'] = hash('sha256', $passwordHash);
$_SESSION['admin_last_activity'] = time();
$_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
respondAdminAuth([
    'authenticated' => true,
    'configured' => true,
    'csrfToken' => $_SESSION['admin_csrf'],
    'message' => 'Sikeres adminbelépés.',
]);
