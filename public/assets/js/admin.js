/* ============================================================
   PETLANDIA - Panel administrativo
   ============================================================ */

/* Las vistas admin viven en /views/admin/ (un nivel más abajo que /views/) */
APP.API = '../../api';

const Admin = {
  UPLOAD: '../../uploads/products',

  /**
   * Inicializa el panel: carga sesión, valida rol y ejecuta la página.
   * @param {object} cfg  { allow: string[], onReady: function|null }
   */
  async init(cfg = {}) {
    this.allow = cfg.allow || ['ADMINISTRADOR'];
    this.onReady = cfg.onReady || null;

    const errorBox = document.getElementById('admin-error');

    await APP.loadSession();
    this.renderUser();
    this.bindLogout();

    if (!APP.user) {
      this.deny('Debes iniciar sesión para acceder al panel.', '../login.html');
      return;
    }

    if (!this.allow.includes(APP.user.rol)) {
      this.deny('No tienes permiso para acceder a esta sección.', '../home.html');
      return;
    }

    if (errorBox) errorBox.innerHTML = '';
    if (this.onReady) this.onReady();
  },

  deny(message, url) {
    const errorBox = document.getElementById('admin-error');
    if (errorBox) {
      errorBox.innerHTML = `
        <div class="alert alert-warning">
          <strong>Acceso restringido:</strong> ${escapeHtml(message)}.
          <a href="${escapeHtml(url)}" class="btn btn-ghost" style="margin-left:8px;">Volver</a>
        </div>`;
    }
    const main = document.querySelector('.admin-main');
    if (main) main.classList.add('denied');
  },

  renderUser() {
    if (!APP.user) return;
    const name = document.getElementById('admin-user-name');
    const role = document.getElementById('admin-user-role');
    if (name) name.textContent = `${APP.user.nombre} ${APP.user.apellido}`.trim();
    if (role) role.textContent = APP.user.rol === 'INVENTARIO' ? 'Rol: Inventario' : 'Rol: Administrador';
  },

  bindLogout() {
    const btn = document.getElementById('admin-logout');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      try {
        await APP.request('auth.php?action=logout', { method: 'POST', csrf: true });
      } catch (e) { /* ignorar */ }
      window.location.href = '../login.html';
    });
  },

  /* Utilidades compartidas */
  img(p) {
    if (!p || !p.imagen) return '<span class="avatar" style="text-align:center;line-height:42px;">🐾</span>';
    return `<img class="avatar" src="${this.UPLOAD}/${escapeHtml(p.imagen)}" alt="${escapeHtml(p.nombre)}">`;
  },

  stockBadge(stock) {
    const s = Number(stock) || 0;
    if (s <= 0) return '<span class="badge badge-stock-out">Agotado</span>';
    if (s <= 5) return `<span class="badge badge-stock-low">Bajo: ${s}</span>`;
    return `<span class="badge badge-stock-ok">En stock</span>`;
  },

  boolBadge(value, opt = {}) {
    const label = value == 1 || value === true ? (opt.on || 'Sí') : (opt.off || 'No');
    const cls = value == 1 || value === true ? 'badge-paid' : 'badge-pending';
    return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
  },

  loader() {
    return '<div class="loader"><div class="spinner"></div></div>';
  },

  empty(colspan, message = 'Sin registros.') {
    return `<tr><td colspan="${colspan}" class="text-center text-muted" style="padding:28px;">${escapeHtml(message)}</td></tr>`;
  },

  renderPagination(container, pg, onChange) {
    if (!container || !pg || pg.last_page <= 1) {
      if (container) container.innerHTML = '';
      return;
    }

    const cur = pg.current_page;
    const last = pg.last_page;
    const pages = [];
    for (let i = Math.max(1, cur - 2); i <= Math.min(last, cur + 2); i++) pages.push(i);

    const btn = (num, label, cls = '', disabled = false) => `
      <button class="btn btn-ghost ${cls}" data-page="${num}" ${disabled ? 'disabled' : ''}>${label}</button>`;

    const html = [
      btn(cur - 1, '&laquo;', '', cur <= 1),
      ...pages.map((n) => btn(n, n, n === cur ? 'current' : '')),
      btn(cur + 1, '&raquo;', '', cur >= last),
    ].join('');

    container.innerHTML = `<nav class="pagination">${html}</nav>`;

    container.querySelectorAll('button').forEach((b) => {
      b.addEventListener('click', () => onChange(Number(b.dataset.page)));
    });
  },

  async flash(container, message, type = 'success') {
    if (!container) return;
    container.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
    clearTimeout(this._flashTimer);
    this._flashTimer = setTimeout(() => { container.innerHTML = ''; }, 3000);
  },
};

