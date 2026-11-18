<?php
declare(strict_types=1);
require __DIR__ . '/api/security.php';
require __DIR__ . '/api/admin-security.php';
setSecurityHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'TRACE') {
    http_response_code(405);
    exit;
}

if (!isAdminAuthenticated()) {
    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=UTF-8');
    $applicationBase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    if ($applicationBase === '.') $applicationBase = '';
    $legalBase = htmlspecialchars($applicationBase . '/dokumentumok/', ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b1020"><title>Rövid karbantartás | orsbuilds</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;grid-template-rows:1fr auto;place-items:center;padding:24px;background:radial-gradient(ellipse at 50% 0%,#173b3d 0,transparent 58%),#0b1020;color:#e9eee9;font-family:Arial,sans-serif}.card{width:min(100%,680px);padding:clamp(30px,7vw,64px);border:1px solid rgba(115,226,213,.2);border-radius:26px;background:rgba(13,24,40,.88);box-shadow:0 28px 90px rgba(0,0,0,.35);text-align:center}.brand{margin:0 0 34px;color:#76e3d8;font-size:.8rem;font-weight:700;letter-spacing:.2em}.icon{width:72px;height:72px;display:grid;place-items:center;margin:0 auto 28px;border:1px solid rgba(115,226,213,.35);border-radius:22px;background:rgba(65,205,190,.1);color:#85eee1;font-size:2rem}.eyebrow{color:#7ee5d8;font-size:.78rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase}h1{margin:16px 0;font-family:Georgia,serif;font-size:clamp(2rem,7vw,3.55rem);font-weight:500;line-height:1.13}p{margin:0 auto;max-width:48ch;color:#bac9c8;font-size:1.05rem;line-height:1.8}.contact{display:inline-block;margin-top:28px;color:#81e8dc;text-underline-offset:4px}.dots{display:flex;justify-content:center;gap:7px;margin-top:30px}.dots span{width:7px;height:7px;border-radius:50%;background:#48d2c4;animation:pulse 1.2s ease-in-out infinite}.dots span:nth-child(2){animation-delay:.15s}.dots span:nth-child(3){animation-delay:.3s}.legal-links{width:min(1080px,calc(100% - 20px));margin:30px auto 0;display:flex;justify-content:center;flex-wrap:wrap;gap:clamp(18px,3vw,36px);text-align:center}.legal-links a{color:#9baead;font-size:.85rem;text-underline-offset:4px}.legal-links a:hover{color:#81e8dc}@keyframes pulse{50%{opacity:.3;transform:translateY(-3px)}}@media(prefers-reduced-motion:reduce){.dots span{animation:none}}
</style></head><body><main class="card"><div class="brand">ORSBUILDS</div><div class="icon" aria-hidden="true">⌁</div><div class="eyebrow">Hamarosan újra elérhető</div><h1>Épp szebbé és jobbá tesszük az oldalt.</h1><p>Jelenleg karbantartást végzünk. Köszönjük a türelmedet — kérjük, térj vissza egy kicsit később!</p><a class="contact" href="mailto:info.orsbuilds@gmail.com">Kérdésed van? Írj nekünk ↗</a><div class="dots" aria-hidden="true"><span></span><span></span><span></span></div></main><footer><nav class="legal-links" aria-label="Jogi dokumentumok"><a href="' . $legalBase . 'aszf.pdf">ÁSZF</a><a href="' . $legalBase . 'adatkezelesi_tajekoztato.pdf">Adatkezelési tájékoztató</a><a href="' . $legalBase . 'suti_tajekoztato.pdf">Sütikezelés</a></nav></footer></body></html>';
    exit;
}

$requestedPath = $_GET['path'] ?? '';
if (!is_string($requestedPath) || $requestedPath === '' || str_contains($requestedPath, "\0")) {
    http_response_code(400);
    exit('Érvénytelen oldal.');
}

$normalizedPath = str_replace('\\', '/', $requestedPath);
$segments = explode('/', $normalizedPath);
if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
    http_response_code(400);
    exit('Érvénytelen oldal.');
}

$target = realpath(__DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments));
$root = realpath(__DIR__);
if ($target === false || $root === false || !str_starts_with($target, $root . DIRECTORY_SEPARATOR) || !is_file($target)) {
    http_response_code(404);
    exit('Az oldal nem található.');
}

$relativePath = str_replace('\\', '/', substr($target, strlen($root) + 1));
if (preg_match('~(?:^|/)(?:\.|vendor/)|(?:^|/)(?:config|security|booking|admin-security)\.php$~i', $relativePath)) {
    http_response_code(404);
    exit('Az oldal nem található.');
}

$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
if ($extension === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://images.unsplash.com; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests");
    readfile($target);
    exit;
}
if ($extension === 'php' && preg_match('~^(?:api/(?:submit|availability)\.php|projects/thermoflow/(?:booking_mail|contact_mail)\.php)$~', $relativePath)) {
    require $target;
    exit;
}

http_response_code(404);
exit('Az oldal nem található.');
