// Süti- és adatkezelési hozzájárulás. A választás a böngésző helyi tárhelyében (localStorage) van, nem sütiben.
// Jelenleg az oldal csak a működéshez szükséges adatokat használ; nem szükséges (pl. statisztikai) szolgáltatás
// kizárólag akkor indulhat el, ha window.ORS_CONSENT.analytics === true.
(function () {
  const KEY = 'orsbuilds-consent';
  const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } };
  const write = (v) => { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { /* nincs tárolás */ } };
  const saved = read();
  window.ORS_CONSENT = { necessary: true, analytics: Boolean(saved && saved.analytics) };

  let banner = null;
  function close() { if (banner) { banner.remove(); banner = null; } }
  function choose(analytics) {
    write({ analytics, at: new Date().toISOString() });
    window.ORS_CONSENT.analytics = analytics;
    document.dispatchEvent(new CustomEvent('orsbuilds:consent', { detail: window.ORS_CONSENT }));
    close();
  }
  function open() {
    if (banner) return;
    banner = document.createElement('div');
    banner.className = 'cookie-bar';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-labelledby', 'consent-title');
    banner.innerHTML = '<h2 id="consent-title">Sütik és adatkezelés</h2>'
      + '<p>Az oldal jelenleg csak a működéshez szükséges adatokat használja, követés nélkül. Ha később statisztikai szolgáltatást építek be, az csak a te engedélyeddel indul el.</p>'
      + '<p><a href="adatkezelesi-tajekoztato.html#sutik">Részletek a tájékoztatóban</a></p>'
      + '<div class="cookie-actions"><button type="button" class="btn cookie-accept">Elfogadom</button>'
      + '<button type="button" class="btn secondary cookie-necessary">Csak a szükségesek</button></div>';
    banner.querySelector('.cookie-accept').addEventListener('click', () => choose(true));
    banner.querySelector('.cookie-necessary').addEventListener('click', () => choose(false));
    document.body.appendChild(banner);
  }
  function init() {
    const links = document.querySelector('.legal-links');
    if (links) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'legal-link-btn';
      b.textContent = 'Sütibeállítások';
      b.addEventListener('click', open);
      links.appendChild(b);
    }
    if (!saved) open();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