/* ============================================================
   DASHBOARD
   ============================================================ */
const Dashboard = {
  init() {
    Admin.init({ onReady: () => this.load() });
  },

  async load() {
    const mount = document.getElementById('stat-grid');
    try {
      const res = await APP.request('admin.php?action=dashboard');
      const d = res.data;
      this.renderStats(mount, d.summary);
      this.renderRecentOrders(d.recent_orders);
      this.renderLowStock(d.low_stock_products);
      this.renderCategories(d.categories);
    } catch (e) {
      APP.showAlert(document.getElementById('admin-error'), e.message);
    }
  },

  renderStats(mount, s) {
    const o = s.orders;
    const p = s.products;
    const card = (label, value, icon, tone = '') => `
      <div class="stat-card tone-${tone}">
        <div class="stat-icon">${icon}</div>
        <div><div class="stat-label">${escapeHtml(label)}</div>
        <div class="stat-value">${escapeHtml(value)}</div></div>
      </div>`;

    mount.innerHTML = [
      card('Pedidos totales', o.total, '&#128203;', ''),
      card('Pendientes', o.pending, '&#9203;', 'warning'),
      card('Pagados', o.paid, '&#10004;', 'success'),
      card('Rechazados', o.rejected, '&#10060;', 'danger'),
      card('Productos activos', p.total_active, '&#128062;'),
      card('Stock bajo', p.low_stock, '&#9888;&#65039;', 'warning'),
      card('Agotados', p.out_of_stock, '&#128683;', 'danger'),
      card('Clientes', s.clients.total, '&#128101;'),
    ].join('');
  },

  renderRecentOrders(orders) {
    const mount = document.getElementById('recent-orders');
    if (!orders || orders.length === 0) {
      mount.innerHTML = `<div class="empty-state"><div class="icon">&#128203;</div>Sin pedidos todavía.</div>`;
      return;
    }

    const rows = orders.map((o) => `
      <tr>
        <td><strong>#${o.id}</strong></td>
        <td>${escapeHtml(`${o.cliente_nombre} ${o.cliente_apellido}`.trim())}</td>
        <td>${APP.formatDate(o.created_at)}</td>
        <td class="price">${APP.formatPrice(o.total)}</td>
        <td>${APP.estadoBadge(o.estado)}</td>
      </tr>`).join('');

    mount.innerHTML = `
      <table class="table">
        <thead><tr><th>#</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  },

  renderLowStock(products) {
    const mount = document.getElementById('low-stock');
    if (!products || products.length === 0) {
      mount.innerHTML = `<div class="empty-state"><div class="icon">&#9989;</div>Todo el stock está bien.</div>`;
      return;
    }

    const rows = products.map((p) => `
      <tr>
        <td>${Admin.img(p)}</td>
        <td>${escapeHtml(p.nombre)}</td>
        <td>${escapeHtml(p.categoria || '')}</td>
        <td class="num"><strong>${p.stock}</strong></td>
        <td>${Admin.stockBadge(p.stock)}</td>
      </tr>`).join('');

    mount.innerHTML = `
      <table class="table">
        <thead><tr><th>Producto</th><th>Categoría</th><th class="num">Stock</th><th>Estado</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  },

  renderCategories(categories) {
    const mount = document.getElementById('categories');
    if (!categories || categories.length === 0) {
      mount.innerHTML = `<div class="empty-state"><div class="icon">&#128722;</div>Sin categorías.</div>`;
      return;
    }

    const items = categories.map((c) => `
      <div style="display:flex;justify-content:space-between;padding:10px 2px;border-bottom:1px solid var(--color-border);">
        <strong>${escapeHtml(c.nombre)}</strong>
        <span class="text-muted">${c.total_productos ?? 0} productos</span>
      </div>`).join('');

    mount.innerHTML = items;
  },
};

/* ============================================================
   PRODUCTOS (lista + paginación)
   ============================================================ */
