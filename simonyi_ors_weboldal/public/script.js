
const menu = document.querySelector('.menu');
const links = document.querySelector('.links');

if (menu && links) {
  menu.addEventListener('click', () => {
    const open = links.classList.toggle('open');
    menu.setAttribute('aria-expanded', String(open));
  });
}

document.querySelectorAll('.nav-dropdown-toggle').forEach((toggle) => {
  toggle.addEventListener('click', () => {
    const dropdown = toggle.closest('.nav-dropdown');
    const open = dropdown.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
});

const year = document.querySelector('#year');
if (year) year.textContent = new Date().getFullYear();

document.querySelectorAll('.price-button').forEach((button) => {
  button.addEventListener('click', () => {
    const packageField = document.querySelector('#booking-package');
    const bookingSection = document.querySelector('#idopontfoglalas');
    const dateField = document.querySelector('#booking-date');
    if (!packageField || !bookingSection || !dateField) return;

    packageField.value = button.dataset.package || '';
    packageField.dispatchEvent(new Event('change'));
    bookingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    packageField.focus({ preventScroll: true });
  });
});

const bookingDate = document.querySelector('#booking-date');
const bookingPackage = document.querySelector('#booking-package');
const bookingTime = document.querySelector('#booking-time');
const bookingAvailability = document.querySelector('#booking-availability');
const bookingForm = document.querySelector('.booking-form');
if (bookingDate && bookingPackage && bookingTime && bookingAvailability && bookingForm) {
  const today = new Date();
  today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
  const todayValue = today.toISOString().split('T')[0];
  const lastDate = new Date(today);
  lastDate.setDate(lastDate.getDate() + 90);
  bookingDate.min = todayValue;
  bookingDate.max = lastDate.toISOString().split('T')[0];
  let availabilityRequest = 0;
  let availabilityPending = false;

  async function loadBookingTimes() {
    const requestId = ++availabilityRequest;
    bookingTime.replaceChildren(new Option('Válassz szabad időpontot', ''));
    bookingTime.disabled = true;
    availabilityPending = false;

    if (!bookingDate.value || !bookingPackage.value) {
      bookingAvailability.textContent = 'Hétköznapokon 09:00 és 17:00 között, magyar idő szerint foglalható időpont.';
      return;
    }

    bookingAvailability.textContent = 'A szabad időpontok betöltése...';
    availabilityPending = true;
    try {
      const query = new URLSearchParams({ date: bookingDate.value, package: bookingPackage.value });
      const response = await fetch(`/api/availability?${query}`, {
        headers: { Accept: 'application/json' }
      });
      const result = await response.json();
      if (requestId !== availabilityRequest) return;
      if (!response.ok) throw new Error(result.error || 'Az időpontok nem tölthetők be.');
      if (!result || !Array.isArray(result.times)) {
        throw new Error('Az időpontfoglaló érvénytelen választ adott.');
      }

      bookingTime.disabled = false;
      if (result.times.length === 0) {
        bookingAvailability.textContent = 'Erre a napra nincs szabad időpont. Kérjük, válassz másik dátumot.';
        return;
      }

      result.times.forEach((time) => bookingTime.add(new Option(time, time)));
      bookingAvailability.textContent = `Elérhető időpontok (${result.duration} perces egyeztetés):`;
    } catch (error) {
      if (requestId !== availabilityRequest) return;
      bookingTime.replaceChildren(new Option('Nem sikerült betölteni az időpontokat', ''));
      bookingTime.disabled = true;
      bookingAvailability.textContent = error instanceof TypeError
        ? 'Az időpontfoglaló szerver nem érhető el. Próbáld újra később.'
        : error.message || 'Az időpontok jelenleg nem tölthetők be.';
    } finally {
      if (requestId === availabilityRequest) availabilityPending = false;
    }
  }

  bookingDate.addEventListener('change', loadBookingTimes);
  bookingPackage.addEventListener('change', loadBookingTimes);
  bookingForm.addEventListener('submit', (event) => {
    if (availabilityPending) {
      event.preventDefault();
      bookingAvailability.textContent = 'Várd meg, amíg betöltődnek a szabad időpontok.';
    }
  });
}

// Görgetéskor megjelenő szövegek (csökkentett mozgásnál kikapcsolva)
(function () {
  if (!('IntersectionObserver' in window)) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const sel = 'main h1, main h2, main h3, main p, main li, main dt, main dd, main label, main .btn, main .text-link, footer p, footer a, footer li';
  const items = [...document.querySelectorAll(sel)].filter((el) => !el.closest('.nav, .topbar, form button, .admin-panel'));
  if (!items.length) return;
  document.documentElement.classList.add('js-reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      e.target.classList.add('in');
      io.unobserve(e.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
  items.forEach((el) => {
    if (el.closest('.reveal') && el.closest('.reveal') !== el) return;
    const sib = el.parentElement ? [...el.parentElement.children].indexOf(el) : 0;
    el.style.setProperty('--d', Math.min(sib, 5) * 70 + 'ms');
    el.classList.add('reveal');
    io.observe(el);
  });
})();

// Szolgáltatások: lapozás nyilakkal, görgetéssel vagy billentyűvel
(function () {
  const track = document.querySelector('.services');
  const nav = document.querySelector('.carousel-nav');
  if (!track || !nav) return;
  const prev = nav.querySelector('[data-dir="-1"]');
  const next = nav.querySelector('[data-dir="1"]');
  const count = nav.querySelector('.carousel-count');
  const cards = [...track.querySelectorAll('.service')];
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const step = () => (cards[1] ? cards[1].offsetLeft - cards[0].offsetLeft : track.clientWidth);
  function update() {
    const max = track.scrollWidth - track.clientWidth - 2;
    prev.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft >= max;
    nav.hidden = max <= 0;
    const idx = Math.min(cards.length, Math.round(track.scrollLeft / step()) + 1);
    count.textContent = idx + ' / ' + cards.length;
  }
  [prev, next].forEach((b) => b.addEventListener('click', () => {
    track.scrollBy({ left: Number(b.dataset.dir) * step(), behavior: reduce ? 'auto' : 'smooth' });
  }));
  track.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
  track.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); next.click(); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); prev.click(); }
  });
  window.addEventListener('resize', update);
  update();
})();
