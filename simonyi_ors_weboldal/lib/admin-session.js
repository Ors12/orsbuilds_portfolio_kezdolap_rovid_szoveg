import { createHash, createHmac, randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';

const SESSION_COOKIE = 'orsbuilds_admin';
const CSRF_COOKIE = 'orsbuilds_csrf';
const IDLE_SECONDS = 1800;

const secretFor = (env) => env.ORSBUILDS_SESSION_SECRET || env.ORSBUILDS_ADMIN_PASSWORD_HASH || '';
const hmac = (secret, value) => createHmac('sha256', secret).update(value).digest('base64url');
const safeEqual = (a, b) => {
  const x = Buffer.from(String(a));
  const y = Buffer.from(String(b));
  return x.length === y.length && timingSafeEqual(x, y);
};

export function parseCookies(header = '') {
  const out = {};
  for (const part of header.split(';')) {
    const i = part.indexOf('=');
    if (i > 0) out[part.slice(0, i).trim()] = part.slice(i + 1).trim();
  }
  return out;
}

const cookie = (name, value, maxAge) =>
  `${name}=${value}; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=${maxAge}`;

/** Jelszóellenőrzés: ORSBUILDS_ADMIN_PASSWORD_HASH = scrypt$<só hex>$<hash hex> (lásd scripts/hash-password.mjs). */
export function verifyPassword(password, stored) {
  const [scheme, salt, hash] = String(stored || '').split('$');
  if (scheme !== 'scrypt' || !salt || !hash) return false;
  const expected = Buffer.from(hash, 'hex');
  const actual = scryptSync(password, Buffer.from(salt, 'hex'), expected.length);
  return actual.length === expected.length && timingSafeEqual(actual, expected);
}

export function hashPassword(password) {
  const salt = randomBytes(16);
  return `scrypt$${salt.toString('hex')}$${scryptSync(password, salt, 64).toString('hex')}`;
}

export function csrfState(request, env = process.env) {
  const cookies = parseCookies(request.headers.get('cookie') || '');
  let seed = cookies[CSRF_COOKIE];
  const isNew = !seed || !/^[A-Za-z0-9_-]{20,}$/.test(seed);
  if (isNew) seed = randomBytes(32).toString('base64url');
  const secret = secretFor(env) || 'unconfigured';
  return { seed, isNew, token: hmac(secret, `csrf:${seed}`), setCookie: cookie(CSRF_COOKIE, seed, 86400) };
}

export function csrfValid(request, token, env = process.env) {
  const cookies = parseCookies(request.headers.get('cookie') || '');
  const seed = cookies[CSRF_COOKIE];
  if (!seed || typeof token !== 'string') return false;
  return safeEqual(hmac(secretFor(env) || 'unconfigured', `csrf:${seed}`), token);
}

const fingerprint = (env) => createHash('sha256').update(env.ORSBUILDS_ADMIN_PASSWORD_HASH || '').digest('hex').slice(0, 24);

export function issueSession(env = process.env, now = Date.now()) {
  const payload = Buffer.from(JSON.stringify({ exp: now + IDLE_SECONDS * 1000, fp: fingerprint(env) })).toString('base64url');
  return cookie(SESSION_COOKIE, `${payload}.${hmac(secretFor(env), payload)}`, IDLE_SECONDS);
}

export const clearSession = () => cookie(SESSION_COOKIE, '', 0);

export function isAuthenticated(request, env = process.env, now = Date.now()) {
  if (!env.ORSBUILDS_ADMIN_PASSWORD_HASH) return false;
  const raw = parseCookies(request.headers.get('cookie') || '')[SESSION_COOKIE];
  if (!raw) return false;
  const [payload, signature] = raw.split('.');
  if (!payload || !signature || !safeEqual(hmac(secretFor(env), payload), signature)) return false;
  try {
    const data = JSON.parse(Buffer.from(payload, 'base64url').toString());
    return data.exp > now && data.fp === fingerprint(env);
  } catch {
    return false;
  }
}
