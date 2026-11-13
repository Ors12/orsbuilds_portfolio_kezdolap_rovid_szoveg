<?php
// orsbuilds szerveroldali beállítások.
// A titkos adatokat éles tárhelyen lehetőleg környezeti változóban add meg.
$privateDirectory = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'orsbuilds_private';
$defaultSqlitePath = $privateDirectory . DIRECTORY_SEPARATOR . 'bookings.sqlite';

// Helyi SMTP-titkok külön fájlban. Ezt a fájlt ne töltsd fel nyilvános helyre.
$localSmtp = [];
$localSmtpFile = __DIR__ . DIRECTORY_SEPARATOR . 'smtp.local.php';
if (is_file($localSmtpFile)) {
    $loadedSmtp = require $localSmtpFile;
    if (is_array($loadedSmtp)) {
        $localSmtp = $loadedSmtp;
    }
}

return [
    'recipient' => 'info.orsbuilds@gmail.com',
    'from_email' => getenv('ORSBUILDS_SMTP_FROM') ?: 'no-reply@orsbuilds.hu',
    'from_name' => 'orsbuilds weboldal',
    'success_url' => '../koszonjuk.html',
    'booking_success_url' => '../foglalas-sikeres.html',
    'database' => [
        // MySQL nélkül SQLite-ot használ. Az adatbázis a webes gyökérkönyvtáron kívül jön létre.
        'dsn' => getenv('ORSBUILDS_DB_DSN') ?: 'sqlite:' . $defaultSqlitePath,
        'username' => getenv('ORSBUILDS_DB_USER') ?: '',
        'password' => getenv('ORSBUILDS_DB_PASSWORD') ?: '',
    ],
    'smtp' => array_merge([
        'host' => getenv('ORSBUILDS_SMTP_HOST') ?: '',
        'username' => getenv('ORSBUILDS_SMTP_USERNAME') ?: '',
        'password' => getenv('ORSBUILDS_SMTP_PASSWORD') ?: '',
        'from' => getenv('ORSBUILDS_SMTP_FROM') ?: (getenv('ORSBUILDS_SMTP_USERNAME') ?: ''),
        'port' => (int)(getenv('ORSBUILDS_SMTP_PORT') ?: 587),
        'encryption' => strtolower(getenv('ORSBUILDS_SMTP_ENCRYPTION') ?: 'tls'),
    ], $localSmtp),
    'booking' => [
        'timezone' => 'Europe/Budapest',
        'open_time' => '09:00',
        'close_time' => '17:00',
        'step_minutes' => 30,
        'max_days_ahead' => 90,
        'services' => [
            'Bemutatkozó weboldal' => 30,
            'Többoldalas céges weboldal' => 45,
            'Webshop' => 60,
            'Egyedi fejlesztés' => 45,
            'Karbantartás és szerkesztés' => 30,
            'Egyéb' => 30,
        ],
    ],
];
