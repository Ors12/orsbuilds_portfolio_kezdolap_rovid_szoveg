import { json, clientIp, isSameOrigin } from '../lib/http.js';
import {
  clearSession, csrfState, csrfValid, isAuthenticated, issueSession, verifyPassword,
} from '../lib/admin-session.js';
import { allowSubmission } from '../lib/ratelimit.js';

const configured = () => Boolean(process.env.ORSBUILDS_ADMIN_PASSWORD_HASH);

export async function GET(request) {
  const csrf = csrfState(request);
  const authenticated = isAuthenticated(request);
  const headers = new Headers();
  headers.append('Set-Cookie', csrf.setCookie);
  if (authenticated) headers.append('Set-Cookie', issueSession()); // csúszó lejárat
  return withCookies(json({ authenticated, configured: configured(), csrfToken: csrf.token }), headers);
}

export async function POST(request) {
  const type = (request.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
  if (type !== 'application/x-www-form-urlencoded' || Number(request.headers.get('content-length') || 0) > 4096) {
    return json({ error: 'Nem támogatott vagy túl nagy kérés.' }, 415);
  }
  if (!isSameOrigin(request)) return json({ error: 'Az adminisztráció csak erről a weboldalról érhető el.' }, 403);

  const form = new URLSearchParams(await request.text());
  if (!csrfValid(request, form.get('csrf_token'))) {
    return json({ error: 'A munkamenet lejárt. Frissítsd az oldalt, és próbáld újra.' }, 403);
  }

  const action = form.get('action');
  const csrf = csrfState(request);

  if (action === 'logout') {
    const headers = new Headers();
    headers.append('Set-Cookie', clearSession());
    return withCookies(json({
      authenticated: false, configured: configured(), csrfToken: csrf.token, message: 'Sikeresen kijelentkeztél.',
    }), headers);
  }
  if (action !== 'login') return json({ error: 'Érvénytelen kérés.' }, 400);

  if (!(await allowSubmission('portfolio-admin-login', clientIp(request), 5))) {
    return json({ error: 'Túl sok belépési kísérlet. Várj néhány másodpercet, majd próbáld újra.' }, 429);
  }
  if (!configured()) {
    console.error('orsbuilds admin login attempted without ORSBUILDS_ADMIN_PASSWORD_HASH configured');
    return json({ error: 'Az adminbelépés még nincs beállítva.' }, 503);
  }
  const password = form.get('password') || '';
  if (password.length > 1024 || !verifyPassword(password, process.env.ORSBUILDS_ADMIN_PASSWORD_HASH)) {
    return json({ error: 'Hibás jelszó.' }, 401);
  }

  const headers = new Headers();
  headers.append('Set-Cookie', issueSession());
  return withCookies(json({
    authenticated: true, configured: true, csrfToken: csrf.token, message: 'Sikeres adminbelépés.',
  }), headers);
}

function withCookies(response, cookieHeaders) {
  for (const value of cookieHeaders.getSetCookie()) response.headers.append('Set-Cookie', value);
  return response;
}
