/* ============================================================
   PETLANDIA - Catálogo y detalle de producto
   ============================================================ */

/* ---------- Módulo: catálogo ---------- */
const Catalog = {
  state: {
    page: 1,
    search: '',
    category: '',
    orderBy: 'p.nombre',
    orderDir: 'ASC',
  },

  async init() {
    const container = document.getElementById('products-container');
    if (!container) return;

    await this.loadCategories();
    this.bindFilters();

    const params = new URLSearchParams(window.location.search);
    if (params.get('q')) {
      this.state.search = params.get('q');
      document.getElementById('filter-search').value = this.state.search;
    }

    await this.load();
  },

  bindFilters() {
    document.getElementById('filter-apply').addEventListener('click', () => this.applyFilters());
    document.getElementById('filter-reset').addEventListener('click', () => this.resetFilters());

    document.getElementById('filter-search').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this.applyFilters();
    });
  },

  async loadCategories() {
    const select = document.getElementById('filter-category');
    try {
      const data = await APP.request('products.php?action=categories', {
        query: { with_count: '1' },
      });
      data.data.categories.forEach((c) => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = `${c.nombre} (${c.total_productos ?? 0})`;
        select.appendChild(opt);
      });
    } catch (e) {
      /* manejo silencioso */
    }
  },

  applyFilters() {
    this.state.search = document.getElementById('filter-search').value.trim();
    this.state.category = document.getElementById('filter-category').value;
    const [ob, od] = document.getElementById('filter-order').value.split('|');
    this.state.orderBy = ob;
    this.state.orderDir = od;
    this.state.page = 1;
    this.load();
  },

  resetFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-category').value = '';
    document.getElementById('filter-order').value = 'p.nombre|ASC';
    this.state = { page: 1, search: '', category: '', orderBy: 'p.nombre', orderDir: 'ASC' };
    this.load();
  },

  goToPage(page) {
    this.state.page = page;
    this.load();
  },

  async load() {
    const loader = document.getElementById('loader');
    const grid = document.getElementById('products-container');
    const info = document.getElementById('result-info');
    const errBox = document.getElementById('error-container');

    loader.classList.remove('hidden');
    grid.innerHTML = '';
    errBox.innerHTML = '';

    try {
      const data = await APP.request('products.php?action=list', {
        query: {
          page: this.state.page,
          q: this.state.search,
          categoria_id: this.state.category,
          order_by: this.state.orderBy,
          order_dir: this.state.orderDir,
        },
      });

      const { products, pagination } = data.data;
      this.render(products);
      this.renderPagination(pagination);

      info.textContent = `${pagination.total} producto(s)` +
        (pagination.last_page > 1 ? ` · página ${pagination.current_page} de ${pagination.last_page}` : '');
    } catch (e) {
      APP.showAlert(errBox, e.message);
    } finally {
      loader.classList.add('hidden');
    }
  },

  render(products) {
    const grid = document.getElementById('products-container');
    if (!products.length) {
      grid.innerHTML = `
        <div class="empty-state" style="grid-column: 1/-1;">
          <div class="icon">🔍</div>
          <p>No encontramos productos con esos filtros.</p>
        </div>`;
      return;
    }

    grid.innerHTML = products.map((p) => `
      <article class="product-card ${!p.disponible ? 'sold-out' : ''}">
        <div class="thumb">
          ${p.imagen
            ? `<img src="../uploads/products/${escapeHtml(p.imagen)}" alt="${escapeHtml(p.nombre)}">`
            : '🐾'}
        </div>
        <div class="body">
          <span class="category">${escapeHtml(p.categoria)}</span>
          <a href="product.html?id=${p.id}" class="name">${escapeHtml(p.nombre)}</a>
          <span class="price">${APP.formatPrice(p.precio)}</span>
          <span class="stock-tag ${p.disponible ? 'stock-ok' : 'stock-no'}">
            ${p.disponible ? `✔ Disponible (${p.stock})` : '✖ Agotado'}
          </span>
          <div class="actions">
            <a href="product.html?id=${p.id}" class="btn btn-outline">Ver</a>
            <button class="btn btn-primary add-cart" data-id="${p.id}"
              ${p.disponible ? '' : 'disabled'}>Añadir</button>
          </div>
        </div>
      </article>
    `).join('');

    grid.querySelectorAll('.add-cart').forEach((btn) => {
      btn.addEventListener('click', () => this.addToCart(Number(btn.dataset.id)));
    });
  },

  renderPagination(pg) {
    const box = document.getElementById('pagination');
    box.innerHTML = '';

    if (pg.last_page <= 1) return;

    const btn = (label, page, disabled = false, active = false) => {
      const b = document.createElement('button');
      b.textContent = label;
      b.disabled = disabled;
      if (active) b.classList.add('active');
      if (!disabled) b.addEventListener('click', () => this.goToPage(page));
      return b;
    };

    box.appendChild(btn('‹', pg.current_page - 1, pg.current_page === 1));

    for (let p = 1; p <= pg.last_page; p++) {
      if (p === pg.current_page || p === 1 || p === pg.last_page ||
          Math.abs(p - pg.current_page) <= 1) {
        box.appendChild(btn(p, p, false, p === pg.current_page));
      } else if (
        (p === 2 && pg.current_page > 3) ||
        (p === pg.last_page - 1 && pg.current_page < pg.last_page - 2)
      ) {
        const ell = document.createElement('span');
        ell.textContent = '…';
        box.appendChild(ell);
      }
    }

    box.appendChild(btn('›', pg.current_page + 1, pg.current_page === pg.last_page));
  },

  async addToCart(productId) {
    if (!APP.user) {
      window.location.href = 'login.html';
      return;
    }
    try {
      await APP.request('cart.php?action=add', {
        method: 'POST',
        csrf: true,
        body: { product_id: productId, quantity: 1 },
      });
      APP.refreshCartBadge();
    } catch (e) {
      alert(e.message);
    }
  },
};

