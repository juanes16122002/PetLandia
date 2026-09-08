/* ============================================================
   PETLANDIA - Checkout y pago simulado
   ============================================================ */

const Checkout = {
  order: null,
  cartTotal: 0,

  async init() {
    if (!document.getElementById('step-shipping')) return;

    const user = await APP.requireLogin();
    if (!user) return;

    const loader = document.getElementById('loader');
    const errBox = document.getElementById('error-container');

    try {
      const data = await APP.request('cart.php?action=get');
      if (!data.data.cart.items.length) {
        loader.classList.add('hidden');
        errBox.innerHTML = `
          <div class="empty-state">
            <p>Tu carrito está vacío. No puedes hacer checkout.</p>
            <a href="catalog.html" class="btn btn-primary" style="margin-top:16px;">Ver catálogo</a>
          </div>`;
        return;
      }
      this.cartTotal = data.data.cart.total;
      this.renderShippingSummary(data.data.cart);
      this.showStep('step-shipping');
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

  renderShippingSummary(cart) {
    document.getElementById('shipping-summary').innerHTML = `
      <div class="summary-row"><span>Productos</span><span>${cart.total_items}</span></div>
      <div class="summary-row total"><span>Total a pagar</span><span>${APP.formatPrice(cart.total)}</span></div>`;
  },

  showStep(id) {
    ['step-shipping', 'step-payment', 'step-result'].forEach((s) => {
      document.getElementById(s).classList.add('hidden');
    });
    document.getElementById(id).classList.remove('hidden');
  },

  async createOrder() {
    const btn = document.getElementById('create-order-btn');
    const errBox = document.getElementById('error-container');
    errBox.innerHTML = '';
    btn.disabled = true;

    try {
      const data = await APP.request('orders.php?action=create', {
        method: 'POST',
        csrf: true,
        body: {
          direccion_envio: document.getElementById('direccion_envio').value.trim(),
          telefono_contacto: document.getElementById('telefono_contacto').value.trim(),
          observaciones: document.getElementById('observaciones').value.trim(),
        },
      });
      this.order = data.data.order;
      this.loadTestCards();
      document.getElementById('order-number').textContent = this.order.id;
      this.showStep('step-payment');
      APP.refreshCartBadge();
    } catch (e) {
      APP.showAlert(errBox, e.message);
      btn.disabled = false;
    }
  },

  async loadTestCards() {
    const box = document.getElementById('test-cards');
    box.innerHTML = '<p class="text-muted" style="margin-bottom:12px;">Tarjetas de prueba del simulador:</p>';
    try {
      const data = await APP.request('payments.php?action=test-cards');
      data.data.cards.forEach((c) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'test-card';
        el.innerHTML = `
          <strong>${escapeHtml(c.type)}</strong> ·
          <span>${escapeHtml(c.number)}</span> ·
          <em>${escapeHtml(c.result)}</em>`;
        el.addEventListener('click', () => {
          document.getElementById('card_number').value = c.number;
          document.getElementById('card_type').value = c.type;
        });
        box.appendChild(el);
      });
    } catch (e) {
      box.innerHTML = '';
    }
  },

  async pay() {
    const btn = document.getElementById('pay-btn');
    const errBox = document.getElementById('error-container');
    errBox.innerHTML = '';
    btn.disabled = true;

    try {
      const data = await APP.request('payments.php?action=process', {
        method: 'POST',
        csrf: true,
        body: {
          order_id: this.order.id,
          card_number: document.getElementById('card_number').value.trim(),
          card_holder: document.getElementById('card_holder').value.trim(),
          expiry: document.getElementById('expiry').value.trim(),
          cvv: document.getElementById('cvv').value.trim(),
        },
      });

      const payment = data.data.payment;
      const approved = payment.status === 'APROBADO';
      const box = document.getElementById('step-result');

      box.innerHTML = `
        <div class="text-center">
          <div style="font-size:4rem;">${approved ? '✅' : '❌'}</div>
          <h2 class="card-title">${approved ? '¡Pago aprobado!' : 'Pago rechazado'}</h2>
          <p>${escapeHtml(data.message)}</p>
          <div class="result-details card">
            <div class="summary-row"><span>Pedido</span><span>#${payment.order_id}</span></div>
            <div class="summary-row"><span>Estado</span><span>${APP.estadoBadge(payment.status)}</span></div>
            <div class="summary-row"><span>Tarjeta</span><span>•••• ${escapeHtml(payment.last_four)}</span></div>
            <div class="summary-row total"><span>Monto</span><span>${APP.formatPrice(payment.amount)}</span></div>
          </div>
          <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;">
            <a href="orders.html" class="btn btn-primary">Ver mis pedidos</a>
            ${approved
              ? '<a href="catalog.html" class="btn btn-outline">Seguir comprando</a>'
              : '<button type="button" class="btn btn-outline" id="retry-payment-btn">Reintentar pago</button>'}
          </div>
        </div>
      `;

      this.showStep('step-result');
      APP.refreshCartBadge();

      if (!approved) {
        const retryBtn = document.getElementById('retry-payment-btn');
        if (retryBtn) retryBtn.addEventListener('click', () => this.retryPayment());
      }
    } catch (e) {
      APP.showAlert(errBox, e.message);
      btn.disabled = false;
    }
  },

  retryPayment() {
    const btn = document.getElementById('pay-btn');
    if (btn) btn.disabled = false;
    this.showStep('step-payment');
  },

  bind() {
    document.getElementById('create-order-btn').addEventListener('click', () => this.createOrder());
    document.getElementById('payment-form').addEventListener('submit', (e) => {
      e.preventDefault();
      this.pay();
    });
  },
};

document.addEventListener('DOMContentLoaded', () => {
  Checkout.bind();
  Checkout.init();
});
