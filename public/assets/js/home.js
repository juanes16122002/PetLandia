/* ============================================================
   PETLANDIA - Carruseles del home
   ============================================================ */

/* ---------- Carrusel de banners (rectangular, autoplay) ---------- */
const BannerCarousel = {
  index: 0,
  timer: null,
  interval: 6000,

  init() {
    const track = document.querySelector('.banner-carousel');
    if (!track) return;

    this.banners = Array.from(track.children);
    if (!this.banners.length) return;

    this.track = track;
    this.count = this.banners.length;
    this.buildDots();
    this.bindControls();
    this.goTo(0);
    this.startAuto();
  },

  buildDots() {
    const dotsBox = document.querySelector('.banner-dots');
    if (!dotsBox) return;
    dotsBox.innerHTML = '';
    for (let i = 0; i < this.count; i++) {
      const d = document.createElement('button');
      d.className = 'dot';
      d.dataset.i = i;
      d.addEventListener('click', () => { this.goTo(i); this.restartAuto(); });
      dotsBox.appendChild(d);
    }
  },

  bindControls() {
    const prev = document.querySelector('.banner-prev');
    const next = document.querySelector('.banner-next');
    if (prev) prev.addEventListener('click', () => { this.prev(); this.restartAuto(); });
    if (next) next.addEventListener('click', () => { this.next(); this.restartAuto(); });

    // Pausar en hover
    const root = this.track.closest('.banner');
    if (root) {
      root.addEventListener('mouseenter', () => this.stopAuto());
      root.addEventListener('mouseleave', () => this.startAuto());
    }
  },

  goTo(i) {
    this.index = (i + this.count) % this.count;
    this.track.style.transform = `translateX(-${this.index * 100}%)`;
    document.querySelectorAll('.banner-dots .dot').forEach((d, k) => {
      d.classList.toggle('active', k === this.index);
    });
  },

  prev() { this.goTo(this.index - 1); },
  next() { this.goTo(this.index + 1); },

  startAuto() {
    if (this.timer || this.count < 2) return;
    this.timer = setInterval(() => this.next(), this.interval);
  },

  stopAuto() {
    if (this.timer) { clearInterval(this.timer); this.timer = null; }
  },

  restartAuto() {
    this.stopAuto();
    this.startAuto();
  },
};

/* ---------- Carrusel de productos ---------- */
const ProductCarousel = {
  scroll: 0,

  init() {
    const box = document.getElementById('featured-carousel');
    if (!box) return;
    this.load();
  },

  async load() {
    const box = document.getElementById('featured-carousel');
    box.innerHTML = '<div class="loader featured-loader"><div class="spinner"></div></div>';

    try {
      const data = await APP.request('products.php?action=list', { query: { page: 1 } });
      const products = (data.data.products || []).slice(0, 10);
      if (!products.length) {
        box.innerHTML = '<div class="empty-state"><p>Pronto habrá novedades.</p></div>';
        return;
      }
      box.innerHTML = products.map((p) => this.card(p)).join('');
      this.bind();
    } catch (e) {
      box.innerHTML = `<div class="empty-state"><p>No se pudieron cargar los productos.</p></div>`;
    }
  },

  card(p) {
    return `
      <article class="pc-card ${!p.disponible ? 'sold-out' : ''}">
        <div class="pc-thumb">
          ${p.imagen
            ? `<img src="../uploads/products/${escapeHtml(p.imagen)}" alt="${escapeHtml(p.nombre)}">`
            : '🐾'}
        </div>
        <div class="pc-body">
          <span class="pc-category">${escapeHtml(p.categoria)}</span>
          <a href="product.html?id=${p.id}" class="pc-name">${escapeHtml(p.nombre)}</a>
          <span class="pc-price">${APP.formatPrice(p.precio)}</span>
          <a href="product.html?id=${p.id}" class="btn btn-outline pc-view"
            ${p.disponible ? '' : 'disabled'}>Ver</a>
        </div>
      </article>`;
  },

  bind() {
    const track = document.getElementById('featured-carousel');
    const prev = document.getElementById('pc-prev');
    const next = document.getElementById('pc-next');

    const cardW = () => (track.children[0] ? track.children[0].offsetWidth + 20 : 260);
    const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const disabled = () => prev.disabled = this.scroll <= 0;

    const doScroll = (amount) => {
      this.scroll = Math.max(0, Math.min(this.scroll + amount, maxScroll()));
      track.scrollTo({ left: this.scroll, behavior: 'smooth' });
      next.disabled = this.scroll >= maxScroll();
      disabled();
    };

    prev.addEventListener('click', () => doScroll(-this.step()));
    next.addEventListener('click', () => doScroll(this.step()));
    window.addEventListener('resize', () => {
      this.scroll = Math.min(this.scroll, maxScroll());
      disabled();
    });
    disabled();
  },

  step() {
    const track = document.getElementById('featured-carousel');
    const card = track.children[0];
    const visible = Math.max(1, Math.floor(track.clientWidth / (card ? card.offsetWidth + 20 : 260)));
    return card ? (card.offsetWidth + 20) * visible : 260;
  },
};

document.addEventListener('DOMContentLoaded', () => {
  BannerCarousel.init();
  ProductCarousel.init();
});
