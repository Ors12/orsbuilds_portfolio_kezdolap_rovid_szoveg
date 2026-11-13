<?php
declare(strict_types=1);
require __DIR__ . '/security.php';
require __DIR__ . '/booking.php';
setSecurityHeaders();
$config = require __DIR__ . '/config.php';

function fail(string $message, int $status = 400): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    $returnUrl = (($_POST['form_type'] ?? '') === 'booking') ? '../szolgaltatasok.html#idopontfoglalas' : '../kapcsolat.html';
    echo '<!doctype html><html lang="hu"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nem sikerült elküldeni</title><body style="font-family:Arial,sans-serif;max-width:640px;margin:12vh auto;padding:24px;color:#173b32"><h1>Nem sikerült elküldeni az űrlapot</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="' . $returnUrl . '">Vissza az űrlaphoz</a></p></body></html>';
    exit;
}
function field(string $key, int $max = 2000): string {
    $rawValue = $_POST[$key] ?? '';
    if (!is_string($rawValue)) fail('Ellenőrizd a megadott adatokat.');
    $value = trim($rawValue);
    if (strlen($value) > $max) fail('Az egyik megadott mező túl hosszú.');
    return $value;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail('Ez a végpont csak űrlapküldésre használható.', 405);
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if (!in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true)) fail('Nem támogatott űrlapformátum.', 415);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) fail('A beküldött űrlap túl nagy.', 413);
if (!isSameOriginSubmission()) fail('Az űrlap csak erről a weboldalról küldhető el.', 403);
// Egyszerű honeypot spamvédelem. A mezőnek üresnek kell maradnia.
if (field('fax_number', 100) !== '') fail('Az űrlap ellenőrzése sikertelen.');

$type = field('form_type', 20);
$name = field('name', 120);
$email = field('email', 254);
$phone = field('phone', 50);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Add meg a nevedet és egy érvényes e-mail-címet.');

$subject = '';
$body = '';
if ($type === 'contact') {
    $company = field('company', 160);
    $service = field('service', 160);
    $website = field('website', 300);
    $message = field('message', 5000);
    $privacyConsent = field('privacy_consent', 100);
    if ($service === '' || $message === '' || $privacyConsent !== 'Elfogadom az adatkezelési tájékoztatót') fail('Töltsd ki a kötelező mezőket, és fogadd el az adatkezelési tájékoztatót.');
    if (!in_array($service, ['Új weboldal készítése', 'Webshop készítése', 'Meglévő weboldal továbbfejlesztése', 'Hiba javítása vagy karbantartás', 'Egyedi fejlesztés', 'Egyéb kérdés / együttműködés'], true)) fail('Válassz érvényes témát.');
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) fail('A weboldal címe nem érvényes URL.');
    $subject = 'Új kapcsolatfelvétel – orsbuilds';
    $body = "Új kapcsolatfelvételi üzenet érkezett.\n\nNév: $name\nE-mail: $email\nTelefon: $phone\nCégnév: $company\nTéma: $service\nWeboldal: $website\n\nÜzenet:\n$message\n\nAdatkezelési tájékoztató elfogadva: igen\n";
} elseif ($type === 'booking') {
    $date = field('date', 10);
    $time = field('time', 5);
    $package = field('package', 80);
    $details = field('details', 3000);
    $privacyConsent = field('privacy_consent', 100);
    if (strlen($phone) < 6) fail('Adj meg egy érvényes telefonszámot.');
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) fail('Válassz egy érvényes szabad időpontot.');
    if ($privacyConsent !== 'Elfogadom az adatkezelési tájékoztatót') fail('Fogadd el az adatkezelési tájékoztatót a foglaláshoz.');
    if (bookingDuration($package, $config) < 1 || !bookingDateIsValid($date, $config)) {
        fail('Válassz érvényes, hétköznapra eső dátumot és szolgáltatást a következő 90 napból.');
    }
} else {
    fail('Ismeretlen űrlaptípus.');
}

