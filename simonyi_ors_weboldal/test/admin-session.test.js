import test from 'node:test';
import assert from 'node:assert/strict';
import {
  csrfState, csrfValid, hashPassword, isAuthenticated, issueSession, verifyPassword,
} from '../lib/admin-session.js';

const env = { ORSBUILDS_ADMIN_PASSWORD_HASH: hashPassword('helyes-jelszo-123'), ORSBUILDS_SESSION_SECRET: 'teszt-titok' };
const req = (cookie) => new Request('https://example.test/', { headers: cookie ? { cookie } : {} });
const cookieValue = (setCookie) => setCookie.split(';')[0];

test('jelszó ellenőrzés', () => {
  assert.equal(verifyPassword('helyes-jelszo-123', env.ORSBUILDS_ADMIN_PASSWORD_HASH), true);
  assert.equal(verifyPassword('rossz', env.ORSBUILDS_ADMIN_PASSWORD_HASH), false);
  assert.equal(verifyPassword('x', ''), false);
});

test('munkamenet: érvényes, lejárt és meghamisított süti', () => {
  const now = Date.now();
  const good = cookieValue(issueSession(env, now));
  assert.equal(isAuthenticated(req(good), env, now + 1000), true);
  assert.equal(isAuthenticated(req(good), env, now + 31 * 60 * 1000), false);
  assert.equal(isAuthenticated(req(good.slice(0, -2) + 'xx'), env, now), false);
  assert.equal(isAuthenticated(req(good), { ...env, ORSBUILDS_ADMIN_PASSWORD_HASH: hashPassword('masik-jelszo-1') }, now), false);
  assert.equal(isAuthenticated(req(), env, now), false);
});

test('CSRF: csak a sütihez tartozó token érvényes', () => {
  const state = csrfState(req(), env);
  const withCookie = req(cookieValue(state.setCookie));
  assert.equal(csrfValid(withCookie, state.token, env), true);
  assert.equal(csrfValid(withCookie, 'hamis', env), false);
  assert.equal(csrfValid(req(), state.token, env), false);
});
