<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/security.php';
setSecurityHeaders();
header('Content-Type: text/plain; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Ez a végpont csak űrlapküldésre használható.');
}
if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/x-www-form-urlencoded'
    || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    http_response_code(415);
    exit('Nem támogatott vagy túl nagy űrlap.');
}
if (!isSameOriginSubmission()) {
    http_response_code(403);
    exit('Az űrlap csak erről a weboldalról küldhető el.');
}

$faxNumber = $_POST['fax_number'] ?? '';
if (!is_string($faxNumber) || trim($faxNumber) !== '') {
    http_response_code(400);
    exit('Az űrlap ellenőrzése sikertelen.');
}

$field = static function (string $key, int $max): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || strlen(trim($value)) > $max) {
        http_response_code(400);
        exit('Ellenőrizd a megadott adatokat.');
    }
    return trim($value);
};

$date = $field('date', 10);
$time = $field('time', 5);
$service = $field('service', 40);
$email = $field('email', 254);
$dateValue = DateTime::createFromFormat('!Y-m-d', $date);
$allowedTimes = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];
$allowedServices = ['Vízszerelés', 'Gázszerelés', 'Fűtéstechnika', 'Állapotfelmérés'];
if (!$dateValue || $dateValue->format('Y-m-d') !== $date || $date < date('Y-m-d')
    || !in_array($time, $allowedTimes, true)
    || !in_array($service, $allowedServices, true)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Válassz érvényes dátumot, időpontot és szolgáltatást, majd adj meg érvényes e-mail-címet.');
}
if (!allowRateLimitedSubmission('thermoflow-booking', 30)) {
    http_response_code(http_response_code() === 429 ? 429 : 503);
    exit(http_response_code() === 429 ? 'Kérlek, várj fél percet az újabb beküldés előtt.' : 'Az űrlapküldés átmenetileg nem érhető el.');
}

$admin = 'info@thermoflow.hu';
$message = "Dátum: $date $time\nSzolgáltatás: $service\nE-mail: $email\n";
$headers = [
    'Content-Type: text/plain; charset=UTF-8',
    'From: ThermoFlow weboldal <no-reply@thermoflow.hu>',
    'Reply-To: ' . $email,
];
if (!mail($admin, '=?UTF-8?B?' . base64_encode('Új időpontfoglalás – ThermoFlow') . '?=', $message, implode("\r\n", $headers))) {
    http_response_code(500);
    exit('A foglalási kérést a levelezőrendszer nem tudta átadni.');
}

echo 'Foglalás elküldve. Ez még nem jelent visszaigazolt időpontot.';
?>