const AdminProducts = {
  state: { page: 1, nombre: '', categoria_id: '' },

  init() {
    Admin.init({ onReady: () => {
      this.loadCategories();
      this.bindEvents();
      this.load();
    }});
  },

  bindEvents() {
    const apply = document.getElementById('filter-apply');
    if (apply) apply.addEventListener('click', () => {
      this.state.page = 1;
      this.state.nombre = document.getElementById('filter-search').value.trim();
      this.state.categoria_id = document.getElementById('filter-category').value;
      this.load();
    });
    const search = document.getElementById('filter-search');
    if (search) search.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') apply && apply.click();
    });
  },

  async loadCategories() {
    try {
      const res = await APP.request('products.php?action=categories');
      const select = document.getElementById('filter-category');
      const cats = res.data.categories || [];
      select.innerHTML = [
        '<option value="">Todas las categorías</option>',
        ...cats.map((c) => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`),
      ].join('');
    } catch (e) { /* ignorar */ }
  },

  async load() {
    const mount = document.getElementById('products-table');
    mount.innerHTML = Admin.loader();
    try {
      const res = await APP.request('admin.php?action=products', {
        query: {
          page: this.state.page,
          nombre: this.state.nombre,
          categoria_id: this.state.categoria_id,
          solo_activos: '',
        },
      });
      this.render(res.data.products, res.data.pagination);
    } catch (e) {
      mount.innerHTML = '';
      APP.showAlert(document.getElementById('admin-error'), e.message);
    }
  },

  render(products, pg) {
    const mount = document.getElementById('products-table');
    if (!products || products.length === 0) {
      mount.innerHTML = `<div class="empty-state"><div class="icon">&#128062;</div>No se encontraron productos.</div>`;
      document.getElementById('pagination').innerHTML = '';
      return;
    }

    const rows = products.map((p) => `
      <tr data-id="${p.id}">
        <td>${Admin.img(p)}</td>
        <td><strong>${escapeHtml(p.nombre)}</strong><br><span class="text-muted" style="font-size:.78rem;">SKU: ${escapeHtml(p.sku)}</span></td>
        <td>${escapeHtml(p.categoria || '')}</td>
        <td class="price">${APP.formatPrice(p.precio)}</td>
        <td class="num"><strong>${p.stock}</strong></td>
        <td>${Admin.boolBadge(p.disponible, { on: 'Sí', off: 'No' })}</td>
        <td>${Admin.boolBadge(p.activo, { on: 'Activo', off: 'Inactivo' })}</td>
        <td>
          <div class="table-actions">
            <a class="btn btn-outline" href="product-form.html?id=${p.id}">Editar</a>
            <button class="btn btn-ghost btn-toggle" type="button">${p.activo == 1 ? 'Desactivar' : 'Activar'}</button>
          </div>
        </td>
      </tr>`).join('');

    mount.innerHTML = `
      <div class="card table-card">
        <table class="table">
          <thead><tr>
            <th>Imagen</th><th>Producto</th><th>Categoría</th><th>Precio</th>
            <th class="num">Stock</th><th>Disponible</th><th>Estado</th><th>Acciones</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;

    mount.querySelectorAll('.btn-toggle').forEach((btn, i) => {
      btn.addEventListener('click', () => this.toggleActive(products[i]));
    });

    Admin.renderPagination(document.getElementById('pagination'), pg, (page) => {
      this.state.page = page;
      this.load();
    });
  },

  async toggleActive(p) {
    const activating = p.activo != 1;
    if (!confirm(`¿${activating ? 'Activar' : 'Desactivar'} el producto "${p.nombre}"?`)) return;

    try {
      if (activating) {
        await APP.request('products.php?action=update', {
          method: 'POST', csrf: true, body: { id: p.id, activo: 1 },
        });
      } else {
        await APP.request('products.php?action=deactivate', {
          method: 'POST', csrf: true, body: { id: p.id },
        });
      }
      this.load();
    } catch (e) {
      APP.showAlert(document.getElementById('admin-error'), e.message);
    }
  },
};

/* ============================================================
   FORMULARIO DE PRODUCTO (crear / editar)
   ============================================================ */
const ProductForm = {
  id: null,
  categories: [],

  init() {
    Admin.init({ onReady: () => this.load() });
  },

  async load() {
    const params = new URLSearchParams(window.location.search);
    this.id = params.get('id') ? Number(params.get('id')) : null;

    if (this.id) {
      document.getElementById('form-title').textContent = 'Editar producto';
      document.getElementById('form-subtitle').textContent = 'Modifica los datos del producto y pulsa Guardar.';
      document.getElementById('save-btn').textContent = 'Guardar cambios';
    }

    try {
      const res = await APP.request('products.php?action=categories');
      this.categories = res.data.categories || [];
    } catch (e) { /* ignorar */ }

    this.renderCategories();

    if (this.id) {
      await this.fillProduct(this.id);
    }

    document.getElementById('product-form').addEventListener('submit', (e) => this.submit(e));
  },

  renderCategories() {
    const select = document.getElementById('categoria_id');
    select.innerHTML = [
      '<option value="">Selecciona una categoría</option>',
      ...this.categories.map((c) => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`),
    ].join('');
  },

  async fillProduct(id) {
    const btn = document.getElementById('save-btn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Cargando...';
    try {
      const res = await APP.request('products.php?action=admin-show', { query: { id } });
      const p = res.data.product;

      document.getElementById('product-id').value = p.id;
      document.getElementById('nombre').value = p.nombre || '';
      document.getElementById('categoria_id').value = p.categoria_id || '';
      document.getElementById('precio').value = p.precio ?? '';
      document.getElementById('sku').value = p.sku || '';
      document.getElementById('stock').value = p.stock ?? 0;
      document.getElementById('descripcion').value = p.descripcion || '';
      document.getElementById('imagen').value = p.imagen || '';
      document.getElementById('disponible').checked = p.disponible == 1;
      document.getElementById('activo').checked = p.activo == 1;
    } catch (e) {
      const box = document.getElementById('form-error');
      box.hidden = false;
      box.innerHTML = escapeHtml(e.message);
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  },

  async submit(e) {
    e.preventDefault();

    const btn = document.getElementById('save-btn');
    const errorBox = document.getElementById('form-error');
    errorBox.hidden = true;

    const body = {
      nombre: document.getElementById('nombre').value.trim(),
      categoria_id: Number(document.getElementById('categoria_id').value) || null,
      precio: Number(document.getElementById('precio').value) || 0,
      sku: document.getElementById('sku').value.trim(),
      stock: Number(document.getElementById('stock').value) || 0,
      descripcion: document.getElementById('descripcion').value.trim(),
      imagen: document.getElementById('imagen').value.trim(),
      disponible: document.getElementById('disponible').checked ? 1 : 0,
      activo: document.getElementById('activo').checked ? 1 : 0,
    };

    if (this.id) body.id = this.id;

    btn.disabled = true;
    btn.textContent = this.id ? 'Guardando...' : 'Creando...';
    try {
      await APP.request(
        `products.php?action=${this.id ? 'update' : 'create'}`,
        { method: 'POST', csrf: true, body }
      );
      window.location.href = 'products.html';
    } catch (err) {
      errorBox.hidden = false;
      errorBox.innerHTML = escapeHtml(err.message);
      if (err.errors) {
        Object.entries(err.errors).forEach(([field, msg]) => {
          const el = document.getElementById(`err-${field}`);
          if (el) { el.hidden = false; el.textContent = msg; }
        });
      }
      this.scrollToError();
    } finally {
      btn.disabled = false;
      btn.textContent = this.id ? 'Guardar cambios' : 'Guardar producto';
    }
  },

  scrollToError() {
    const err = document.getElementById('form-error');
    if (err && !err.hidden && err.getBoundingClientRect().top < 0) {
      window.scrollTo({ top: err.offsetTop - 80, behavior: 'smooth' });
    }
  },
};

/* ============================================================
   INVENTARIO (stock)
   ============================================================ */
const Inventory = {
  state: { page: 1, nombre: '' },

  init() {
    Admin.init({ allow: ['ADMINISTRADOR', 'INVENTARIO'], onReady: () => {
      this.bindEvents();
      this.load();
    }});
  },

  bindEvents() {
    const btn = document.getElementById('inv-filter');
    btn && btn.addEventListener('click', () => {
      this.state.page = 1;
      this.state.nombre = document.getElementById('inv-search').value.trim();
      this.load();
    });
    const search = document.getElementById('inv-search');
    search && search.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') btn && btn.click();
    });
    const reload = document.getElementById('inv-reload');
    reload && reload.addEventListener('click', () => this.load());
  },

  async load() {
    const mount = document.getElementById('inv-table');
    mount.innerHTML = Admin.loader();
    try {
      const res = await APP.request('admin.php?action=products', {
        query: {
          page: this.state.page,
          nombre: this.state.nombre,
          solo_activos: '',
        },
      });
      this.render(res.data.products, res.data.pagination);
      const info = document.getElementById('inv-info');
      if (info) info.innerHTML = `<p class="text-muted" style="margin-bottom:10px;">${res.data.pagination.total} productos en total.</p>`;
    } catch (e) {
      mount.innerHTML = '';
      APP.showAlert(document.getElementById('admin-error'), e.message);
    }
  },

  render(products, pg) {
    const mount = document.getElementById('inv-table');
    if (!products || products.length === 0) {
      mount.innerHTML = `<div class="empty-state"><div class="icon">&#128230;</div>No se encontraron productos.</div>`;
      document.getElementById('inv-pagination').innerHTML = '';
      return;
    }

    const rows = products.map((p) => `
      <tr data-id="${p.id}">
        <td>${Admin.img(p)}</td>
        <td><strong>${escapeHtml(p.nombre)}</strong><br><span class="text-muted" style="font-size:.78rem;">SKU: ${escapeHtml(p.sku)}</span></td>
        <td>${escapeHtml(p.categoria || '')}</td>
        <td class="price">${APP.formatPrice(p.precio)}</td>
        <td>
          <input type="number" class="stock-input" value="${p.stock}" min="0" />
        </td>
        <td>${Admin.stockBadge(p.stock)}</td>
        <td>
          <div class="table-actions">
            <button class="btn btn-primary btn-save" type="button">Guardar</button>
          </div>
        </td>
      </tr>`).join('');

    mount.innerHTML = `
      <table class="table">
        <thead><tr>
          <th>Imagen</th><th>Producto</th><th>Categoría</th><th>Precio</th>
          <th>Stock</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>`;

    mount.querySelectorAll('.btn-save').forEach((btn, i) => {
      btn.addEventListener('click', () => this.saveStock(btn));
    });

    Admin.renderPagination(document.getElementById('inv-pagination'), pg, (page) => {
      this.state.page = page;
      this.load();
    });
  },

  async saveStock(btn) {
    const tr = btn.closest('tr');
    const id = Number(tr.dataset.id);
    const input = tr.querySelector('.stock-input');
    const stock = Number(input.value);
    const name = tr.querySelector('strong').textContent;

    if (isNaN(stock) || stock < 0) {
      alert('Ingresa una cantidad de stock válida (0 o más).');
      return;
    }

    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    try {
      await APP.request('products.php?action=update-stock', {
        method: 'POST', csrf: true, body: { product_id: id, stock },
      });
      await Admin.flash(document.getElementById('inv-info'), `Stock de "${name}" actualizado.`);
      this.load();
    } catch (e) {
      alert(e.message);
      btn.disabled = false;
      btn.textContent = original;
    }
  },
};

