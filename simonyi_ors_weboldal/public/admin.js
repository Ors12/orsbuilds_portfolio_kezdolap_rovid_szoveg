const loginForm = document.querySelector('#admin-login-form');
const passwordField = document.querySelector('#admin-password');
const loginButton = document.querySelector('#admin-login-button');
const logoutButton = document.querySelector('#admin-logout-button');
const sessionPanel = document.querySelector('#admin-session');
const message = document.querySelector('#admin-message');
const csrfField = document.querySelector('#admin-csrf-token');

let csrfToken = '';

function showMessage(text, isError = false) {
  message.textContent = text;
  message.style.color = isError ? '#ffaaa7' : '';
}

async function loadAdminStatus() {
  try {
    const response = await fetch('/api/admin-auth', {
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Az adminbelépés nem érhető el.');

    csrfToken = result.csrfToken;
    csrfField.value = csrfToken;
    loginForm.hidden = result.authenticated;
    sessionPanel.hidden = !result.authenticated;
    loginButton.disabled = result.authenticated || !result.configured;
    if (!result.configured) {
      showMessage('Az adminbelépés még nincs beállítva a tárhelyen.', true);
    }
  } catch (error) {
    showMessage(error.message || 'Az adminbelépés nem érhető el.', true);
    loginButton.disabled = true;
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  loginButton.disabled = true;
  showMessage('Belépés ellenőrzése...');

  try {
    const response = await fetch('/api/admin-auth', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({
        action: 'login',
        csrf_token: csrfToken,
        password: passwordField.value
      })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'A belépés nem sikerült.');

    passwordField.value = '';
    csrfToken = result.csrfToken;
    csrfField.value = csrfToken;
    loginForm.hidden = true;
    sessionPanel.hidden = false;
    showMessage(result.message);
  } catch (error) {
    showMessage(error.message || 'A belépés nem sikerült.', true);
  } finally {
    loginButton.disabled = false;
  }
});

logoutButton.addEventListener('click', async () => {
  logoutButton.disabled = true;
  try {
    const response = await fetch('/api/admin-auth', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({ action: 'logout', csrf_token: csrfToken })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'A kijelentkezés nem sikerült.');

    csrfToken = result.csrfToken;
    sessionPanel.hidden = true;
    loginForm.hidden = false;
    showMessage(result.message);
  } catch (error) {
    showMessage(error.message || 'A kijelentkezés nem sikerült.', true);
  } finally {
    logoutButton.disabled = false;
  }
});

loadAdminStatus();
