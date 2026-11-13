<?php
declare(strict_types=1);
require __DIR__ . '/../../api/security.php';
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

$field = static function (string $key, int $max): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || strlen(trim($value)) > $max) {
        http_response_code(400);
        exit('Ellenőrizd a megadott adatokat.');
    }
    return trim($value);
};

if ($field('fax_number', 100) !== '') {
    http_response_code(400);
    exit('Az űrlap ellenőrzése sikertelen.');
}

$lastname = $field('lastname', 100);
$firstname = $field('firstname', 100);
$email = $field('email', 254);
$phone = $field('phone', 50);
$message = $field('message', 3000);
$source = $field('source', 40);
if ($lastname === '' || $firstname === '' || $phone === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Töltsd ki az összes kötelező mezőt érvényes adatokkal.');
}
if (!allowRateLimitedSubmission('thermoflow-contact', 30)) {
    http_response_code(http_response_code() === 429 ? 429 : 503);
    exit(http_response_code() === 429 ? 'Kérlek, várj fél percet az újabb beküldés előtt.' : 'Az űrlapküldés átmenetileg nem érhető el.');
}

$to = 'info@thermoflow.hu';
$subject = 'Kapcsolatfelvétel – ThermoFlow';
$body = "Név: $lastname $firstname\nE-mail: $email\nTelefon: $phone\nHonnan talált ránk?: $source\n\nÜzenet:\n$message\n";
$headers = [
    'Content-Type: text/plain; charset=UTF-8',
    'From: ThermoFlow weboldal <no-reply@thermoflow.hu>',
    'Reply-To: ' . $email,
];
if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers))) {
    http_response_code(500);
    exit('Az üzenetet a levelezőrendszer nem tudta átadni.');
}

echo 'Elküldve';
?>
