-- orsbuilds időpontfoglaló adatbázis-séma (SQLite)
-- Új adatbázishoz futtatható: sqlite3 bookings.sqlite < schema.sql
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(254) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    service VARCHAR(80) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    duration_minutes SMALLINT NOT NULL CHECK (duration_minutes > 0),
    details TEXT NOT NULL DEFAULT '',
    privacy_accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (booking_date, start_time)
);

CREATE INDEX IF NOT EXISTS booking_date_lookup ON bookings (booking_date);
