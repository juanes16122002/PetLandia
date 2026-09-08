/* ============================================================
   PETLANDIA - Núcleo de la aplicación
   Maneja llamadas a la API, sesión, CSRF y utilidades comunes.
   ============================================================ */

const APP = {
  API: '../api',

  /* Token CSRF global (se obtiene de auth.php?action=csrf) */
  csrfToken: null,

  /* Usuario actualmente autenticado (o null) */
  user: null,

  /**
   * Realiza una llamada a la API.
   * @param {string} endpoint  p.ej. 'products.php'
   * @param {object} [opts]    options: method, query, body, auth, csrf
   * @returns {Promise<object>} respuesta JSON { success, message, data }
   */
  async request(endpoint, opts = {}) {
    const {
      method = 'GET',
      query = {},
      body = null,
      auth = false,
      csrf = false,
    } = opts;

    const params = new URLSearchParams();
    Object.entries(query).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') params.set(k, v);
    });

    const qs = params.toString();
    const sep = qs ? (endpoint.includes('?') ? '&' : '?') : '';
    const url = `${APP.API}/${endpoint}${sep}${qs}`;

    const headers = {};

    if (csrf && APP.csrfToken) {
      headers['X-CSRF-Token'] = APP.csrfToken;
    }

    let fetchBody;
    if (body !== null) {
      headers['Content-Type'] = 'application/json';
      fetchBody = JSON.stringify(body);
    }

    const res = await fetch(url, {
      method,
      headers,
      body: fetchBody,
      credentials: 'same-origin',
    });

    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('Respuesta inválida del servidor.');
    }

    if (!res.ok) {
      const err = new Error(data.message || 'Ocurrió un error.');
      err.status = res.status;
      err.errors = data.errors;
      throw err;
    }

    return data;
  },

  /* ---------- Formato ---------- */
  formatPrice(value) {
    const n = Number(value);
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP',
      maximumFractionDigits: 0,
    }).format(n);
  },

  formatDate(value) {
    if (!value) return '';
    const d = new Date(typeof value === 'string' ? value.replace(' ', 'T') : value);
    if (isNaN(d)) return value;
    return d.toLocaleString('es-CL', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  },

  estadoBadge(estado) {
    const map = {
      PENDIENTE: ['badge-pending', 'Pendiente'],
      PAGADO: ['badge-paid', 'Pagado'],
      RECHAZADO: ['badge-rejected', 'Rechazado'],
      APROBADO: ['badge-paid', 'Aprobado'],
    };
    const [cls, label] = map[estado] || ['badge-pending', estado];
    return `<span class="badge ${cls}">${label}</span>`;
  },

  /* ---------- Sesión ---------- */
  /* Promesa de carga de sesión compartida (para que varias páginas la esperen) */
  sessionReady: null,

  loadSession() {
    if (!APP.sessionReady) {
      APP.sessionReady = this._doLoadSession();
    }
    return APP.sessionReady;
  },

  async _doLoadSession() {
    try {
      const data = await APP.request('auth.php?action=check');
      APP.user = data.data.user;
      APP.csrfToken = data.data.csrf_token;
    } catch (e) {
      APP.user = null;
    }
    APP.renderAuth();
    return APP.user;
  },

  /**
   * Fuerza a esperar la carga de sesión y, si no hay sesión,
   * redirige a login. Devuelve el usuario o null.
   */
  async requireLogin() {
    const user = await this.loadSession();
    if (!user) {
      window.location.href = 'login.html';
      return null;
    }
    return user;
  },

  async fetchCsrf() {
    try {
      const data = await APP.request('auth.php?action=csrf');
      APP.csrfToken = data.data.csrf_token;
    } catch (e) {
      /* sin conexión o error: se omite */
    }
  },

  async logout() {
    try {
      await APP.request('auth.php?action=logout', { method: 'POST', csrf: true });
    } catch (e) {
      /* ignorar */
    }
    APP.user = null;
    APP.csrfToken = null;
    APP.renderAuth();
    window.location.href = 'login.html';
  },

  /**
   * Página de destino tras iniciar sesión según el rol:
   * administradores -> dashboard admin, resto -> catálogo.
   */
  homePath() {
    if (APP.user && APP.user.rol === 'ADMINISTRADOR') {
      return 'admin/dashboard.html';
    }
    return 'catalog.html';
  },

  /* ---------- Carrito (badge del header) ---------- */
  async refreshCartBadge() {
    const badge = document.getElementById('cart-badge');
    if (!badge || !APP.user) return;
    try {
      const data = await APP.request('cart.php?action=count');
      const c = data.data.total_items || 0;
      if (c > 0) {
        badge.textContent = c;
        badge.classList.add('visible');
      } else {
        badge.classList.remove('visible');
      }
    } catch (e) {
      badge.classList.remove('visible');
    }
  },

  /* ---------- Header común ---------- */
  renderAuth() {
    const userArea = document.getElementById('user-area');
    if (!userArea) return;

    if (APP.user) {
      userArea.innerHTML = `
        <div class="user-menu">
          <span class="user-name">Hola, ${escapeHtml(APP.user.nombre)}</span>
          <a href="orders.html" class="btn btn-ghost">Mis pedidos</a>
          ${APP.user.rol === 'ADMINISTRADOR'
            ? '<a href="admin/dashboard.html" class="btn btn-ghost">Panel admin</a>'
            : ''}
          <button class="btn btn-outline" id="logout-btn">Salir</button>
        </div>
      `;
      const logoutBtn = document.getElementById('logout-btn');
      if (logoutBtn) logoutBtn.addEventListener('click', () => APP.logout());
    } else {
      userArea.innerHTML = `
        <div class="user-menu">
          <a href="login.html" class="btn btn-ghost">Ingresar</a>
          <a href="register.html" class="btn btn-primary">Registrarse</a>
        </div>
      `;
    }
  },

  /* ---------- Utilidades DOM ---------- */
  showAlert(container, message, type = 'error') {
    if (!container) return;
    container.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
  },
};

/* Escapado HTML para evitar XSS al insertar datos de la API */
function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* Arranque común: sesión + badge de carrito (si la página lo incluye) */
document.addEventListener('DOMContentLoaded', () => {
  APP.loadSession().then(() => APP.refreshCartBadge());
});
