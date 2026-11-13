<?php
declare(strict_types=1);
require __DIR__ . '/security.php';
require __DIR__ . '/booking.php';
setSecurityHeaders();
header('Content-Type: application/json; charset=UTF-8');

function availabilityRespond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    availabilityRespond(['error' => 'Ez a végpont csak lekérdezésre használható.'], 405);
}

$date = $_GET['date'] ?? '';
$service = $_GET['package'] ?? '';
if (!is_string($date) || !is_string($service) || strlen($date) !== 10 || strlen($service) > 80) {
    availabilityRespond(['error' => 'Válassz érvényes dátumot és szolgáltatást.'], 400);
}

$config = require __DIR__ . '/config.php';
if (bookingDuration($service, $config) < 1 || !bookingDateIsValid($date, $config)) {
    availabilityRespond(['times' => [], 'duration' => 0]);
}

try {
    $pdo = bookingPdo($config);
    availabilityRespond([
        'times' => bookingAvailableTimes($pdo, $date, $service, $config),
        'duration' => bookingDuration($service, $config),
    ]);
} catch (Throwable $error) {
    error_log('orsbuilds availability error: ' . $error->getMessage());
    availabilityRespond(['error' => 'Az időpontok jelenleg nem tölthetők be. Ellenőrizd a tárhely PHP-adatbázis támogatását és a könyvtár írási jogosultságát.'], 503);
}
