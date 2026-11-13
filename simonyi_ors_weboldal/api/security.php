<?php
declare(strict_types=1);

function setSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, max-age=0');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
}

/** Same-origin ellenőrzés, proxy mögötti HTTPS tárhelyekkel is kompatibilisen. */
function isSameOriginSubmission(): bool
{
    $fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($fetchSite === 'cross-site') {
        return false;
    }

    $source = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($source === '') {
        $source = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    }
    // Régebbi böngészőből vagy szerveroldali kliensből hiányozhat mindkét fejléc.
    if ($source === '') {
        return true;
    }

    $hostHeader = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $request = parse_url('//' . $hostHeader);
    $origin = parse_url($source);
    if (!is_array($request) || !is_array($origin) || empty($request['host']) || empty($origin['host'])) {
        return false;
    }
    $scheme = strtolower((string)($origin['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    $requestHost = strtolower(rtrim((string)$request['host'], '.'));
    $originHost = strtolower(rtrim((string)$origin['host'], '.'));
    if ($requestHost !== $originHost) {
        return false;
    }
    // Csak akkor vizsgálunk portot, ha a Host fejlécben explicit port szerepel.
    if (isset($request['port']) && isset($origin['port']) && (int)$request['port'] !== (int)$origin['port']) {
        return false;
    }
    return true;
}

function allowRateLimitedSubmission(string $scope, int $intervalSeconds): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') {
        http_response_code(503);
        return false;
    }

    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'orsbuilds_' . hash('sha256', $scope . "\0" . $ip) . '.lock';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        http_response_code(503);
        return false;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        http_response_code(503);
        return false;
    }
    // A chmod csak best-effort: egyes megosztott tárhelyek tiltják, ez ne bénítsa meg az űrlapot.
    @chmod($path, 0600);

    rewind($handle);
    $contents = stream_get_contents($handle);
    if ($contents === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        http_response_code(503);
        return false;
    }

    $now = time();
    $lastSubmission = (int)$contents;
    if ($lastSubmission > 0 && ($now - $lastSubmission) < $intervalSeconds) {
        flock($handle, LOCK_UN);
        fclose($handle);
        http_response_code(429);
        return false;
    }

    rewind($handle);
    $timestamp = (string)$now;
    $written = ftruncate($handle, 0) && fwrite($handle, $timestamp) === strlen($timestamp) && fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!$written) {
        http_response_code(503);
        return false;
    }
    return true;
}
