export const securityHeaders = {
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Cache-Control': 'no-store, max-age=0',
  'Content-Security-Policy': "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
};

export function json(data, status = 200, extraHeaders = {}) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { ...securityHeaders, 'Content-Type': 'application/json; charset=UTF-8', ...extraHeaders },
  });
}

export function redirect(location, extraHeaders = {}) {
  return new Response(null, { status: 303, headers: { ...securityHeaders, Location: location, ...extraHeaders } });
}

export const escapeHtml = (s) =>
  String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export function errorPage(message, status, returnUrl) {
  const html = `<!doctype html><html lang="hu"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nem sikerült elküldeni</title><body style="font-family:Arial,sans-serif;max-width:640px;margin:12vh auto;padding:24px;color:#173b32"><h1>Nem sikerült elküldeni az űrlapot</h1><p>${escapeHtml(message)}</p><p><a href="${escapeHtml(returnUrl)}">Vissza az űrlaphoz</a></p></body></html>`;
  return new Response(html, { status, headers: { ...securityHeaders, 'Content-Type': 'text/html; charset=UTF-8' } });
}

export function clientIp(request) {
  const forwarded = request.headers.get('x-forwarded-for') || '';
  return forwarded.split(',')[0].trim() || request.headers.get('x-real-ip') || '';
}

/** Same-origin ellenőrzés az Origin/Referer fejlécek alapján. */
export function isSameOrigin(request) {
  if ((request.headers.get('sec-fetch-site') || '').toLowerCase() === 'cross-site') return false;
  const source = request.headers.get('origin') || request.headers.get('referer') || '';
  if (!source) return true;
  const host = (request.headers.get('x-forwarded-host') || request.headers.get('host') || '').toLowerCase();
  try {
    const url = new URL(source);
    if (!['http:', 'https:'].includes(url.protocol)) return false;
    return url.host.toLowerCase() === host;
  } catch {
    return false;
  }
}
