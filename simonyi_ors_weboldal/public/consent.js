// Süti- és adatkezelési hozzájárulás. A választás a böngésző helyi tárhelyében (localStorage) van, nem sütiben.
// Jelenleg az oldal csak a működéshez szükséges adatokat használ; nem szükséges (pl. statisztikai) szolgáltatás
// kizárólag akkor indulhat el, ha window.ORS_CONSENT.analytics === true.
(function () {
  const KEY = 'orsbuilds-consent-v2';
  const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } };
  const write = (v) => { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { /* nincs tárolás */ } };
  const saved = read();
  window.ORS_CONSENT = { necessary: true, analytics: Boolean(saved && saved.analytics) };

  let banner = null;
  function close() { if (banner) { banner.remove(); banner = null; } }
  function open() {
    if (banner) return;
    banner = document.createElement('div');
    banner.className = 'cookie-bar';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Süti és adatkezelés');
    banner.innerHTML = '<p>Az oldal csak a működéshez szükséges adatokat használja, követés nélkül. <a href="adatkezelesi-tajekoztato.html#sutik">Részletek</a></p>'
      + '<button type="button" class="btn cookie-accept">Elfogadom</button>';
    banner.querySelector('.cookie-accept').addEventListener('click', () => {
      write({ acknowledged: true, analytics: false, at: new Date().toISOString() });
      close();
    });
    document.body.appendChild(banner);
  }
  function init() {
    // Nem egyből jelenik meg: az oldal betöltése után néhány másodperccel
    if (!saved) setTimeout(open, 5000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
