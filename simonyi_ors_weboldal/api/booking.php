<?php
declare(strict_types=1);

/** A foglalások tartós adatbázis-kapcsolata. SQLite az alapértelmezett. */
function bookingPdo(array $config): PDO
{
    $database = $config['database'] ?? [];
    $dsn = (string)($database['dsn'] ?? '');
    $username = (string)($database['username'] ?? '');
    $password = (string)($database['password'] ?? '');

    if ($dsn === '') {
        throw new RuntimeException('A foglalási adatbázis nincs beállítva.');
    }

    $driver = strtolower((string)strtok($dsn, ':'));
    if (!in_array($driver, ['sqlite', 'mysql'], true)) {
        throw new RuntimeException('Nem támogatott adatbázistípus.');
    }
    if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('A tárhelyen nem érhető el a szükséges PDO adatbázis-meghajtó: ' . $driver . '.');
    }
    if ($driver === 'mysql' && $username === '') {
        throw new RuntimeException('A MySQL adatbázis-felhasználónév hiányzik.');
    }

    if ($driver === 'sqlite') {
        $databasePath = substr($dsn, 7);
        if ($databasePath === '' || $databasePath === ':memory:') {
            throw new RuntimeException('Az SQLite adatbázis elérési útja érvénytelen.');
        }
        $databaseDirectory = dirname($databasePath);
        if (!is_dir($databaseDirectory)) {
            if (!@mkdir($databaseDirectory, 0700, true) && !is_dir($databaseDirectory)) {
                throw new RuntimeException('Az SQLite adatbázis könyvtára nem hozható létre. Ellenőrizd a tárhely írási jogosultságait.');
            }
        }
        @chmod($databaseDirectory, 0700);
    }

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 8);

    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA busy_timeout = 8000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
    }

    $idColumn = $driver === 'mysql'
        ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $indexes = $driver === 'mysql'
        ? ', UNIQUE KEY booking_start (booking_date, start_time), KEY booking_date_lookup (booking_date)'
        : ', UNIQUE (booking_date, start_time)';
    $tableOptions = $driver === 'mysql'
        ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        : '';

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bookings (
            id ' . $idColumn . ',
            customer_name VARCHAR(120) NOT NULL,
            customer_email VARCHAR(254) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            service VARCHAR(80) NOT NULL,
            booking_date DATE NOT NULL,
            start_time TIME NOT NULL,
            duration_minutes SMALLINT NOT NULL,
            details TEXT NOT NULL,
            privacy_accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' . $indexes . '
        )' . $tableOptions
    );

    if ($driver === 'sqlite') {
        $databasePath = substr($dsn, 7);
        if (is_file($databasePath)) {
            @chmod($databasePath, 0600);
        }
    }

    return $pdo;
}

function bookingDuration(string $service, array $config): int
{
    return (int)($config['booking']['services'][$service] ?? 0);
}

function bookingDateIsValid(string $date, array $config): bool
{
    try {
        $timezone = new DateTimeZone((string)$config['booking']['timezone']);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$day || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $day->format('Y-m-d') !== $date) {
            return false;
        }
        $today = new DateTimeImmutable('today', $timezone);
        $lastBookableDay = $today->modify('+' . max(1, (int)$config['booking']['max_days_ahead']) . ' days');
        return (int)$day->format('N') <= 5 && $day >= $today && $day <= $lastBookableDay;
    } catch (Throwable $error) {
        error_log('orsbuilds booking date configuration error: ' . $error->getMessage());
        return false;
    }
}

function bookingAvailableTimes(PDO $pdo, string $date, string $service, array $config): array
{
    $duration = bookingDuration($service, $config);
    if ($duration < 1 || !bookingDateIsValid($date, $config)) {
        return [];
    }

    $query = $pdo->prepare('SELECT start_time, duration_minutes FROM bookings WHERE booking_date = ?');
    $query->execute([$date]);
    $booked = $query->fetchAll();

    [$openHour, $openMinute] = array_map('intval', explode(':', (string)$config['booking']['open_time']));
    [$closeHour, $closeMinute] = array_map('intval', explode(':', (string)$config['booking']['close_time']));
    $opensAt = $openHour * 60 + $openMinute;
    $closesAt = $closeHour * 60 + $closeMinute;
    $step = max(5, (int)$config['booking']['step_minutes']);
    $timezone = new DateTimeZone((string)$config['booking']['timezone']);
    $today = new DateTimeImmutable('today', $timezone);
    $now = new DateTimeImmutable('now', $timezone);
    $currentMinute = (int)$now->format('H') * 60 + (int)$now->format('i');
    $available = [];

    for ($start = $opensAt; $start + $duration <= $closesAt; $start += $step) {
        if ($date === $today->format('Y-m-d') && $start <= $currentMinute) {
            continue;
        }
        $overlap = false;
        foreach ($booked as $booking) {
            [$hour, $minute] = array_map('intval', explode(':', substr((string)$booking['start_time'], 0, 5)));
            $bookedStart = $hour * 60 + $minute;
            $bookedEnd = $bookedStart + (int)$booking['duration_minutes'];
            if ($start < $bookedEnd && ($start + $duration) > $bookedStart) {
                $overlap = true;
                break;
            }
        }
        if (!$overlap) {
            $available[] = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
        }
    }
    return $available;
}

function bookingLockName(string $date): string
{
    return 'orsbuilds_' . substr(hash('sha256', $date), 0, 48);
}

/** A foglalási lock fájl is a nem publikus adatkönyvtárba kerül. */
function bookingLockDirectory(array $config): string
{
    $dsn = (string)($config['database']['dsn'] ?? '');
    if (strpos($dsn, 'sqlite:') === 0) {
        return dirname(substr($dsn, 7));
    }
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
}

function bookingMailer(array $config): \PHPMailer\PHPMailer\PHPMailer
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('A PHPMailer nincs telepítve (vendor/autoload.php hiányzik).');
    }
    require_once $autoload;

    $smtp = $config['smtp'] ?? [];
    foreach (['host', 'username', 'password', 'from'] as $required) {
        if (trim((string)($smtp[$required] ?? '')) === '') {
            throw new RuntimeException('Az SMTP beállítás hiányos: ' . $required . '.');
        }
    }
    $port = (int)($smtp['port'] ?? 0);
    if ($port < 1 || $port > 65535 || !filter_var((string)$smtp['from'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Az SMTP port vagy feladó e-mail-cím érvénytelen.');
    }
    $encryption = strtolower((string)($smtp['encryption'] ?? 'tls'));
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        throw new RuntimeException('Az SMTP titkosítás csak tls vagy ssl lehet.');
    }

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = (string)$smtp['host'];
    $mailer->SMTPAuth = true;
    $mailer->Username = (string)$smtp['username'];
    $mailer->Password = (string)$smtp['password'];
    $mailer->Port = $port;
    $mailer->Timeout = 15;
    $mailer->SMTPSecure = $encryption === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom((string)$smtp['from'], (string)$config['from_name']);
    return $mailer;
}
