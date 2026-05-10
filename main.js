/* =============================================
   GAMEVAULT — MAIN JAVASCRIPT
   ============================================= */

// ---- CART ----
const Cart = {
  get() { return JSON.parse(localStorage.getItem('gv_cart') || '[]'); },
  save(cart) { localStorage.setItem('gv_cart', JSON.stringify(cart)); this.updateBadge(); },
  add(product) {
    const cart = this.get();
    const idx = cart.findIndex(i => i.id === product.id);
    if (idx >= 0) cart[idx].qty++;
    else cart.push({ ...product, qty: 1 });
    this.save(cart);
    showToast(`${product.name} ditambahkan ke keranjang!`, 'success');
  },
  remove(id) {
    const cart = this.get().filter(i => i.id !== id);
    this.save(cart);
  },
  total() { return this.get().reduce((s, i) => s + i.price * i.qty, 0); },
  updateBadge() {
    const el = document.getElementById('cartCount');
    if (el) el.textContent = this.get().reduce((s, i) => s + i.qty, 0);
  }
};
Cart.updateBadge();

// ---- TOAST ----
function showToast(msg, type = 'info') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = msg;
  container.appendChild(toast);
  setTimeout(() => { toast.remove(); }, 3500);
}

// ---- NAVBAR SCROLL ----
window.addEventListener('scroll', () => {
  const nav = document.getElementById('navbar');
  if (nav) nav.style.background = window.scrollY > 40
    ? 'rgba(8,12,20,0.98)'
    : 'rgba(8,12,20,0.85)';
});

// ---- FORMAT RUPIAH ----
function formatRupiah(num) {
  return 'Rp ' + num.toLocaleString('id-ID');
}

// ---- LOAD PRODUCTS (via AJAX) ----
function loadProducts(params = {}) {
  const grid = document.getElementById('productsGrid');
  if (!grid) return;

  const qs = new URLSearchParams(params).toString();
  fetch(`api/products.php${qs ? '?' + qs : ''}`)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        grid.innerHTML = '<p style="color:var(--text-muted);text-align:center;grid-column:1/-1;padding:40px">Tidak ada produk ditemukan.</p>';
        return;
      }
      grid.innerHTML = data.map(p => `
        <div class="product-card" onclick="location.href='product.php?id=${p.id}'">
          <div class="product-img">${p.emoji || '🎮'}</div>
          <div class="product-body">
            <div class="product-badge">${p.category}</div>
            <div class="product-name">${p.name}</div>
            <div class="product-game">${p.game}</div>
            <div class="product-price">${formatRupiah(p.price)}</div>
          </div>
          <div class="product-footer">
            <span class="product-sold">Terjual ${p.sold}+</span>
            <button class="btn-add-cart" onclick="event.stopPropagation(); Cart.add({id:${p.id}, name:'${p.name}', price:${p.price}})">+ Keranjang</button>
          </div>
        </div>
      `).join('');
    })
    .catch(() => {
      // Fallback demo data
      const demo = [
        { id:1, emoji:'🗡️', category:'Diamond', name:'Diamond ML 100+5', game:'Mobile Legends', price:18000, sold:12000 },
        { id:2, emoji:'🗡️', category:'Diamond', name:'Diamond ML 250+30', game:'Mobile Legends', price:38000, sold:8500 },
        { id:3, emoji:'🔫', category:'Diamond', name:'Diamond FF 100', game:'Free Fire', price:15000, sold:9200 },
        { id:4, emoji:'🪖', category:'UC', name:'UC PUBG 60', game:'PUBG Mobile', price:17000, sold:6100 },
        { id:5, emoji:'✨', category:'Crystal', name:'Genesis Crystal 160', game:'Genshin Impact', price:24000, sold:4300 },
        { id:6, emoji:'🎯', category:'VP', name:'Valorant Points 1000', game:'Valorant', price:75000, sold:2800 },
        { id:7, emoji:'🎟️', category:'Voucher', name:'Google Play Rp 50.000', game:'Universal', price:52000, sold:15000 },
        { id:8, emoji:'🎟️', category:'Voucher', name:'Steam Wallet $5', game:'Universal', price:80000, sold:7600 },
      ];
      grid.innerHTML = demo.map(p => `
        <div class="product-card" onclick="location.href='product.php?id=${p.id}'">
          <div class="product-img" style="background:var(--bg-surface)">${p.emoji}</div>
          <div class="product-body">
            <div class="product-badge">${p.category}</div>
            <div class="product-name">${p.name}</div>
            <div class="product-game">${p.game}</div>
            <div class="product-price">${formatRupiah(p.price)}</div>
          </div>
          <div class="product-footer">
            <span class="product-sold">Terjual ${p.sold.toLocaleString()}+</span>
            <button class="btn-add-cart" onclick="event.stopPropagation(); Cart.add({id:${p.id}, name:'${p.name}', price:${p.price}})">+ Keranjang</button>
          </div>
        </div>
      `).join('');
    });
}

loadProducts({ limit: 8 });
