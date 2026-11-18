import { config } from './config.js';

const { booking } = config;

const pad = (n) => String(n).padStart(2, '0');
export const toHHMM = (minutes) => `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
const toMinutes = (hhmm) => {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + m;
};

/** A magyar idő szerinti mai dátum (YYYY-MM-DD) és a nap eltelt percei. */
export function budapestNow(now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: booking.timezone,
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(now);
  const p = Object.fromEntries(parts.map((x) => [x.type, x.value]));
  return { date: `${p.year}-${p.month}-${p.day}`, minutes: Number(p.hour) * 60 + Number(p.minute) };
}

const dayNumber = (dateStr) => {
  const [y, m, d] = dateStr.split('-').map(Number);
  return Math.floor(Date.UTC(y, m - 1, d) / 86400000);
};

export function bookingDuration(service) {
  return typeof service === 'string' && Object.hasOwn(booking.services, service) ? booking.services[service] : 0;
}

export function bookingDateIsValid(dateStr, now = new Date()) {
  if (typeof dateStr !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return false;
  const [y, m, d] = dateStr.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d));
  if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== m - 1 || dt.getUTCDate() !== d) return false;
  const weekday = dt.getUTCDay();
  if (weekday === 0 || weekday === 6) return false;
  const today = dayNumber(budapestNow(now).date);
  const target = dayNumber(dateStr);
  return target >= today && target <= today + Math.max(1, booking.maxDaysAhead);
}

/** Az egyeztetés által elfoglalt rácspontok (pl. 45 perc 09:00-tól → 09:00, 09:30). */
export function cellsFor(time, duration) {
  const start = toMinutes(time);
  const count = Math.ceil(duration / booking.stepMinutes);
  return Array.from({ length: count }, (_, i) => toHHMM(start + i * booking.stepMinutes));
}

/** Szabad kezdési időpontok. A taken egy Set a már foglalt rácspontokról ('HH:MM'). */
export function availableTimes(dateStr, service, taken = new Set(), now = new Date()) {
  const duration = bookingDuration(service);
  if (duration < 1 || !bookingDateIsValid(dateStr, now)) return [];
  const opens = toMinutes(booking.openTime);
  const closes = toMinutes(booking.closeTime);
  const step = Math.max(5, booking.stepMinutes);
  const current = budapestNow(now);
  const result = [];
  for (let start = opens; start + duration <= closes; start += step) {
    if (dateStr === current.date && start <= current.minutes) continue;
    if (cellsFor(toHHMM(start), duration).some((cell) => taken.has(cell))) continue;
    result.push(toHHMM(start));
  }
  return result;
}