/* ---------- Módulo: detalle de producto ---------- */
const ProductDetail = {
  async init() {
    const container = document.getElementById('product-detail');
    if (!container) return;

    const id = Number(new URLSearchParams(window.location.search).get('id'));
    if (!id) {
      container.innerHTML = '<div class="empty-state"><p>Producto no encontrado.</p></div>';
      return;
    }

    try {
      const data = await APP.request('products.php?action=show', { query: { id } });
      this.render(data.data.product);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-error">${escapeHtml(e.message)}</div>`;
    }
  },

  render(p) {
    document.getElementById('product-detail').innerHTML = `
      <div class="pd-layout">
        <div class="pd-image">
          ${p.imagen
            ? `<img src="../uploads/products/${escapeHtml(p.imagen)}" alt="${escapeHtml(p.nombre)}">`
            : '<div class="pd-placeholder">🐾</div>'}
        </div>
        <div class="pd-info">
          <span class="pd-category">${escapeHtml(p.categoria)}</span>
          <h1 class="pd-name">${escapeHtml(p.nombre)}</h1>
          <div class="pd-price">${APP.formatPrice(p.precio)}</div>
          <span class="stock-tag ${p.disponible ? 'stock-ok' : 'stock-no'}">
            ${p.disponible ? `✔ Disponible · ${p.stock} en stock` : '✖ Agotado'}
          </span>
          <p class="pd-desc">${escapeHtml(p.descripcion || 'Sin descripción.')}</p>

          <div class="pd-buy">
            <label for="pd-qty">Cantidad</label>
            <div class="qty-row">
              <input type="number" id="pd-qty" class="form-control" value="1" min="1" max="${p.disponible ? p.stock : 0}">
              <button class="btn btn-primary" id="pd-add"
                ${p.disponible ? '' : 'disabled'}>Añadir al carrito</button>
            </div>
          </div>

          <p class="text-muted">SKU: ${escapeHtml(p.sku)}</p>
        </div>
      </div>
    `;

    document.getElementById('pd-add').addEventListener('click', () => {
      const qty = Number(document.getElementById('pd-qty').value) || 1;
      this.addToCart(p.id, qty);
    });
  },

  async addToCart(productId, quantity) {
    if (!APP.user) {
      window.location.href = 'login.html';
      return;
    }
    try {
      await APP.request('cart.php?action=add', {
        method: 'POST', csrf: true,
        body: { product_id: productId, quantity },
      });
      APP.refreshCartBadge();
      alert('Producto agregado al carrito.');
    } catch (e) {
      alert(e.message);
    }
  },
};

document.addEventListener('DOMContentLoaded', () => {
  Catalog.init();
  ProductDetail.init();
});
