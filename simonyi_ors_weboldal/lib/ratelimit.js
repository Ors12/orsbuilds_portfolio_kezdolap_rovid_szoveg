import { hashKey, readJson, writeJson } from './store.js';

/** Egyszerű, IP-nkénti időkorlát. Tárolási hiba esetén nem blokkol (a honeypot és a validáció marad). */
export async function allowSubmission(scope, ip, intervalSeconds, now = Date.now()) {
  if (!ip) return true;
  const path = `ratelimit/${scope}/${hashKey(ip)}.json`;
  try {
    const last = (await readJson(path))?.at ?? 0;
    if (last && now - last < intervalSeconds * 1000) return false;
    await writeJson(path, { at: now });
  } catch (error) {
    console.error('orsbuilds rate limit error:', error?.message);
  }
  return true;
}
