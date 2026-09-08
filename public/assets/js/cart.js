/* ============================================================
   PETLANDIA - Carrito de compras
   ============================================================ */

const Cart = {
  cart: null,

  async init() {
    if (!document.getElementById('cart-content')) return;

    // Requiere sesión
    const user = await APP.requireLogin();
    if (!user) return;

    await this.load();
  },

  async load() {
    const loader = document.getElementById('loader');
    const box = document.getElementById('cart-content');
    const errBox = document.getElementById('error-container');

    loader.classList.remove('hidden');
    box.innerHTML = '';
    errBox.innerHTML = '';

    try {
      const data = await APP.request('cart.php?action=get');
      this.cart = data.data.cart;
      this.render();
    } catch (e) {
      if (e.status === 401) {
        window.location.href = 'login.html';
        return;
      }
      APP.showAlert(errBox, e.message);
    } finally {
      loader.classList.add('hidden');
    }
  },

  render() {
    const box = document.getElementById('cart-content');
    const items = this.cart.items;

    if (!items.length) {
      box.innerHTML = `
        <div class="empty-state">
          <div class="icon">🛒</div>
          <p>Tu carrito está vacío.</p>
          <a href="catalog.html" class="btn btn-primary" style="margin-top:16px;">Ver catálogo</a>
        </div>`;
      return;
    }

    box.innerHTML = `
      <div class="cart-layout">
        <div class="cart-items">
          ${items.map((it) => this.itemRow(it)).join('')}
        </div>
        <aside class="cart-summary card">
          <h2 class="card-title">Resumen</h2>
          <div class="summary-row">
            <span>Productos</span><span id="summary-count">${this.cart.total_items}</span>
          </div>
          <div class="summary-row total">
            <span>Total</span><span id="summary-total">${APP.formatPrice(this.cart.total)}</span>
          </div>
          <a href="checkout.html" class="btn btn-primary btn-block">Ir a pagar</a>
          <button class="btn btn-ghost btn-block" id="cart-clear" style="margin-top:8px;">Vaciar carrito</button>
        </aside>
      </div>
    `;

    // Bindings de eventos
    box.querySelectorAll('.qty-input').forEach((input) => {
      input.addEventListener('change', () => this.updateQty(Number(input.dataset.id), Number(input.value)));
    });
    box.querySelectorAll('.remove-item').forEach((btn) => {
      btn.addEventListener('click', () => this.removeItem(Number(btn.dataset.id)));
    });
    document.getElementById('cart-clear').addEventListener('click', () => this.clear());
  },

  itemRow(it) {
    return `
      <div class="cart-item card">
        <div class="ci-thumb">
          ${it.imagen
            ? `<img src="../uploads/products/${escapeHtml(it.imagen)}" alt="${escapeHtml(it.nombre)}">`
            : '🐾'}
        </div>
        <div class="ci-info">
          <span class="ci-name">${escapeHtml(it.nombre)}</span>
          <span class="ci-price">${APP.formatPrice(it.precio)} c/u</span>
        </div>
        <div class="ci-qty">
          <label>Qty</label>
          <input type="number" class="form-control qty-input" data-id="${it.producto_id}"
            value="${it.cantidad}" min="1" max="${it.stock}">
        </div>
        <div class="ci-subtotal">
          <span>Subtotal</span>
          <strong>${APP.formatPrice(it.subtotal)}</strong>
        </div>
        <button class="remove-item" data-id="${it.producto_id}" title="Eliminar">✕</button>
      </div>`;
  },

  async updateQty(productId, quantity) {
    if (!quantity || quantity < 1) return this.reload();
    try {
      await APP.request('cart.php?action=update', {
        method: 'POST', csrf: true,
        body: { product_id: productId, quantity },
      });
      this.reload();
    } catch (e) {
      alert(e.message);
      this.reload();
    }
  },

  async removeItem(productId) {
    try {
      await APP.request('cart.php?action=remove', {
        method: 'POST', csrf: true,
        body: { product_id: productId },
      });
      this.reload();
    } catch (e) {
      alert(e.message);
    }
  },

  async clear() {
    if (!confirm('¿Vaciar el carrito por completo?')) return;
    try {
      await APP.request('cart.php?action=clear', { method: 'POST', csrf: true });
      this.reload();
    } catch (e) {
      alert(e.message);
    }
  },

  reload() {
    this.load().then(() => APP.refreshCartBadge());
  },
};

document.addEventListener('DOMContentLoaded', () => Cart.init());