/* ============================================================
   PEDIDOS
   ============================================================ */
const AdminOrders = {
  estado: '',

  init() {
    Admin.init({ onReady: () => {
      this.bindEvents();
      this.load();
    }});
  },

  bindEvents() {
    const apply = document.getElementById('filter-apply');
    apply && apply.addEventListener('click', () => {
      this.estado = document.getElementById('filter-estado').value;
      this.load();
    });
    const reload = document.getElementById('orders-reload');
    reload && reload.addEventListener('click', () => this.load());
  },

  async load() {
    const mount = document.getElementById('orders-table');
    mount.innerHTML = Admin.loader();
    try {
      const res = await APP.request('admin.php?action=orders', {
        query: { estado: this.estado, limit: 50 },
      });
      this.render(res.data.orders);
    } catch (e) {
      mount.innerHTML = '';
      APP.showAlert(document.getElementById('admin-error'), e.message);
    }
  },

  render(orders) {
    const mount = document.getElementById('orders-table');
    if (!orders || orders.length === 0) {
      mount.innerHTML = `<div class="empty-state"><div class="icon">&#128203;</div>No hay pedidos.</div>`;
      return;
    }

    const rows = orders.map((o) => `
      <tr data-id="${o.id}">
        <td><strong>#${o.id}</strong></td>
        <td>${escapeHtml(`${o.cliente_nombre} ${o.cliente_apellido}`.trim())}<br><span class="text-muted" style="font-size:.78rem;">${escapeHtml(o.cliente_email)}</span></td>
        <td>${APP.formatDate(o.created_at)}</td>
        <td class="price">${APP.formatPrice(o.total)}</td>
        <td>${APP.estadoBadge(o.estado)}</td>
        <td>
          <div class="table-actions">
            <select class="form-control status-select" style="width:130px;padding:6px 10px;">
              <option value="PENDIENTE" ${o.estado === 'PENDIENTE' ? 'selected' : ''}>Pendiente</option>
              <option value="PAGADO" ${o.estado === 'PAGADO' ? 'selected' : ''}>Pagado</option>
              <option value="RECHAZADO" ${o.estado === 'RECHAZADO' ? 'selected' : ''}>Rechazado</option>
            </select>
            <button class="btn btn-outline btn-detail" type="button">Ver detalle</button>
          </div>
        </td>
      </tr>`).join('');

    mount.innerHTML = `
      <table class="table">
        <thead><tr>
          <th>#</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>`;

    mount.querySelectorAll('.status-select').forEach((sel, i) => {
      sel.addEventListener('change', () => this.updateStatus(orders[i], sel.value));
    });
    mount.querySelectorAll('.btn-detail').forEach((btn, i) => {
      btn.addEventListener('click', () => this.showDetail(orders[i].id));
    });
  },

  async updateStatus(order, estado) {
    if (estado === order.estado) return;
    if (!confirm(`¿Marcar pedido #${order.id} como ${estado.toLowerCase()}?`)) {
      this.load();
      return;
    }
    try {
      await APP.request('orders.php?action=update-status', {
        method: 'POST', csrf: true, body: { id: order.id, estado },
      });
      this.load();
    } catch (e) {
      alert(e.message);
      this.load();
    }
  },

  async showDetail(id) {
    const modalMount = document.getElementById('order-modal');
    modalMount.innerHTML = Admin.loader();
    try {
      const res = await APP.request('orders.php?action=show', { query: { id } });
      this.renderModal(modalMount, res.data.order);
    } catch (e) {
      modalMount.innerHTML = '';
      alert(e.message);
    }
  },

  renderModal(mount, o) {
    const detalles = (o.detalles || []).map((d) => `
      <tr>
        <td>${escapeHtml(d.nombre_producto)}</td>
        <td class="num">${d.cantidad}</td>
        <td class="num">${APP.formatPrice(d.precio_unitario)}</td>
        <td class="num price">${APP.formatPrice(d.subtotal)}</td>
      </tr>`).join('');

    mount.innerHTML = `
      <div class="modal-overlay">
        <div class="modal">
          <div class="modal-header">
            <div>
              <div class="modal-title">Pedido #${o.id}</div>
              <div class="text-muted" style="font-size:.85rem;">${APP.formatDate(o.created_at)} · ${APP.estadoBadge(o.estado)}</div>
            </div>
            <button class="modal-close" type="button">&times;</button>
          </div>

          <dl class="detail-grid">
            <div><dt>Cliente</dt><dd>${escapeHtml(`${o.cliente_nombre} ${o.cliente_apellido}`.trim())}</dd></div>
            <div><dt>Email</dt><dd>${escapeHtml(o.cliente_email)}</dd></div>
            <div><dt>Dirección de envío</dt><dd>${escapeHtml(o.direccion_envio || '—')}</dd></div>
            <div><dt>Teléfono</dt><dd>${escapeHtml(o.telefono_contacto || '—')}</dd></div>
            <div><dt>Observaciones</dt><dd>${escapeHtml(o.observaciones || '—')}</dd></div>
          </dl>

          <table class="table">
            <thead><tr>
              <th>Producto</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Subtotal</th>
            </tr></thead>
            <tbody>${detalles || Admin.empty(4, 'Sin detalles.')}</tbody>
          </table>

          <div style="display:flex;justify-content:flex-end;gap:28px;margin-top:14px;font-weight:700;">
            <span>Total: <span class="price-big">${APP.formatPrice(o.total)}</span></span>
          </div>
        </div>
      </div>`;

    modalMount.querySelector('.modal-close').addEventListener('click', () => { mount.innerHTML = ''; });
    modalMount.querySelector('.modal-overlay').addEventListener('click', (e) => {
      if (e.target === e.currentTarget) mount.innerHTML = '';
    });
  },
};