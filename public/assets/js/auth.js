/* ============================================================
   PETLANDIA - Autenticación (login y registro)
   ============================================================ */

const Auth = {
  async init() {
    if (document.getElementById('login-form')) {
      await this._bindLogin();
    }
    if (document.getElementById('register-form')) {
      await this._bindRegister();
    }
  },

  /* Asegura tener token CSRF antes de una mutación */
  async _ensureCsrf() {
    if (!APP.csrfToken) {
      await APP.fetchCsrf();
    }
  },

  async _bindLogin() {
    // Si ya hay sesión, redirigir según rol
    if (APP.user) {
      window.location.href = APP.homePath();
      return;
    }

    const form = document.getElementById('login-form');
    const btn = document.getElementById('login-btn');
    const errBox = document.getElementById('error-container');

    await this._ensureCsrf();

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errBox.innerHTML = '';
      btn.disabled = true;

      try {
        const data = await APP.request('auth.php?action=login', {
          method: 'POST',
          csrf: true,
          body: {
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
          },
        });

        APP.user = data.data.user;
        APP.csrfToken = data.data.csrf_token;
        APP.renderAuth();
        APP.refreshCartBadge();
        window.location.href = APP.homePath();
      } catch (err) {
        APP.showAlert(errBox, err.message);
        btn.disabled = false;
      }
    });
  },

  async _bindRegister() {
    if (APP.user) {
      window.location.href = APP.homePath();
      return;
    }

    const form = document.getElementById('register-form');
    const btn = document.getElementById('register-btn');
    const errBox = document.getElementById('error-container');

    await this._ensureCsrf();

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errBox.innerHTML = '';
      btn.disabled = true;

      try {
        const data = await APP.request('auth.php?action=register', {
          method: 'POST',
          csrf: true,
          body: {
            nombre: document.getElementById('nombre').value.trim(),
            apellido: document.getElementById('apellido').value.trim(),
            email: document.getElementById('email').value.trim(),
            telefono: document.getElementById('telefono').value.trim(),
            password: document.getElementById('password').value,
            password_confirmation: document.getElementById('password_confirmation').value,
          },
        });

        APP.csrfToken = data.data.csrf_token;
        APP.showAlert(errBox, data.message, 'success');
        setTimeout(() => (window.location.href = 'login.html'), 1200);
      } catch (err) {
        const first = err.errors ? Object.values(err.errors)[0] : err.message;
        APP.showAlert(errBox, first || err.message);
        btn.disabled = false;
      }
    });
  },
};

document.addEventListener('DOMContentLoaded', () => Auth.init());