if ($type === 'booking') {
    if (!allowRateLimitedSubmission('portfolio-booking', 30)) {
        if (http_response_code() === 429) fail('Kérlek, várj fél percet a következő foglalás előtt.', 429);
        fail('Az időpontfoglalás átmenetileg nem érhető el. Próbáld újra később.', 503);
    }

    try {
        $pdo = bookingPdo($config);
    } catch (PDOException $error) {
        error_log('orsbuilds booking database connection error: ' . $error->getMessage());
        fail('Az időpontfoglalás jelenleg nem érhető el. Kérjük, próbáld újra később.', 503);
    } catch (Throwable $error) {
        error_log('orsbuilds booking database initialization error: ' . $error->getMessage());
        fail('Az időpontfoglalás jelenleg nem érhető el. Ellenőrizd a tárhely PHP-adatbázis támogatását és a könyvtár írási jogosultságát.', 503);
    }

    $lockName = bookingLockName($date);
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $fileLock = null;
    if ($driver === 'mysql') {
        try {
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lock->execute([$lockName]);
        } catch (PDOException $error) {
            error_log('orsbuilds booking lock error: ' . $error->getMessage());
            fail('Az időpontfoglalás átmenetileg nem érhető el. Kérjük, próbáld újra.', 503);
        }
        if ((int)$lock->fetchColumn() !== 1) {
            fail('Az időpontfoglalás átmenetileg nem érhető el. Kérjük, próbáld újra.');
        }
    } else {
        $lockDirectory = bookingLockDirectory($config);
        if (!is_dir($lockDirectory) || !is_writable($lockDirectory)) {
            fail('A foglalási adatkönyvtár nem írható. Ellenőrizd a tárhely jogosultságait.', 503);
        }
        $fileLock = @fopen($lockDirectory . DIRECTORY_SEPARATOR . $lockName . '.lock', 'c');
        if ($fileLock === false) {
            fail('Az időpontfoglalás átmenetileg nem érhető el. Kérjük, próbáld újra.', 503);
        }
        $locked = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (flock($fileLock, LOCK_EX | LOCK_NB)) {
                $locked = true;
                break;
            }
            usleep(50000);
        }
        if (!$locked) {
            fclose($fileLock);
            fail('Az időpontfoglalás átmenetileg nem érhető el. Kérjük, próbáld újra.');
        }
    }

    $slotTaken = false;
    $bookingId = 0;
    $databaseError = null;
    try {
        $pdo->beginTransaction();
        $availableTimes = bookingAvailableTimes($pdo, $date, $package, $config);
        if (!in_array($time, $availableTimes, true)) {
            $slotTaken = true;
            $pdo->rollBack();
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO bookings
                    (customer_name, customer_email, customer_phone, service, booking_date, start_time, duration_minutes, details)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $name,
                $email,
                $phone,
                $package,
                $date,
                $time . ':00',
                bookingDuration($package, $config),
                $details,
            ]);
            $bookingId = (int)$pdo->lastInsertId();
            $pdo->commit();
        }
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $databaseError = $error;
    } finally {
        if ($driver === 'mysql') {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            } catch (PDOException $error) {
                error_log('orsbuilds booking lock release error: ' . $error->getMessage());
            }
        } elseif (is_resource($fileLock)) {
            flock($fileLock, LOCK_UN);
            fclose($fileLock);
        }
    }

    if ($databaseError instanceof PDOException) {
        error_log('orsbuilds booking save error: ' . $databaseError->getMessage());
        fail('A foglalást nem sikerült rögzíteni. Kérjük, próbáld újra később.', 503);
    }
    if ($slotTaken) {
        fail('Ezt az időpontot időközben lefoglalták. Válassz egy másik szabad időpontot.');
    }

    $timezone = new DateTimeZone($config['booking']['timezone']);
    $bookingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    $dateLabel = $bookingDate->format('Y. m. d.');
    $duration = bookingDuration($package, $config);
    $adminBody = "Új időpontfoglalás (#$bookingId)\n\nNév: $name\nE-mail: $email\nTelefon: $phone\nSzolgáltatás: $package\nIdőpont: $dateLabel, $time (magyar idő)\nIdőtartam: $duration perc\nAdatkezelési tájékoztató elfogadva: igen\n\nMegjegyzés:\n$details\n";
    $customerBody = "Kedves $name!\n\nSikeresen rögzítettük az időpontfoglalásodat.\n\nSzolgáltatás: $package\nIdőpont: $dateLabel, $time (magyar idő)\nIdőtartam: $duration perc\nFoglalási azonosító: $bookingId\n\nKérdés vagy módosítás esetén válaszolj erre az e-mailre.\n\nÜdvözlettel,\norsbuilds";
    $mailErrors = [];
    $adminMailer = null;
    $customerMailer = null;

    // E-mail-küldési hiba nem vonhatja vissza a már elmentett foglalást.
    try {
        $adminMailer = bookingMailer($config);
        $customerMailer = bookingMailer($config);
    } catch (Throwable $error) {
        error_log('orsbuilds booking mail configuration error: ' . $error->getMessage());
        $mailErrors[] = 'configuration';
    }

    if ($adminMailer !== null && $customerMailer !== null) {
        try {
            $adminMailer->addAddress($config['recipient']);
            $adminMailer->addReplyTo($email, $name);
            $adminMailer->Subject = 'Új időpontfoglalás – orsbuilds';
            $adminMailer->Body = $adminBody;
            $adminMailer->send();
        } catch (Throwable $error) {
            error_log('orsbuilds booking admin email error: ' . $error->getMessage());
            $mailErrors[] = 'admin';
        }

        try {
            $customerMailer->addAddress($email, $name);
            $customerMailer->addReplyTo($config['recipient'], $config['from_name']);
            $customerMailer->Subject = 'Időpontfoglalás visszaigazolása – orsbuilds';
            $customerMailer->Body = $customerBody;
            $customerMailer->send();
        } catch (Throwable $error) {
            error_log('orsbuilds booking confirmation email error: ' . $error->getMessage());
            $mailErrors[] = 'customer';
        }
    }

    // A sikeroldal jelzi az e-mail hibát; a látogatót nem kérjük újabb foglalásra.
    $successUrl = (string)$config['booking_success_url'];
    if ($mailErrors !== []) {
        $successUrl .= (str_contains($successUrl, '?') ? '&' : '?') . 'email=failed';
    }
    header('Location: ' . $successUrl, true, 303);
    exit;
}

$recipient = (string)$config['recipient'];
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fail('A címzett e-mail-címe hibásan van beállítva.', 500);
}
if (!allowRateLimitedSubmission('portfolio-form', 30)) {
    if (http_response_code() === 429) fail('Kérlek, várj fél percet a következő beküldés előtt.', 429);
    fail('Az űrlapküldés átmenetileg nem érhető el. Próbáld újra később.', 503);
}

// A kapcsolatfelvételi űrlap is ugyanazon a Gmail SMTP-kapcsolaton küld, mint a foglalások.
try {
    $mailer = bookingMailer($config);
    $mailer->addAddress($recipient);
    $mailer->addReplyTo($email, $name);
    $mailer->Subject = $subject;
    $mailer->Body = $body;
    $mailer->send();
} catch (Throwable $error) {
    error_log('orsbuilds contact email error: ' . $error->getMessage());
    fail('Az üzenetet most nem sikerült e-mailben elküldeni. Ellenőrizd az SMTP-beállításokat, majd próbáld újra.', 503);
}

header('Location: ' . $config['success_url'], true, 303);
exit;
