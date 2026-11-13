
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

// Egységes, lépcsőzetes belépő animáció minden aloldalon.
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reduceMotion) {
  const groups = [
    '.hero',
    '.page-head',
    '.section-head',
    '.grid',
    '.content',
    '.contact-grid',
    '.visual',
    '.actions'
  ];

  groups.forEach((selector) => {
    document.querySelectorAll(selector).forEach((group) => {
      const children = Array.from(group.children).filter((element) =>
        !element.matches('script, style')
      );

      children.forEach((element, index) => {
        element.classList.add('stagger-item');
        element.style.setProperty('--stagger-delay', `${index * 110}ms`);
      });
    });
  });

  // Egységes spin animáció a szövegdobozokon minden oldalon.
  document.querySelectorAll('.page-head, .content, .card, .visual, .field').forEach((element, index) => {
    element.classList.add('carousel-item', 'stagger-item');
    if (!element.style.getPropertyValue('--stagger-delay')) {
      element.style.setProperty('--stagger-delay', `${index * 110}ms`);
    }
  });

  // A kártyák és űrlapmezők külön-külön, egymás után jelennek meg.
  document.querySelectorAll('.grid > *, .contact-grid > *, form .field').forEach((element, index) => {
    element.classList.add('stagger-item');
    element.style.setProperty('--stagger-delay', `${index * 100}ms`);
  });

  const observer = new IntersectionObserver((entries, currentObserver) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        currentObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -35px 0px' });

  document.querySelectorAll('.stagger-item').forEach((element) => observer.observe(element));
}


// Olvasási folyamatjelző és akadálymentes vissza a tetejére gomb.
const progress = document.createElement('div');
progress.className = 'reading-progress';
progress.setAttribute('aria-hidden', 'true');
document.body.appendChild(progress);
const topButton = document.createElement('button');
topButton.className = 'back-to-top';
topButton.type = 'button';
topButton.setAttribute('aria-label', 'Vissza az oldal tetejére');
topButton.innerHTML = '<span aria-hidden="true">↑</span>' ;
document.body.appendChild(topButton);
function updateScrollUI() {
  const max = document.documentElement.scrollHeight - window.innerHeight;
  const percent = max > 0 ? (window.scrollY / max) * 100 : 0;
  progress.style.width = `${percent}%`;
  topButton.classList.toggle('is-visible', window.scrollY > 420);
}
window.addEventListener('scroll', updateScrollUI, { passive: true });
window.addEventListener('resize', updateScrollUI);
topButton.addEventListener('click', () => window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }));
updateScrollUI();

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
      const response = await fetch(`api/availability.php?${query}`, {
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
        ? 'Az időpontfoglaló szerver nem érhető el. Ellenőrizd, hogy PHP-t támogató tárhelyről nyitottad-e meg az oldalt.'
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

// Szolgáltatások vízszintes lapozása nyilakkal és billentyűzettel.
const serviceTrack = document.querySelector('#service-track');
if (serviceTrack) {
  document.querySelectorAll('[data-service-scroll]').forEach((button) => {
    button.addEventListener('click', () => {
      const card = serviceTrack.querySelector('.service-card');
      const distance = card ? card.getBoundingClientRect().width + 16 : serviceTrack.clientWidth * 0.8;
      serviceTrack.scrollBy({ left: Number(button.dataset.serviceScroll) * distance, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
  });
}
